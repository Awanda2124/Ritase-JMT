<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SettingKey;

class SettingService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public function ratePerTrip(): int
    {
        return (int) $this->get(SettingKey::RATE_PER_TRIP, '0');
    }

    public function radiusMeters(): int
    {
        return (int) $this->get(SettingKey::RADIUS_M, '0');
    }

    public function jmtLatitude(): ?float
    {
        $value = $this->get(SettingKey::JMT_LAT);

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function jmtLongitude(): ?float
    {
        $value = $this->get(SettingKey::JMT_LNG);

        return $value === null || $value === '' ? null : (float) $value;
    }
}
