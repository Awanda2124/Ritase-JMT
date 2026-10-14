<?php

namespace App\Services;

use App\Models\SessionGroup;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WorkSessionService
{
    public const DEFAULT_ROUTE = 'Langsiran IKT - JMT2';

    public const DEFAULT_CHECKIN_TIME = '20:00';

    public const DEFAULT_REPORT_TIME = '06:00'; // hari berikutnya

    /**
     * @param  array{date: string, route: string, open_time: string, close_time: string, groups: array<int, array{id?: ?int, name: string, quota: int}>, checkin_closes_at?: ?string, report_closes_at?: ?string}  $data
     */
    public function create(array $data, User $admin): WorkSession
    {
        return DB::transaction(function () use ($data, $admin) {
            $session = WorkSession::query()->create([
                ...$this->payload($data),
                'created_by' => $admin->id,
            ]);

            $this->syncGroups($session, $data['groups']);

            return $session;
        });
    }

    /**
     * @param  array{date: string, route: string, open_time: string, close_time: string, groups: array<int, array{id?: ?int, name: string, quota: int}>, checkin_closes_at?: ?string, report_closes_at?: ?string}  $data
     */
    public function update(WorkSession $session, array $data): WorkSession
    {
        return DB::transaction(function () use ($session, $data) {
            $session->update($this->payload($data));
            $this->syncGroups($session, $data['groups']);

            return $session->refresh();
        });
    }

    public function delete(WorkSession $session): void
    {
        if ($session->sessionDrivers()->exists() || $session->tripReports()->exists()) {
            throw new RuntimeException('Sesi tidak dapat dihapus karena sudah ada data roster atau laporan.');
        }

        DB::transaction(function () use ($session) {
            $session->groups()->delete();
            $session->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $opensAt = $this->combineDateTime($data['date'], $data['open_time']);
        $closesAt = $this->combineDateTime($data['date'], $data['close_time']);

        $checkinClosesAt = filled($data['checkin_closes_at'] ?? null)
            ? Carbon::parse($data['checkin_closes_at'], config('app.timezone'))
            : $this->combineDateTime($data['date'], self::DEFAULT_CHECKIN_TIME);

        $reportClosesAt = filled($data['report_closes_at'] ?? null)
            ? Carbon::parse($data['report_closes_at'], config('app.timezone'))
            : $this->combineDateTime($data['date'], self::DEFAULT_REPORT_TIME)->addDay();

        if ($checkinClosesAt->lt($opensAt)) {
            throw ValidationException::withMessages([
                'checkin_closes_at' => 'Batas check-in tidak boleh sebelum registrasi dibuka.',
            ]);
        }

        if ($reportClosesAt->lt($checkinClosesAt)) {
            throw ValidationException::withMessages([
                'report_closes_at' => 'Batas laporan tidak boleh sebelum batas check-in.',
            ]);
        }

        return [
            'date' => $data['date'],
            'route' => $data['route'],
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'checkin_closes_at' => $checkinClosesAt,
            'report_closes_at' => $reportClosesAt,
            // Kuota sesi = total kuota semua kelompok.
            'quota' => (int) collect($data['groups'])->sum('quota'),
        ];
    }

    /**
     * @param  array<int, array{id?: ?int, name: string, quota: int}>  $groups
     */
    private function syncGroups(WorkSession $session, array $groups): void
    {
        $keptIds = [];

        foreach ($groups as $g) {
            $group = filled($g['id'] ?? null)
                ? $session->groups()->findOrFail($g['id'])
                : new SessionGroup([
                    'work_session_id' => $session->id,
                    'token' => Str::lower(Str::random(40)),
                ]);

            $registered = $group->exists ? $group->sessionDrivers()->count() : 0;

            if ((int) $g['quota'] < $registered) {
                throw ValidationException::withMessages([
                    'groups' => "Kuota {$g['name']} tidak boleh di bawah jumlah yang sudah terdaftar ({$registered}).",
                ]);
            }

            $group->fill([
                'name' => $g['name'],
                'quota' => (int) $g['quota'],
            ])->save();

            $keptIds[] = $group->id;
        }

        foreach ($session->groups()->whereNotIn('id', $keptIds)->get() as $removed) {
            if ($removed->sessionDrivers()->exists()) {
                throw ValidationException::withMessages([
                    'groups' => "Kelompok {$removed->name} sudah punya supir terdaftar, tidak bisa dihapus.",
                ]);
            }

            $removed->delete();
        }
    }

    private function combineDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, config('app.timezone'));
    }
}