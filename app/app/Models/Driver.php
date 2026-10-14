<?php

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'ktp',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // KTP sengaja tidak dicatat: nomor lengkap tidak boleh masuk activity_log.
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function maskedKtp(): string
    {
        $ktp = (string) $this->ktp;

        if (strlen($ktp) !== 16) {
            return $ktp;
        }

        return substr($ktp, 0, 4).str_repeat('•', 8).substr($ktp, -4);
    }

    public function sessionDrivers(): HasMany
    {
        return $this->hasMany(SessionDriver::class);
    }

    public function workSessions(): BelongsToMany
    {
        return $this->belongsToMany(WorkSession::class, 'session_drivers')
            ->withPivot(['registered_at', 'note'])
            ->withTimestamps();
    }

    public function tripReports(): HasMany
    {
        return $this->hasMany(TripReport::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
