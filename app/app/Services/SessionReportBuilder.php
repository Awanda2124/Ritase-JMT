<?php

namespace App\Services;

use App\Enums\TripReportStatus;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;

class SessionReportBuilder
{
    public const MAX_COLUMNS = 7;

    /**
     * @return array{
     *     rows: list<array{no: int, name: string, ktp: string, chassis: list<string>, count: int, cost: int, note: ?string}>,
     *     total_trips: int,
     *     total_cost: int,
     *     pending: int,
     *     rate: int
     * }
     */
    public function build(WorkSession $session): array
    {
        $reports = TripReport::query()
            ->where('work_session_id', $session->id)
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get()
            ->groupBy('driver_id');

        $rate = $session->ratePerTrip();

        $rows = [];
        $sessionDrivers = SessionDriver::query()
            ->with('driver')
            ->where('work_session_id', $session->id)
            ->orderBy('registered_at')
            ->orderBy('id')
            ->get();

        foreach ($sessionDrivers as $index => $sessionDriver) {
            $cocok = ($reports[$sessionDriver->driver_id] ?? collect())
                ->where('status', TripReportStatus::Cocok);

            $count = $cocok->count();

            $rows[] = [
                'no' => $index + 1,
                'name' => $sessionDriver->driver->name,
                'ktp' => (string) $sessionDriver->driver->ktp,
                'chassis' => $cocok->pluck('chassis_last4')->take(self::MAX_COLUMNS)->values()->all(),
                'count' => $count,
                'cost' => $count * $rate,
                'note' => $sessionDriver->note,
            ];
        }

        $totalTrips = array_sum(array_column($rows, 'count'));

        return [
            'rows' => $rows,
            'total_trips' => $totalTrips,
            'total_cost' => $totalTrips * $rate,
            'pending' => $reports->flatten()->where('status', TripReportStatus::Menunggu)->count(),
            'rate' => $rate,
        ];
    }
}