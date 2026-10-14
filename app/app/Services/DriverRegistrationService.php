<?php

namespace App\Services;

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Exceptions\SessionNotOpenException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\SessionGroup;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class DriverRegistrationService
{
    private const IP_MAX_ATTEMPTS = 60;   // per menit per IP (banyak supir bisa satu jaringan)

    private const KTP_MAX_ATTEMPTS = 5;   // per menit per KTP (cegah spam satu orang)

    private const DECAY_SECONDS = 60;

    public function register(SessionGroup $group, string $name, string $ktp): SessionDriver
    {
        $this->throttle($ktp);

        // 3 = percobaan ulang otomatis kalau MySQL membatalkan transaksi karena deadlock
        return DB::transaction(function () use ($group, $name, $ktp) {
            // Kunci baris kelompok: pendaftar kelompok yang sama antre di sini,
            // sedangkan kelompok lain tidak saling menunggu.
            $lockedGroup = SessionGroup::query()
                ->whereKey($group->id)
                ->lockForUpdate()
                ->firstOrFail();

            $session = $lockedGroup->workSession;

            if (! $session->isOpen()) {
                throw new SessionNotOpenException;
            }

            $driver = $this->findOrCreateDriver($name, $ktp);

            // "Sudah terdaftar" dicek per SESI, jadi satu supir tidak bisa
            // masuk dua kelompok sekaligus (misalnya Yayasan dan Akamsi).
            $alreadyRegistered = SessionDriver::query()
                ->where('work_session_id', $session->id)
                ->where('driver_id', $driver->id)
                ->exists();

            if ($alreadyRegistered) {
                throw new AlreadyRegisteredException;
            }

            $registeredCount = SessionDriver::query()
                ->where('session_group_id', $lockedGroup->id)
                ->count();

            if ($registeredCount >= $lockedGroup->quota) {
                throw new QuotaFullException; // exception = rollback, driver baru tidak tersimpan
            }

            return SessionDriver::query()->create([
                'work_session_id' => $session->id,
                'session_group_id' => $lockedGroup->id,
                'driver_id' => $driver->id,
                'registered_at' => now(),
            ]);
        }, 3);
    }

    public function removeFromRoster(SessionDriver $sessionDriver, User $admin): void
    {
        DB::transaction(function () use ($sessionDriver, $admin) {
            $sessionDriver->load(['driver', 'workSession', 'group']);

            activity()
                ->causedBy($admin)
                ->performedOn($sessionDriver->workSession)
                ->withProperties([
                    'session_driver_id' => $sessionDriver->id,
                    'driver_id' => $sessionDriver->driver_id,
                    'driver_name' => $sessionDriver->driver?->name,
                    'group_name' => $sessionDriver->group?->name,
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
            // Request lain di kelompok berbeda baru saja membuat KTP yang sama.
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