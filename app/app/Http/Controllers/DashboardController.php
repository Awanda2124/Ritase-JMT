<?php

namespace App\Http\Controllers;

use App\Enums\TripReportStatus;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\SettingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SettingService $settings): View
    {
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        // Hanya sesi yang sedang berjalan (sudah dibuka, batas lapor belum lewat).
        $activeSession = WorkSession::query()
            ->withCount('sessionDrivers')
            ->reportable()
            ->latest('date')
            ->first();

        $recentDrivers = collect();
        $reportCounts = collect();
        $hadirCount = 0;

        if ($activeSession) {
            // Semua supir di sesi ini (tabel di view yang di-scroll).
            $recentDrivers = SessionDriver::query()
                ->where('work_session_id', $activeSession->id)
                ->with('driver:id,name,ktp')
                ->latest('registered_at')
                ->get();

            $hadirCount = $recentDrivers->whereNotNull('checked_in_at')->count();

            // Ritase per supir = semua laporan kecuali yang di-void.
            $reportCounts = TripReport::query()
                ->where('work_session_id', $activeSession->id)
                ->where('status', '!=', TripReportStatus::Void->value)
                ->selectRaw('driver_id, COUNT(*) as total')
                ->groupBy('driver_id')
                ->pluck('total', 'driver_id');
        }

        return view('dashboard', [
            'ratePerTrip' => $settings->ratePerTrip(),
            'radiusMeters' => $settings->radiusMeters(),
            'totalSessions' => WorkSession::count(),
            'totalDrivers' => $activeSession ? $activeSession->session_drivers_count : 0,
            'activeSession' => $activeSession,
            'recentDrivers' => $recentDrivers,
            'reportCounts' => $reportCounts,
            'hadirCount' => $hadirCount,
            'todayCheckIns' => SessionDriver::query()
                ->whereBetween('checked_in_at', [$startOfDay, $endOfDay])
                ->count(),
            'todayReports' => TripReport::query()
                ->whereBetween('reported_at', [$startOfDay, $endOfDay])
                ->count(),
        ]);
    }
}