<?php

namespace App\Services;

use App\Enums\TripReportStatus;
use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\ReportingClosedException;
use App\Exceptions\ReportLimitExceededException;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Exceptions\NotCheckedInException;
use InvalidArgumentException;

class TripReportService
{
    public const MAX_TRIPS_PER_SESSION = 7;

    private const IP_MAX_ATTEMPTS = 60;      // per menit per IP (banyak supir bisa satu jaringan)
    private const DRIVER_MAX_ATTEMPTS = 10;  // per menit per supir
    private const DECAY_SECONDS = 60;

    public function __construct(private GeofenceService $geofence) {}

    /**
     * @param  array<int, string>  $chassisLast4  4 digit terakhir sasis, satu per ritase
     * @return int jumlah laporan yang tersimpan
     */
    public function submit(int $workSessionId, int $driverId, array $chassisLast4, float $lat, float $lng): int
    {
        $chassisLast4 = array_values($chassisLast4);
        $count = count($chassisLast4);

        foreach ($chassisLast4 as $chassis) {
            if (! is_string($chassis) || preg_match('/^\d{4}$/', $chassis) !== 1) {
                throw new InvalidArgumentException('Sasis harus 4 digit angka.');
            }
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Minimal satu sasis.');
        }

        if ($count > self::MAX_TRIPS_PER_SESSION) {
            throw new ReportLimitExceededException(0);
        }

        $this->throttle($driverId);

        // Penjaga sebenarnya ada di server: lokasi dicek ulang di sini.
        $this->geofence->ensureWithinRadius($lat, $lng);

        $session = WorkSession::query()->findOrFail($workSessionId);

        if (! $session->isReportingOpen()) {
            throw new ReportingClosedException;
        }

        return DB::transaction(function () use ($session, $driverId, $chassisLast4, $count, $lat, $lng): int {
            // Kunci baris roster supir ini supaya dua kiriman bersamaan
            // dari supir yang sama tidak bisa menembus batas 7.
            $sessionDriver = SessionDriver::query()
                ->where('work_session_id', $session->id)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            if ($sessionDriver === null) {
                throw new DriverNotInRosterException;
            }

            if ($sessionDriver->checked_in_at === null) {
                throw new NotCheckedInException;
            }

            $existing = TripReport::query()
                ->where('work_session_id', $session->id)
                ->where('driver_id', $driverId)
                ->where('status', '!=', TripReportStatus::Void->value)
                ->count();

            if ($existing + $count > self::MAX_TRIPS_PER_SESSION) {
                throw new ReportLimitExceededException($existing);
            }

            $reportedAt = now();

            foreach ($chassisLast4 as $chassis) {
                TripReport::query()->create([
                    'work_session_id' => $session->id,
                    'driver_id' => $driverId,
                    'chassis_last4' => $chassis,
                    'status' => TripReportStatus::Menunggu,
                    'lat' => $lat,
                    'lng' => $lng,
                    'reported_at' => $reportedAt,
                ]);
            }

            return $count;
        }, 3);
    }

    private function throttle(int $driverId): void
    {
        $ipKey = 'trip-report:ip:'.request()->ip();
        $driverKey = 'trip-report:driver:'.$driverId;

        if (RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($driverKey, self::DRIVER_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'chassis' => 'Terlalu banyak percobaan. Coba lagi sebentar.',
            ]);
        }

        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
        RateLimiter::hit($driverKey, self::DECAY_SECONDS);
    }
}