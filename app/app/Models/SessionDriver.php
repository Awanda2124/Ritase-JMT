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
        'session_group_id',
        'driver_id',
        'registered_at',
        'checked_in_at',
        'checked_in_lat',
        'checked_in_lng',
        'checked_in_by',
        'note',
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

    /**
     * Tanpa log otomatis: check-in manual dan hapus roster sudah dicatat
     * manual di service, dan kolom checked_in_* sudah jadi catatan check-in sendiri.
     *
     * @var list<string>
     */
    protected static $recordEvents = [];

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(SessionGroup::class, 'session_group_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}