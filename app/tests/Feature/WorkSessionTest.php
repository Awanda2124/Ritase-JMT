<?php

use App\Models\User;
use App\Models\WorkSession;
use App\Services\SettingService;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actingAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    return $admin;
}

it('lets an admin create a work session with a registration token', function () {
    $admin = actingAdmin();

    $this->actingAs($admin);

    Livewire::test('admin.work-sessions.form')
        ->set('date', '2026-10-12')
        ->set('route', 'Langsiran IKT - JMT2')
        ->set('open_time', '06:00')
        ->set('close_time', '18:00')
        ->set('quota', '50')
        ->call('save')
        ->assertHasNoErrors();

    $session = WorkSession::query()->first();

    expect($session)->not->toBeNull()
        ->and($session->quota)->toBe(50)
        ->and($session->token)->toHaveLength(64)
        ->and($session->created_by)->toBe($admin->id);
});

it('lets an admin update settings from the settings table', function () {
    $this->seed(SettingSeeder::class);
    $this->actingAs(actingAdmin());

    Livewire::test('admin.settings')
        ->set('rate_per_trip', '75000')
        ->set('radius_m', '80')
        ->set('jmt_lat', '-6.105176')
        ->set('jmt_lng', '106.959152')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingService::class)->ratePerTrip())->toBe(75000)
        ->and(app(SettingService::class)->radiusMeters())->toBe(80);
});
