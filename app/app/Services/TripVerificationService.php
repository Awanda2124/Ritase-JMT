<?php

namespace App\Services;

use App\Enums\TripReportStatus;
use App\Models\Receipt;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\RoleName;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TripVerificationService
{
    public function setStatus(TripReport $report, TripReportStatus $status, User $admin): TripReport
    {
        $this->authorize($admin);

        return DB::transaction(function () use ($report, $status, $admin) {
            $locked = TripReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            $this->ensureUnlocked($locked->work_session_id, $locked->driver_id);

            // Void -> aktif lagi tidak boleh melewati batas 7 per supir per sesi.
            if ($locked->status === TripReportStatus::Void && $status !== TripReportStatus::Void) {
                $active = TripReport::query()
                    ->where('work_session_id', $locked->work_session_id)
                    ->where('driver_id', $locked->driver_id)
                    ->where('status', '!=', TripReportStatus::Void)
                    ->where('id', '!=', $locked->id)
                    ->count();

                if ($active >= TripReportService::MAX_TRIPS_PER_SESSION) {
                    throw new DomainException('Supir ini sudah punya 7 ritase aktif, tidak bisa mengaktifkan lagi.');
                }
            }

            $this->applyStatus($locked, $status, $admin);

            return $locked;
        });
    }

    public function approveAllPending(WorkSession $session, int $driverId, User $admin): int
    {
        $this->authorize($admin);

        return DB::transaction(function () use ($session, $driverId, $admin) {
            $reports = TripReport::query()
                ->where('work_session_id', $session->id)
                ->where('driver_id', $driverId)
                ->where('status', TripReportStatus::Menunggu)
                ->lockForUpdate()
                ->get();

            $this->ensureUnlocked($session->id, $driverId);

            foreach ($reports as $report) {
                $this->applyStatus($report, TripReportStatus::Cocok, $admin);
            }

            return $reports->count();
        });
    }

    public function correctChassis(TripReport $report, string $chassisLast4, User $admin): TripReport
    {
        $this->authorize($admin);

        if (! preg_match('/^\d{4}$/', $chassisLast4)) {
            throw new InvalidArgumentException('Nomor sasis harus 4 digit angka.');
        }

        return DB::transaction(function () use ($report, $chassisLast4, $admin) {
            $locked = TripReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            $this->ensureUnlocked($locked->work_session_id, $locked->driver_id);

            if ($locked->status === TripReportStatus::Void) {
                throw new DomainException('Laporan void tidak bisa diubah. Kembalikan ke menunggu dulu.');
            }

            if ($locked->chassis_last4 === $chassisLast4) {
                return $locked;
            }

            // Koreksi = harus dicek ulang, jadi verifikasi lama dihapus.
            $locked->update([
                'chassis_last4' => $chassisLast4,
                'status' => TripReportStatus::Menunggu,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            return $locked;
        });
    }

    public function saveNote(SessionDriver $sessionDriver, ?string $note, User $admin): void
    {
        $this->authorize($admin);

        $note = trim((string) $note);

        $sessionDriver->update(['note' => $note === '' ? null : mb_substr($note, 0, 500)]);
    }

    /** Setelah struk terbit, laporan supir itu terkunci sampai struknya dibatalkan. */
    private function ensureUnlocked(int $workSessionId, int $driverId): void
    {
        $locked = Receipt::query()->active()
            ->where('work_session_id', $workSessionId)
            ->where('driver_id', $driverId)
            ->exists();

        if ($locked) {
            throw new DomainException('Struk sudah terbit untuk supir ini. Batalkan struk dulu kalau laporan perlu diubah.');
        }
    }

    private function applyStatus(TripReport $report, TripReportStatus $status, User $admin): void
    {
        $pending = $status === TripReportStatus::Menunggu;

        $report->update([
            'status' => $status,
            'verified_by' => $pending ? null : $admin->id,
            'verified_at' => $pending ? null : now(),
        ]);
    }

    private function authorize(User $admin): void
    {
        abort_unless($admin->hasRole(RoleName::ADMIN), 403);
    }
}