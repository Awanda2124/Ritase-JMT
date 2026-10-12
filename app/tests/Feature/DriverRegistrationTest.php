<?php

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Cache::flush();
});

function adminUser(): User
{
    test()->seed(RoleSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    return $admin;
}

it('registers a driver until quota is full and then rejects extras', function () {
    $session = WorkSession::factory()->create(['quota' => 1]);
    $service = app(DriverRegistrationService::class);

    $service->register($session, 'Supir Satu', '3172010101010001');

    expect(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1);

    expect(fn () => $service->register($session, 'Supir Dua', '3172010101010002'))
        ->toThrow(QuotaFullException::class);

    expect(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1)
        ->and(Driver::query()->count())->toBe(1);
});

it('reuses an existing driver by unique ktp instead of creating a duplicate', function () {
    $existing = Driver::factory()->create([
        'name' => 'Nama Lama',
        'ktp' => '3172010101010010',
    ]);
    $session = WorkSession::factory()->create(['quota' => 5]);

    $roster = app(DriverRegistrationService::class)
        ->register($session, 'Nama Baru', '3172010101010010');

    expect(Driver::query()->where('ktp', '3172010101010010')->count())->toBe(1)
        ->and($roster->driver_id)->toBe($existing->id);
});

it('rejects the same ktp twice on the same session', function () {
    $session = WorkSession::factory()->create(['quota' => 5]);
    $service = app(DriverRegistrationService::class);

    $service->register($session, 'Supir', '3172010101010010');

    $service->register($session, 'Supir', '3172010101010010');
})->throws(AlreadyRegisteredException::class);

it('shows the public registration form for a valid token', function () {
    $session = WorkSession::factory()->create(['quota' => 10]);

    $this->get(route('public.register', $session->token))
        ->assertOk()
        ->assertSee('Registrasi supir');
});

it('returns not found for an unknown registration token', function () {
    $this->get(route('public.register', str_repeat('b', 64)))
        ->assertNotFound();
});

it('registers a driver through the public form', function () {
    $session = WorkSession::factory()->create(['quota' => 10]);

    Livewire::test('public.register', ['token' => $session->token])
        ->set('name', 'Budi Santoso')
        ->set('ktp', '3172010101010010')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Pendaftaran berhasil');

    expect(Driver::query()->where('ktp', '3172010101010010')->exists())->toBeTrue()
        ->and(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1);
});

it('shows penuh when the session quota is already used', function () {
    $session = WorkSession::factory()->create(['quota' => 1]);
    app(DriverRegistrationService::class)->register($session, 'Penuh', '3172010101010001');
    RateLimiter::clear('register-driver:127.0.0.1');

    Livewire::test('public.register', ['token' => $session->token])
        ->assertSee('Penuh');
});

it('lets an admin remove a driver from the roster and writes an audit log', function () {
    $admin = adminUser();
    $session = WorkSession::factory()->create(['created_by' => $admin->id, 'quota' => 5]);
    $roster = app(DriverRegistrationService::class)
        ->register($session, 'Budi', '3172010101010010');

    RateLimiter::clear('register-driver:127.0.0.1');

    $this->actingAs($admin);

    Livewire::test('admin.work-sessions.show', ['workSession' => $session])
        ->call('remove', $roster->id)
        ->assertHasNoErrors();

    expect(SessionDriver::query()->count())->toBe(0)
        ->and(Activity::query()->where('description', 'Menghapus supir dari roster')->exists())->toBeTrue();
});
