<?php

namespace App\Http\Controllers;

use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\SettingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SettingService $settings): View
    {
        $today = now()->toDateString();

        // Sesi terbaru (bukan cuma hari ini) — biar dashboard tetep nampilin data
        // walau sesi terakhir udah lewat.
        $activeSession = WorkSession::query()
            ->withCount('sessionDrivers')
            ->latest('date')
            ->first();

        $recentDrivers = collect();
        $reportCounts = collect();

        if ($activeSession) {
            $recentDrivers = SessionDriver::query()
                ->where('work_session_id', $activeSession->id)
                ->with('driver')
                ->latest('registered_at')
                ->limit(6)
                ->get();

            $reportCounts = TripReport::query()
                ->where('work_session_id', $activeSession->id)
                ->selectRaw('driver_id, COUNT(*) as total')
                ->groupBy('driver_id')
                ->pluck('total', 'driver_id');
        }

        return view('dashboard', [
            'ratePerTrip' => $settings->ratePerTrip(),
            'radiusMeters' => $settings->radiusMeters(),
            'sessions' => WorkSession::query()
                ->withCount('sessionDrivers')
                ->latest('date')
                ->limit(5)
                ->get(),
            'totalSessions' => WorkSession::count(),
            'totalDrivers' => $activeSession ? $activeSession->session_drivers_count : 0,
            'activeSession' => $activeSession,
            'recentDrivers' => $recentDrivers,
            'reportCounts' => $reportCounts,
            'todayCheckIns' => SessionDriver::query()->whereDate('checked_in_at', $today)->count(),
            'todayReports' => TripReport::query()->whereDate('reported_at', $today)->count(),
        ]);
    }
}