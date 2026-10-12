<?php

namespace App\Http\Controllers;

use App\Models\WorkSession;
use App\Services\SettingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(SettingService $settings): View
    {
        return view('dashboard', [
            'ratePerTrip' => $settings->ratePerTrip(),
            'radiusMeters' => $settings->radiusMeters(),
            'sessions' => WorkSession::query()
                ->withCount('sessionDrivers')
                ->latest('date')
                ->limit(5)
                ->get(),
        ]);
    }
}
