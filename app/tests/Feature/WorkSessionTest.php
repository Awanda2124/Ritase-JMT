<?php

use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\SettingService;
use App\Services\WorkSessionService;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Validation\ValidationException;
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

/** Sesi dengan dua kelompok: Yayasan 30 dan Akamsi 20. */
function sessionWithGroups(User $admin): WorkSession
{
    return app(WorkSessionService::class)->create([
        'date' => '2026-10-12',
        'route' => 'Langsiran IKT - JMT2',
        'open_time' => '06:00',
        'close_time' => '18:00',
        'groups' => [
            ['id' => null, 'name' => 'Yayasan', 'quota' => 30],
            ['id' => null, 'name' => 'Akamsi', 'quota' => 20],
        ],
    ], $admin);
}

function sessionPayload(WorkSession $session, array $groups): array
{
    return [
        'date' => '2026-10-12',
        'route' => 'Langsiran IKT - JMT2',
        'open_time' => '06:00',
        'close_time' => '18:00',
        'groups' => $groups,
    ];
}

function addRoster(WorkSession $session, \App\Models\SessionGroup $group, int $count): void
{
    foreach (range(1, $count) as $i) {
        SessionDriver::query()->create([
            'work_session_id' => $session->id,
            'session_group_id' => $group->id,
            'driver_id' => Driver::factory()->create()->id,
            'registered_at' => now(),
        ]);
    }
}

it('lets an admin create a work session with groups and registration tokens', function () {
    $admin = actingAdmin();

    $this->actingAs($admin);

    Livewire::test('admin.work-sessions.form')
        ->set('date', '2026-10-12')
        ->set('route', 'Langsiran IKT - JMT2')
        ->set('open_time', '06:00')
        ->set('close_time', '18:00')
        ->set('groups', [
            ['id' => null, 'name' => 'Yayasan', 'quota' => 30],
            ['id' => null, 'name' => 'Akamsi', 'quota' => 20],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $session = WorkSession::query()->first();
    $groups = $session->groups()->get();

    expect($session)->not->toBeNull()
        ->and($session->quota)->toBe(50)
        ->and($session->created_by)->toBe($admin->id)
        ->and($groups)->toHaveCount(2)
        ->and($groups->pluck('name')->all())->toBe(['Yayasan', 'Akamsi'])
        ->and($groups->pluck('quota')->all())->toBe([30, 20])
        ->and($groups->pluck('token')->unique())->toHaveCount(2)
        ->and($groups[0]->token)->toHaveLength(40);
});

it('requires at least one group', function () {
    $this->actingAs(actingAdmin());

    Livewire::test('admin.work-sessions.form')
        ->set('date', '2026-10-12')
        ->set('groups', [])
        ->call('save')
        ->assertHasErrors(['groups']);

    expect(WorkSession::query()->count())->toBe(0);
});

it('keeps group ids and tokens when a session is updated', function () {
    $session = sessionWithGroups(actingAdmin());
    [$yayasan, $akamsi] = $session->groups()->get()->all();
    $tokens = [$yayasan->token, $akamsi->token];

    app(WorkSessionService::class)->update($session, sessionPayload($session, [
        ['id' => $yayasan->id, 'name' => 'Yayasan', 'quota' => 30],
        ['id' => $akamsi->id, 'name' => 'Akamsi', 'quota' => 25],
    ]));

    $groups = $session->groups()->get();

    expect($session->fresh()->quota)->toBe(55)
        ->and($groups->pluck('id')->all())->toBe([$yayasan->id, $akamsi->id])
        ->and($groups->pluck('token')->all())->toBe($tokens)
        ->and($groups[1]->quota)->toBe(25);
});

it('rejects lowering a group quota below the registered drivers', function () {
    $session = sessionWithGroups(actingAdmin());
    [$yayasan, $akamsi] = $session->groups()->get()->all();
    addRoster($session, $akamsi, 3);

    expect(fn () => app(WorkSessionService::class)->update($session, sessionPayload($session, [
        ['id' => $yayasan->id, 'name' => 'Yayasan', 'quota' => 30],
        ['id' => $akamsi->id, 'name' => 'Akamsi', 'quota' => 2],
    ])))->toThrow(ValidationException::class);

    expect($akamsi->fresh()->quota)->toBe(20);
});

it('removes an empty group but refuses to remove one that has drivers', function () {
    $session = sessionWithGroups(actingAdmin());
    [$yayasan, $akamsi] = $session->groups()->get()->all();
    $service = app(WorkSessionService::class);

    // Akamsi kosong: boleh dihapus.
    $service->update($session, sessionPayload($session, [
        ['id' => $yayasan->id, 'name' => 'Yayasan', 'quota' => 30],
    ]));

    expect($session->groups()->count())->toBe(1)
        ->and($session->fresh()->quota)->toBe(30);

    // Yayasan sudah punya supir: tidak boleh dihapus.
    addRoster($session, $yayasan, 1);

    expect(fn () => $service->update($session, sessionPayload($session, [
        ['id' => null, 'name' => 'Baru', 'quota' => 10],
    ])))->toThrow(ValidationException::class);

    expect($session->groups()->whereKey($yayasan->id)->exists())->toBeTrue();
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