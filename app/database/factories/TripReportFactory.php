<?php

namespace Database\Factories;

use App\Enums\TripReportStatus;
use App\Models\Driver;
use App\Models\TripReport;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripReport>
 */
class TripReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_session_id' => WorkSession::factory(),
            'driver_id' => Driver::factory(),
            'chassis_last4' => fake()->numerify('####'),
            'status' => TripReportStatus::Menunggu,
            'lat' => null,
            'lng' => null,
            'reported_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
            'note' => null,
        ];
    }
}
