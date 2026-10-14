<?php

namespace App\Models;

use App\Enums\TripReportStatus;
use Database\Factories\TripReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TripReport extends Model
{
    /** @use HasFactory<TripReportFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'work_session_id',
        'driver_id',
        'chassis_last4',
        'status',
        'lat',
        'lng',
        'reported_at',
        'verified_by',
        'verified_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TripReportStatus::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Pembuatan laporan tidak di-log (tabel ini sudah jadi catatannya);
     * yang dicatat hanya perubahan oleh admin.
     *
     * @var list<string>
     */
    protected static $recordEvents = ['updated', 'deleted'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
