<?php

use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function createAdmin(array $attributes = []): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create($attributes);
    $user->assignRole(RoleName::ADMIN);

    return $user;
}
