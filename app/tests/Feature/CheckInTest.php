<?php

use App\Exceptions\CheckInClosedException;
use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\OutsideRadiusException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\CheckInService;
use App\Services\SettingService;
use App\Support\SettingKey;
use Livewire\Livewire;

function checkInSetup(array $sessionOverrides = []): array
{
    $settings = app(SettingService::class);
    $settings->set(SettingKey::JMT_LAT, '-6.105176');
    $settings->set(SettingKey::JMT_LNG, '106.959152');
    $settings->set(SettingKey::RADIUS_M, '50');

    $session = WorkSession::factory()->create($sessionOverrides);
    $group = groupFor($session);
    $driver = Driver::factory()->create();

    $sessionDriver = SessionDriver::query()->create([
        'work_session_id' => $session->id,
        'session_group_id' => $group->id,
        'driver_id' => $driver->id,
        'registered_at' => now(),
    ]);

    return [$session, $driver, $sessionDriver];
}

it('records the check-in time and location inside the radius', function () {
    [$session, $driver] = checkInSetup();

    $result = app(CheckInService::class)->checkIn($session->id, $driver->id, -6.105176, 106.959152);

    expect($result->checked_in_at)->not->toBeNull()
        ->and($result->checked_in_by)->toBeNull()
        ->and((float) $result->checked_in_lat)->toEqualWithDelta(-6.105176, 0.000001);
});

it('rejects a check-in from outside the radius and records nothing', function () {
    [$session, $driver, $sessionDriver] = checkInSetup();

    expect(fn () => app(CheckInService::class)->checkIn($session->id, $driver->id, -6.104276, 106.959152))
        ->toThrow(OutsideRadiusException::class);

    expect($sessionDriver->fresh()->checked_in_at)->toBeNull();
});

it('rejects a first check-in after the check-in deadline', function () {
    [$session, $driver, $sessionDriver] = checkInSetup(['checkin_closes_at' => now()->subMinute()]);

    expect(fn () => app(CheckInService::class)->checkIn($session->id, $driver->id, -6.105176, 106.959152))
        ->toThrow(CheckInClosedException::class);

    expect($sessionDriver->fresh()->checked_in_at)->toBeNull();
});

it('lets an already checked-in driver re-identify after the deadline without changing the time', function () {
    [$session, $driver] = checkInSetup(['checkin_closes_at' => now()->addHour()]);
    $service = app(CheckInService::class);

    $first = $service->checkIn($session->id, $driver->id, -6.105176, 106.959152);
    $session->update(['checkin_closes_at' => now()->subMinute()]);

    $this->travel(10)->minutes();
    $second = $service->checkIn($session->id, $driver->id, -6.105176, 106.959152);

    expect($second->checked_in_at->equalTo($first->checked_in_at))->toBeTrue();
});

it('rejects a driver who is not on the roster', function () {
    [$session] = checkInSetup();
    $stranger = Driver::factory()->create();

    expect(fn () => app(CheckInService::class)->checkIn($session->id, $stranger->id, -6.105176, 106.959152))
        ->toThrow(DriverNotInRosterException::class);
});

it('lets an admin check a late driver in manually after the deadline', function () {
    [, , $sessionDriver] = checkInSetup(['checkin_closes_at' => now()->subHour()]);
    $admin = User::factory()->create();

    $result = app(CheckInService::class)->checkInManually($sessionDriver, $admin);

    expect($result->checked_in_at)->not->toBeNull()
        ->and($result->checked_in_by)->toBe($admin->id);
});

it('shows the check-in page to guests', function () {
    checkInSetup();

    $this->get(route('public.checkin'))->assertOk();
});

it('shows drivers from every group of the session in one roster without a session picker', function () {
    [$session] = checkInSetup();
    $yayasan = groupFor($session, 5, 'Yayasan');
    $akamsi = groupFor($session, 5, 'Akamsi');

    foreach ([[$yayasan, 'Rudi Yayasan'], [$akamsi, 'Rudi Akamsi']] as [$group, $name]) {
        SessionDriver::query()->create([
            'work_session_id' => $session->id,
            'session_group_id' => $group->id,
            'driver_id' => Driver::factory()->create(['name' => $name])->id,
            'registered_at' => now(),
        ]);
    }

    Livewire::test('public.checkin')
        ->call('setLocation', -6.105176, 106.959152)
        ->set('search', 'rudi')
        ->assertSet('workSessionId', $session->id)
        ->assertSee('Rudi Yayasan')
        ->assertSee('Rudi Akamsi');
});

it('sends guests without a check-in to the check-in page from the report page', function () {
    checkInSetup();

    $this->get(route('public.report'))->assertRedirect(route('public.checkin'));
});

it('shows the report page with the driver name after check-in', function () {
    [$session, $driver, $sessionDriver] = checkInSetup();
    $sessionDriver->update(['checked_in_at' => now()]);

    $this->withSession(['reporter' => ['work_session_id' => $session->id, 'driver_id' => $driver->id]])
        ->get(route('public.report'))
        ->assertOk()
        ->assertSee($driver->name);
});