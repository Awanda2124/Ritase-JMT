<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalTrips = fake()->numberBetween(1, 7);

        return [
            'receipt_no' => Str::upper(Str::random(12)),
            'work_session_id' => WorkSession::factory(),
            'driver_id' => Driver::factory(),
            'total_trips' => $totalTrips,
            'amount' => 0,
            'printed_by' => User::factory(),
            'printed_at' => now(),
        ];
    }
}
