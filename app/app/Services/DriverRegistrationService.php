<?php

namespace App\Services;

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Exceptions\SessionNotOpenException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

// class DriverRegistrationService
// {
//     public function register(WorkSession $session, string $name, string $ktp): SessionDriver
//     {
//         $this->ensureIsNotRateLimited();
//         RateLimiter::hit($this->throttleKey(), 60);

//         return DB::transaction(function () use ($session, $name, $ktp) {
//             $lockedSession = WorkSession::query()
//                 ->whereKey($session->id)
//                 ->lockForUpdate()
//                 ->firstOrFail();

//             $registeredCount = SessionDriver::query()
//                 ->where('work_session_id', $lockedSession->id)
//                 ->count();

//             if ($registeredCount >= $lockedSession->quota) {
//                 throw new QuotaFullException;
//             }

//             $driver = Driver::query()
//                 ->where('ktp', $ktp)
//                 ->lockForUpdate()
//                 ->first();

//             if ($driver === null) {
//                 $driver = Driver::query()->create([
//                     'name' => $name,
//                     'ktp' => $ktp,
//                 ]);
//             }

//             $alreadyRegistered = SessionDriver::query()
//                 ->where('work_session_id', $lockedSession->id)
//                 ->where('driver_id', $driver->id)
//                 ->exists();

//             if ($alreadyRegistered) {
//                 throw new AlreadyRegisteredException;
//             }

//             return SessionDriver::query()->create([
//                 'work_session_id' => $lockedSession->id,
//                 'driver_id' => $driver->id,
//                 'registered_at' => now(),
//             ]);
//         });
//     }

//     public function removeFromRoster(SessionDriver $sessionDriver, User $admin): void
//     {
//         DB::transaction(function () use ($sessionDriver, $admin) {
//             $sessionDriver->load(['driver', 'workSession']);

//             activity()
//                 ->causedBy($admin)
//                 ->performedOn($sessionDriver->workSession)
//                 ->withProperties([
//                     'session_driver_id' => $sessionDriver->id,
//                     'driver_id' => $sessionDriver->driver_id,
//                     'driver_name' => $sessionDriver->driver?->name,
//                     'ktp_masked' => $sessionDriver->driver?->maskedKtp(),
//                 ])
//                 ->log('Menghapus supir dari roster');

//             $sessionDriver->delete();
//         });
//     }

//     private function ensureIsNotRateLimited(): void
//     {
//         if (! RateLimiter::tooManyAttempts($this->throttleKey(), 10)) {
//             return;
//         }

//         throw ValidationException::withMessages([
//             'ktp' => 'Terlalu banyak percobaan. Coba lagi nanti.',
//         ]);
//     }

//     private function throttleKey(): string
//     {
//         return 'register-driver:'.request()->ip();
//     }
// }

class DriverRegistrationService
{
    private const IP_MAX_ATTEMPTS = 60;   // per menit per IP (banyak supir bisa satu jaringan)

    private const KTP_MAX_ATTEMPTS = 5;   // per menit per KTP (cegah spam satu orang)

    private const DECAY_SECONDS = 60;

    public function register(WorkSession $session, string $name, string $ktp): SessionDriver
    {
        $this->throttle($ktp);

        // 3 = percobaan ulang otomatis kalau MySQL membatalkan transaksi karena deadlock
        return DB::transaction(function () use ($session, $name, $ktp) {
            // Kunci baris sesi: semua pendaftar sesi yang sama antre di sini.
            $lockedSession = WorkSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSession->isOpen()) {
                throw new SessionNotOpenException;
            }

            $driver = $this->findOrCreateDriver($name, $ktp);

            // Cek "sudah terdaftar" sebelum kuota, supaya pesannya tepat
            // untuk supir yang sudah masuk lalu klik daftar lagi.
            $alreadyRegistered = SessionDriver::query()
                ->where('work_session_id', $lockedSession->id)
                ->where('driver_id', $driver->id)
                ->exists();

            if ($alreadyRegistered) {
                throw new AlreadyRegisteredException;
            }

            $registeredCount = SessionDriver::query()
                ->where('work_session_id', $lockedSession->id)
                ->count();

            if ($registeredCount >= $lockedSession->quota) {
                throw new QuotaFullException; // exception = rollback, driver baru tidak tersimpan
            }

            return SessionDriver::query()->create([
                'work_session_id' => $lockedSession->id,
                'driver_id' => $driver->id,
                'registered_at' => now(),
            ]);
        }, 3);
    }

    public function removeFromRoster(SessionDriver $sessionDriver, User $admin): void
    {
        DB::transaction(function () use ($sessionDriver, $admin) {
            $sessionDriver->load(['driver', 'workSession']);

            activity()
                ->causedBy($admin)
                ->performedOn($sessionDriver->workSession)
                ->withProperties([
                    'session_driver_id' => $sessionDriver->id,
                    'driver_id' => $sessionDriver->driver_id,
                    'driver_name' => $sessionDriver->driver?->name,
                    'ktp_masked' => $sessionDriver->driver?->maskedKtp(),
                ])
                ->log('Menghapus supir dari roster');

            $sessionDriver->delete();
        });
    }

    private function findOrCreateDriver(string $name, string $ktp): Driver
    {
        try {
            return Driver::query()->firstOrCreate(['ktp' => $ktp], ['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            // Request lain di sesi berbeda baru saja membuat KTP yang sama.
            // Di MySQL transaksi tetap hidup setelah error ini, jadi aman dibaca ulang.
            return Driver::query()->where('ktp', $ktp)->firstOrFail();
        }
    }

    private function throttle(string $ktp): void
    {
        $ipKey = 'register-driver:ip:'.request()->ip();
        // KTP di-hash agar nomor asli tidak tersimpan di cache/log.
        $ktpKey = 'register-driver:ktp:'.hash('sha256', $ktp);

        if (RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ktpKey, self::KTP_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'ktp' => 'Terlalu banyak percobaan. Coba lagi sebentar.',
            ]);
        }

        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($ktpKey, self::DECAY_SECONDS);
    }
}
