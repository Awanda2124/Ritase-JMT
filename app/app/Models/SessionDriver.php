<?php

namespace App\Models;

use Database\Factories\SessionDriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SessionDriver extends Model
{
    /** @use HasFactory<SessionDriverFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'work_session_id',
        'driver_id',
        'registered_at',
        'note',
        'checked_in_at',
        'checked_in_lat',
        'checked_in_lng',
        'checked_in_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_in_lat' => 'decimal:7',
            'checked_in_lng' => 'decimal:7',
        ];
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

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
}
