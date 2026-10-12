<?php

use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\SettingService;
use App\Support\RoleName;
use App\Support\SettingKey;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;

it('seeds only the admin role', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::query()->where('name', RoleName::ADMIN)->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'koordinator')->exists())->toBeFalse();
});

it('does not create an admin user when credentials are empty', function () {
    config([
        'auth.admin_email' => '',
        'auth.admin_password' => '',
    ]);

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(0);
});

it('creates an admin user from configuration', function () {
    $this->seed(RoleSeeder::class);

    config([
        'auth.admin_email' => 'from-env@example.com',
        'auth.admin_password' => 'secret123',
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'from-env@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasRole(RoleName::ADMIN))->toBeTrue();
});

it('seeds default settings from the settings table', function () {
    $this->seed(SettingSeeder::class);

    $settings = app(SettingService::class);

    expect($settings->ratePerTrip())->toBe(60000)
        ->and($settings->radiusMeters())->toBe(50)
        ->and($settings->get(SettingKey::JMT_LAT))->toBe('-6.105176')
        ->and($settings->get(SettingKey::JMT_LNG))->toBe('106.959152');
});

it('enforces unique ktp on drivers', function () {
    Driver::factory()->create(['ktp' => '3172010101010010']);

    Driver::factory()->create(['ktp' => '3172010101010010']);
})->throws(QueryException::class);

it('enforces unique driver per work session', function () {
    $session = WorkSession::factory()->create();
    $driver = Driver::factory()->create();

    SessionDriver::factory()->create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
    ]);

    SessionDriver::factory()->create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
    ]);
})->throws(QueryException::class);

it('enforces unique work session tokens', function () {
    $token = str_repeat('a', 64);

    WorkSession::factory()->create(['token' => $token]);
    WorkSession::factory()->create(['token' => $token]);
})->throws(QueryException::class);
