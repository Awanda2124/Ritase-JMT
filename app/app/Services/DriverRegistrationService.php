<?php

namespace App\Services;

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class DriverRegistrationService
{
    public function register(WorkSession $session, string $name, string $ktp): SessionDriver
    {
        $this->ensureIsNotRateLimited();
        RateLimiter::hit($this->throttleKey(), 60);

        return DB::transaction(function () use ($session, $name, $ktp) {
            $lockedSession = WorkSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $registeredCount = SessionDriver::query()
                ->where('work_session_id', $lockedSession->id)
                ->count();

            if ($registeredCount >= $lockedSession->quota) {
                throw new QuotaFullException;
            }

            $driver = Driver::query()
                ->where('ktp', $ktp)
                ->lockForUpdate()
                ->first();

            if ($driver === null) {
                $driver = Driver::query()->create([
                    'name' => $name,
                    'ktp' => $ktp,
                ]);
            }

            $alreadyRegistered = SessionDriver::query()
                ->where('work_session_id', $lockedSession->id)
                ->where('driver_id', $driver->id)
                ->exists();

            if ($alreadyRegistered) {
                throw new AlreadyRegisteredException;
            }

            return SessionDriver::query()->create([
                'work_session_id' => $lockedSession->id,
                'driver_id' => $driver->id,
                'registered_at' => now(),
            ]);
        });
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

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 10)) {
            return;
        }

        throw ValidationException::withMessages([
            'ktp' => 'Terlalu banyak percobaan. Coba lagi nanti.',
        ]);
    }

    private function throttleKey(): string
    {
        return 'register-driver:'.request()->ip();
    }
}
