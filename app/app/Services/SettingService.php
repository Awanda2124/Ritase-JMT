<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public const CACHE_KEY = 'settings.all';

    /** @var array<string, ?string>|null memo per-request */
    private ?array $loaded = null;

    /** @return array<string, ?string> */
    private function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
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
