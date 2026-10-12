<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            SettingKey::RATE_PER_TRIP => '60000',
            SettingKey::RADIUS_M => '50',
            SettingKey::JMT_LAT => '-6.105176',
            SettingKey::JMT_LNG => '106.959152',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
