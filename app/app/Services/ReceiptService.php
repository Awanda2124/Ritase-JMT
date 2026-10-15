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

class ReceiptService
{
    private const MIN_VOID_REASON = 5;

    /**
     * Terbitkan struk untuk satu supir. Idempotent: kalau struk aktif sudah ada, dikembalikan apa adanya.
     * Isi struk adalah snapshot, jadi angkanya tidak berubah walau data di belakangnya dikoreksi nanti.
     */
    public function issue(WorkSession $session, int $driverId, User $admin): Receipt
    {
        $this->authorize($admin);

        return DB::transaction(function () use ($session, $driverId, $admin): Receipt {
            // Penerbitan diserialkan per sesi: nomor urut aman dan tidak ada struk ganda.
            $locked = WorkSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Serialkan juga dengan pengiriman laporan supir ini (TripReportService mengunci baris roster yang sama).
            SessionDriver::query()
                ->where('work_session_id', $locked->id)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            $existing = Receipt::query()->active()
                ->where('work_session_id', $locked->id)
                ->where('driver_id', $driverId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $reports = TripReport::query()
                ->where('work_session_id', $locked->id)
                ->where('driver_id', $driverId)
                ->orderBy('reported_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($reports->contains('status', TripReportStatus::Menunggu)) {
                throw new DomainException('Masih ada laporan yang belum dicek. Selesaikan pengecekan dulu.');
            }

            $cocok = $reports->where('status', TripReportStatus::Cocok)->values();

            if ($cocok->isEmpty()) {
                throw new DomainException('Tidak ada ritase yang cocok, struk tidak bisa diterbitkan.');
            }

            $rate = $locked->ratePerTrip();
            $sequence = Receipt::query()->where('work_session_id', $locked->id)->count() + 1;

            return Receipt::query()->create([
                'receipt_no' => sprintf('RS-%s-S%d-%03d', $locked->date->format('ymd'), $locked->id, $sequence),
                'work_session_id' => $locked->id,
                'driver_id' => $driverId,
                'total_trips' => $cocok->count(),
                'rate_per_trip' => $rate,
                'amount' => $cocok->count() * $rate,
                'chassis' => $cocok->pluck('chassis_last4')->all(),
                'print_count' => 0,
                'active_flag' => true,
                'printed_by' => $admin->id,
                'printed_at' => now(),
            ]);
        });
    }

    /** Catat satu kali cetak. Cetak kedua dan seterusnya ditandai "CETAK ULANG" di struk. */
    public function recordPrint(Receipt $receipt, User $admin): Receipt
    {
        $this->authorize($admin);

        return DB::transaction(function () use ($receipt, $admin): Receipt {
            $locked = Receipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new DomainException('Struk ini sudah dibatalkan dan tidak bisa dicetak.');
            }

            // Quietly: log kita tulis manual di bawah supaya tidak dobel.
            $locked->incrementQuietly('print_count');

            activity()
                ->performedOn($locked)
                ->causedBy($admin)
                ->withProperties(['print_count' => $locked->print_count])
                ->log('Struk dicetak');

            return $locked;
        });
    }

    /** Batalkan struk supaya laporan supir bisa dikoreksi lagi. Wajib beralasan, tercatat di audit log. */
    public function void(Receipt $receipt, string $reason, User $admin): Receipt
    {
        $this->authorize($admin);

        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_VOID_REASON) {
            throw new DomainException('Alasan pembatalan minimal '.self::MIN_VOID_REASON.' karakter.');
        }

        return DB::transaction(function () use ($receipt, $reason, $admin): Receipt {
            $locked = Receipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw new DomainException('Struk ini sudah dibatalkan.');
            }

            $locked->update([
                'active_flag' => null,
                'voided_by' => $admin->id,
                'voided_at' => now(),
                'void_reason' => mb_substr($reason, 0, 255),
            ]);

            return $locked;
        });
    }

    private function authorize(User $admin): void
    {
        abort_unless($admin->hasRole(RoleName::ADMIN), 403);
    }
}