<?php

use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\WorkSessionService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function deadlineData(array $overrides = []): array
{
    return array_merge([
        'date' => '2026-10-12',
        'route' => 'Langsiran IKT - JMT2',
        'open_time' => '06:00',
        'close_time' => '18:00',
        'groups' => [['id' => null, 'name' => 'Umum', 'quota' => 50]],
    ], $overrides);
}

it('fills the default check-in and report deadlines when left blank', function () {
    $admin = User::factory()->create();

    $session = app(WorkSessionService::class)->create(deadlineData(), $admin);

    expect($session->checkin_closes_at->format('Y-m-d H:i'))->toBe('2026-10-12 20:00')
        ->and($session->report_closes_at->format('Y-m-d H:i'))->toBe('2026-10-13 06:00');
});

it('stores explicit deadlines and allows the report deadline after midnight', function () {
    $admin = User::factory()->create();

    $session = app(WorkSessionService::class)->create(deadlineData([
        'checkin_closes_at' => '2026-10-12T21:30',
        'report_closes_at' => '2026-10-13T05:00',
    ]), $admin);

    expect($session->checkin_closes_at->format('Y-m-d H:i'))->toBe('2026-10-12 21:30')
        ->and($session->report_closes_at->format('Y-m-d H:i'))->toBe('2026-10-13 05:00');
});

it('rejects a check-in deadline before registration opens', function () {
    $admin = User::factory()->create();

    app(WorkSessionService::class)->create(deadlineData(['checkin_closes_at' => '2026-10-12T05:00']), $admin);
})->throws(ValidationException::class);

it('rejects a report deadline before the check-in deadline', function () {
    $admin = User::factory()->create();

    app(WorkSessionService::class)->create(deadlineData([
        'checkin_closes_at' => '2026-10-12T21:00',
        'report_closes_at' => '2026-10-12T20:00',
    ]), $admin);
})->throws(ValidationException::class);

it('persists changed deadlines on update', function () {
    $admin = User::factory()->create();
    $service = app(WorkSessionService::class);
    $session = $service->create(deadlineData(), $admin);
    $groupId = $session->groups()->first()->id;

    $service->update($session, deadlineData([
        'checkin_closes_at' => '2026-10-12T22:00',
        'report_closes_at' => '2026-10-13T07:00',
        'groups' => [['id' => $groupId, 'name' => 'Umum', 'quota' => 50]],
    ]));

    $fresh = $session->fresh();

    expect($fresh->checkin_closes_at->format('Y-m-d H:i'))->toBe('2026-10-12 22:00')
        ->and($fresh->report_closes_at->format('Y-m-d H:i'))->toBe('2026-10-13 07:00')
        ->and($session->groups()->count())->toBe(1);
});

it('lets an admin check a driver in manually from the roster page', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $session = WorkSession::factory()->create();
    $group = groupFor($session);
    $driver = Driver::factory()->create();
    $sessionDriver = SessionDriver::query()->create([
        'work_session_id' => $session->id,
        'session_group_id' => $group->id,
        'driver_id' => $driver->id,
        'registered_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test('admin.work-sessions.show', ['workSession' => $session])
        ->call('checkInManually', $sessionDriver->id);

    $fresh = $sessionDriver->fresh();

    expect($fresh->checked_in_at)->not->toBeNull()
        ->and($fresh->checked_in_by)->toBe($admin->id);
});