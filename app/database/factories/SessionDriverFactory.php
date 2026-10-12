<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionDriver>
 */
class SessionDriverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_session_id' => WorkSession::factory(),
            'driver_id' => Driver::factory(),
            'registered_at' => now(),
            'note' => null,
        ];
    }
}
