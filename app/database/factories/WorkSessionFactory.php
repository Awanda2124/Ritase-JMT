<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkSession>
 */
class WorkSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'route' => 'Langsiran IKT - JMT2',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHours(8),
            'quota' => 50,
            'token' => Str::lower(Str::random(64)),
            'created_by' => User::factory(),
        ];
    }
}