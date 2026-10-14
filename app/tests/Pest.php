<?php

use App\Models\SessionGroup;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

/**
 * Buat kelompok registrasi untuk sebuah sesi.
 * Kalau $quota kosong, kuota kelompok = kuota sesi.
 */
function groupFor(WorkSession $session, ?int $quota = null, string $name = 'Umum'): SessionGroup
{
    return SessionGroup::query()->create([
        'work_session_id' => $session->id,
        'name' => $name,
        'quota' => $quota ?? $session->quota,
        'token' => Str::lower(Str::random(40)),
    ]);
}