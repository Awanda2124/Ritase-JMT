<?php

namespace App\Models;

use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'receipt_no',
        'work_session_id',
        'driver_id',
        'total_trips',
        'rate_per_trip',
        'amount',
        'chassis',
        'print_count',
        'active_flag',
        'printed_by',
        'printed_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_trips' => 'integer',
            'rate_per_trip' => 'integer',
            'amount' => 'integer',
            'chassis' => 'array',
            'print_count' => 'integer',
            'active_flag' => 'boolean',
            'printed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Struk yang masih berlaku (belum dibatalkan). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_flag', true);
    }

    public function isActive(): bool
    {
        return $this->active_flag === true;
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}