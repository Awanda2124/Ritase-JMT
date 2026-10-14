<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionGroup extends Model
{
    protected $fillable = [
        'work_session_id',
        'name',
        'quota',
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota' => 'integer',
        ];
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function sessionDrivers(): HasMany
    {
        return $this->hasMany(SessionDriver::class);
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

    public function registrationUrl(): string
    {
        return route('public.register', ['token' => $this->token]);
    }
}