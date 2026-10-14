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
    $group = groupFor($session);
    $service = app(DriverRegistrationService::class);

    $service->register($group, 'Supir Satu', '3172010101010001');

    expect(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1);

    expect(fn () => $service->register($group, 'Supir Dua', '3172010101010002'))
        ->toThrow(QuotaFullException::class);

    expect(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1)
        ->and(Driver::query()->count())->toBe(1);
});

it('stores the group on the roster entry', function () {
    $session = WorkSession::factory()->create(['quota' => 5]);
    $group = groupFor($session, 5, 'Yayasan');

    $roster = app(DriverRegistrationService::class)->register($group, 'Budi', '3172010101010001');

    expect($roster->work_session_id)->toBe($session->id)
        ->and($roster->session_group_id)->toBe($group->id);
});

it('keeps quotas separate per group', function () {
    $session = WorkSession::factory()->create(['quota' => 3]);
    $yayasan = groupFor($session, 1, 'Yayasan');
    $akamsi = groupFor($session, 2, 'Akamsi');
    $service = app(DriverRegistrationService::class);

    $service->register($yayasan, 'Y1', '3171000000000001');

    expect(fn () => $service->register($yayasan, 'Y2', '3171000000000002'))
        ->toThrow(QuotaFullException::class);

    // Yayasan penuh, Akamsi tidak terpengaruh.
    $service->register($akamsi, 'A1', '3171000000000003');
    $service->register($akamsi, 'A2', '3171000000000004');

    expect(SessionDriver::query()->where('session_group_id', $akamsi->id)->count())->toBe(2)
        ->and(SessionDriver::query()->where('session_group_id', $yayasan->id)->count())->toBe(1)
        ->and(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(3);
});

it('rejects the same ktp in two groups of the same session', function () {
    $session = WorkSession::factory()->create(['quota' => 10]);
    $yayasan = groupFor($session, 5, 'Yayasan');
    $akamsi = groupFor($session, 5, 'Akamsi');
    $service = app(DriverRegistrationService::class);

    $service->register($yayasan, 'Budi', '3171000000000009');

    expect(fn () => $service->register($akamsi, 'Budi', '3171000000000009'))
        ->toThrow(AlreadyRegisteredException::class);

    expect(SessionDriver::query()->where('work_session_id', $session->id)->count())->toBe(1);
});

it('reuses an existing driver by unique ktp instead of creating a duplicate', function () {
    $existing = Driver::factory()->create([
        'name' => 'Nama Lama',
        'ktp' => '3172010101010010',
    ]);
    $session = WorkSession::factory()->create(['quota' => 5]);
    $group = groupFor($session);

    $roster = app(DriverRegistrationService::class)
        ->register($group, 'Nama Baru', '3172010101010010');

    expect(Driver::query()->where('ktp', '3172010101010010')->count())->toBe(1)
        ->and($roster->driver_id)->toBe($existing->id);
});

it('rejects the same ktp twice on the same session', function () {
    $session = WorkSession::factory()->create(['quota' => 5]);
    $group = groupFor($session);
    $service = app(DriverRegistrationService::class);

    $service->register($group, 'Supir', '3172010101010010');

    $service->register($group, 'Supir', '3172010101010010');
})->throws(AlreadyRegisteredException::class);

it('shows the public registration form for a valid group token', function () {
    $session = WorkSession::factory()->create(['quota' => 10]);
    $group = groupFor($session, 10, 'Yayasan');

    $this->get(route('public.register', $group->token))
        ->assertOk()
        ->assertSee('Registrasi supir')
        ->assertSee('Kelompok Yayasan');
});

it('returns not found for an unknown registration token', function () {
    $this->get(route('public.register', str_repeat('b', 64)))
        ->assertNotFound();
});

it('registers a driver through the public form', function () {
    $session = WorkSession::factory()->create(['quota' => 10]);
    $group = groupFor($session);

    Livewire::test('public.register', ['token' => $group->token])
        ->set('name', 'Budi Santoso')
        ->set('ktp', '3172010101010010')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Pendaftaran berhasil');

    $roster = SessionDriver::query()->where('work_session_id', $session->id)->first();

    expect(Driver::query()->where('ktp', '3172010101010010')->exists())->toBeTrue()
        ->and($roster)->not->toBeNull()
        ->and($roster->session_group_id)->toBe($group->id);
});

it('shows penuh when the group quota is already used', function () {
    $session = WorkSession::factory()->create(['quota' => 1]);
    $group = groupFor($session);
    app(DriverRegistrationService::class)->register($group, 'Penuh', '3172010101010001');
    RateLimiter::clear('register-driver:ip:127.0.0.1');

    Livewire::test('public.register', ['token' => $group->token])
        ->assertSee('Kuota penuh');
});

it('shows penuh only for the full group and not for the other one', function () {
    $session = WorkSession::factory()->create(['quota' => 3]);
    $yayasan = groupFor($session, 1, 'Yayasan');
    $akamsi = groupFor($session, 2, 'Akamsi');
    app(DriverRegistrationService::class)->register($yayasan, 'Y1', '3172010101010001');
    RateLimiter::clear('register-driver:ip:127.0.0.1');

    Livewire::test('public.register', ['token' => $yayasan->token])
        ->assertSee('Kuota penuh');

    Livewire::test('public.register', ['token' => $akamsi->token])
        ->assertDontSee('Kuota penuh')
        ->assertSee('Kelompok Akamsi');
});

it('lets an admin remove a driver from the roster and writes an audit log', function () {
    $admin = adminUser();
    $session = WorkSession::factory()->create(['created_by' => $admin->id, 'quota' => 5]);
    $group = groupFor($session);
    $roster = app(DriverRegistrationService::class)
        ->register($group, 'Budi', '3172010101010010');

    RateLimiter::clear('register-driver:ip:127.0.0.1');

    $this->actingAs($admin);

    Livewire::test('admin.work-sessions.show', ['workSession' => $session])
        ->call('remove', $roster->id)
        ->assertHasNoErrors();

    expect(SessionDriver::query()->count())->toBe(0)
        ->and(Activity::query()->where('description', 'Menghapus supir dari roster')->exists())->toBeTrue();
});