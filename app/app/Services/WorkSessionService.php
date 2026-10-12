<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkSessionService
{
    public const DEFAULT_ROUTE = 'Langsiran IKT - JMT2';

    /**
     * @param  array{date: string, route: string, open_time: string, close_time: string, quota: int}  $data
     */
    public function create(array $data, User $admin): WorkSession
    {
        return WorkSession::query()->create([
            'date' => $data['date'],
            'route' => $data['route'],
            'opens_at' => $this->combineDateTime($data['date'], $data['open_time']),
            'closes_at' => $this->combineDateTime($data['date'], $data['close_time']),
            'quota' => (int) $data['quota'],
            'created_by' => $admin->id,
        ]);
    }

    /**
     * @param  array{date: string, route: string, open_time: string, close_time: string, quota: int}  $data
     */
    public function update(WorkSession $session, array $data): WorkSession
    {
        $session->update([
            'date' => $data['date'],
            'route' => $data['route'],
            'opens_at' => $this->combineDateTime($data['date'], $data['open_time']),
            'closes_at' => $this->combineDateTime($data['date'], $data['close_time']),
            'quota' => (int) $data['quota'],
        ]);

        return $session->refresh();
    }

    public function delete(WorkSession $session): void
    {
        if ($session->sessionDrivers()->exists() || $session->tripReports()->exists()) {
            throw new RuntimeException('Sesi tidak dapat dihapus karena sudah ada data roster atau laporan.');
        }

        DB::transaction(function () use ($session) {
            $session->delete();
        });
    }

    private function combineDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}
