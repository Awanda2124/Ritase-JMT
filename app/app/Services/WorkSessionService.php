<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WorkSessionService
{
    public const DEFAULT_ROUTE = 'Langsiran IKT - JMT2';

    // Default batas check-in: jam ini pada tanggal sesi.
    public const DEFAULT_CHECKIN_CLOSE_TIME = '20:00';

    // Default batas lapor: jam ini pada hari BERIKUTNYA (shift malam).
    public const DEFAULT_REPORT_CLOSE_TIME = '06:00';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $admin): WorkSession
    {
        return WorkSession::query()->create(
            $this->attributes($data) + ['created_by' => $admin->id],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkSession $session, array $data): WorkSession
    {
        $session->update($this->attributes($data));

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $opensAt = $this->combineDateTime($data['date'], $data['open_time']);
        $closesAt = $this->combineDateTime($data['date'], $data['close_time']);

        $checkinClosesAt = filled($data['checkin_closes_at'] ?? null)
            ? Carbon::parse($data['checkin_closes_at'], config('app.timezone'))
            : $this->combineDateTime($data['date'], self::DEFAULT_CHECKIN_CLOSE_TIME);

        $reportClosesAt = filled($data['report_closes_at'] ?? null)
            ? Carbon::parse($data['report_closes_at'], config('app.timezone'))
            : $this->combineDateTime($data['date'], self::DEFAULT_REPORT_CLOSE_TIME)->addDay();

        if ($checkinClosesAt->lt($opensAt)) {
            throw ValidationException::withMessages([
                'checkin_closes_at' => 'Batas check-in tidak boleh sebelum jam buka registrasi.',
            ]);
        }

        if ($reportClosesAt->lt($checkinClosesAt)) {
            throw ValidationException::withMessages([
                'report_closes_at' => 'Batas lapor tidak boleh sebelum batas check-in.',
            ]);
        }

        return [
            'date' => $data['date'],
            'route' => $data['route'],
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'checkin_closes_at' => $checkinClosesAt,
            'report_closes_at' => $reportClosesAt,
            'quota' => (int) $data['quota'],
        ];
    }

    private function combineDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}