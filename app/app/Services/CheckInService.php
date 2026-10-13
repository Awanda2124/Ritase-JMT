<?php

namespace App\Services;

use App\Exceptions\CheckInClosedException;
use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\ReportingClosedException;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    private const IP_MAX_ATTEMPTS = 60;

    private const DRIVER_MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 60;

    public function __construct(private GeofenceService $geofence) {}

    /**
     * Check-in oleh supir sendiri. Idempotent: supir yang sudah check-in boleh
     * mengulang (misalnya setelah menutup browser) tanpa mengubah jam aslinya,
     * dan itu tetap diizinkan setelah batas check-in lewat.
     */
    public function checkIn(int $workSessionId, int $driverId, float $lat, float $lng): SessionDriver
    {
        $this->throttle($driverId);

        $this->geofence->ensureWithinRadius($lat, $lng);

        $session = WorkSession::query()->findOrFail($workSessionId);

        if (! $session->isReportingOpen()) {
            throw new ReportingClosedException;
        }

        return DB::transaction(function () use ($session, $driverId, $lat, $lng): SessionDriver {
            $sessionDriver = SessionDriver::query()
                ->where('work_session_id', $session->id)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            if ($sessionDriver === null) {
                throw new DriverNotInRosterException;
            }

            if ($sessionDriver->checked_in_at !== null) {
                return $sessionDriver;
            }

            if (! $session->isCheckInOpen()) {
                throw new CheckInClosedException;
            }

            $sessionDriver->update([
                'checked_in_at' => now(),
                'checked_in_lat' => $lat,
                'checked_in_lng' => $lng,
            ]);

            return $sessionDriver;
        }, 3);
    }

    /**
     * Check-in manual oleh admin untuk supir yang telat. Tidak terikat lokasi
     * maupun batas check-in, tapi tercatat di audit log.
     */
    public function checkInManually(SessionDriver $sessionDriver, User $admin): SessionDriver
    {
        return DB::transaction(function () use ($sessionDriver, $admin): SessionDriver {
            $locked = SessionDriver::query()
                ->whereKey($sessionDriver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->checked_in_at !== null) {
                return $locked;
            }

            $locked->update([
                'checked_in_at' => now(),
                'checked_in_by' => $admin->id,
            ]);

            activity()
                ->causedBy($admin)
                ->performedOn($locked)
                ->withProperties(['driver_id' => $locked->driver_id])
                ->log('Check-in manual oleh admin');

            return $locked;
        });
    }

    private function throttle(int $driverId): void
    {
        $ipKey = 'check-in:ip:'.request()->ip();
        $driverKey = 'check-in:driver:'.$driverId;

        if (RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($driverKey, self::DRIVER_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'driverId' => 'Terlalu banyak percobaan. Coba lagi sebentar.',
            ]);
        }

        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($driverKey, self::DECAY_SECONDS);
    }
}
