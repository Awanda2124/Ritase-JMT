<?php

namespace App\Models;

use Database\Factories\WorkSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkSession extends Model
{
    /** @use HasFactory<WorkSessionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'route',
        'opens_at',
        'closes_at',
        'quota',
        'token',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'quota' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkSession $session): void {
            if (blank($session->token)) {
                $session->token = Str::lower(Str::random(64));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionDrivers(): HasMany
    {
        return $this->hasMany(SessionDriver::class);
    }

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'session_drivers')
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

    public function registrationUrl(): string
    {
        return route('public.register', ['token' => $this->token]);
    }

    public function remainingQuota(): int
    {
        $registered = $this->session_drivers_count ?? $this->sessionDrivers()->count();

        return max(0, $this->quota - $registered);
    }

    public function isFull(): bool
    {
        return $this->remainingQuota() === 0;
    }
}
