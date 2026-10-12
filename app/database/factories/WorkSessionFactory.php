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
        $date = fake()->dateTimeBetween('now', '+1 week');
        $opensAt = (clone $date)->setTime(6, 0);
        $closesAt = (clone $date)->setTime(18, 0);

        return [
            'date' => $date->format('Y-m-d'),
            'route' => 'Langsiran IKT - JMT2',
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'quota' => 50,
            'token' => Str::lower(Str::random(64)),
            'created_by' => User::factory(),
        ];
    }
}
