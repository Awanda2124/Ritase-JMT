<?php

use App\Enums\TripReportStatus;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\TripVerificationService;
use App\Support\RoleName;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

function verifySetup(int $count = 2): array
{
    Role::findOrCreate(RoleName::ADMIN, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    $session = WorkSession::factory()->create();
    $driver = Driver::create(['name' => 'Budi', 'ktp' => '3201010101010001']);

    SessionDriver::create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
        'registered_at' => now(),
        'checked_in_at' => now(),
    ]);

    $reports = collect(range(1, $count))->map(fn (int $i) => TripReport::create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
        'chassis_last4' => sprintf('%04d', 1000 + $i),
        'status' => TripReportStatus::Menunggu,
        'reported_at' => now(),
    ]));

    return [$admin, $session, $driver, $reports];
}

it('lets an admin mark a report as cocok and records who verified it', function () {
    [$admin, , , $reports] = verifySetup();

    app(TripVerificationService::class)->setStatus($reports[0], TripReportStatus::Cocok, $admin);

    $fresh = $reports[0]->fresh();
    expect($fresh->status)->toBe(TripReportStatus::Cocok)
        ->and($fresh->verified_by)->toBe($admin->id)
        ->and($fresh->verified_at)->not->toBeNull();
});

it('forbids verification by a user without the admin role', function () {
    [, , , $reports] = verifySetup();
    $user = User::factory()->create();

    expect(fn () => app(TripVerificationService::class)->setStatus($reports[0], TripReportStatus::Cocok, $user))
        ->toThrow(HttpException::class);

    expect($reports[0]->fresh()->status)->toBe(TripReportStatus::Menunggu);
});

it('approves only pending reports when approving all', function () {
    [$admin, $session, $driver, $reports] = verifySetup(3);
    $service = app(TripVerificationService::class);

    $service->setStatus($reports[0], TripReportStatus::Void, $admin);

    $count = $service->approveAllPending($session, $driver->id, $admin);

    expect($count)->toBe(2)
        ->and($reports[0]->fresh()->status)->toBe(TripReportStatus::Void)
        ->and($reports[1]->fresh()->status)->toBe(TripReportStatus::Cocok)
        ->and($reports[2]->fresh()->status)->toBe(TripReportStatus::Cocok);
});

it('resets a report to menunggu when the chassis number is corrected', function () {
    [$admin, , , $reports] = verifySetup();
    $service = app(TripVerificationService::class);

    $service->setStatus($reports[0], TripReportStatus::Cocok, $admin);
    $service->correctChassis($reports[0], '9999', $admin);

    $fresh = $reports[0]->fresh();
    expect($fresh->chassis_last4)->toBe('9999')
        ->and($fresh->status)->toBe(TripReportStatus::Menunggu)
        ->and($fresh->verified_by)->toBeNull()
        ->and($fresh->verified_at)->toBeNull();
});

it('rejects a chassis correction that is not four digits', function () {
    [$admin, , , $reports] = verifySetup();

    expect(fn () => app(TripVerificationService::class)->correctChassis($reports[0], '12a4', $admin))
        ->toThrow(InvalidArgumentException::class);
});

it('does not let a void report come back when the driver already has seven active trips', function () {
    [$admin, $session, $driver, $reports] = verifySetup(7);

    $void = TripReport::create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
        'chassis_last4' => '5555',
        'status' => TripReportStatus::Void,
        'reported_at' => now(),
    ]);

    expect(fn () => app(TripVerificationService::class)->setStatus($void, TripReportStatus::Menunggu, $admin))
        ->toThrow(DomainException::class);

    expect($void->fresh()->status)->toBe(TripReportStatus::Void);
});

it('writes an audit log when the status changes', function () {
    [$admin, , , $reports] = verifySetup();

    app(TripVerificationService::class)->setStatus($reports[0], TripReportStatus::TidakCocok, $admin);

    expect($reports[0]->activities()->where('event', 'updated')->exists())->toBeTrue();
});

it('shows the verification page and verifies a report from it', function () {
    [$admin, $session, , $reports] = verifySetup();

    Livewire::actingAs($admin)
        ->test('admin.work-sessions.verify', ['workSession' => $session])
        ->assertSee('1001')
        ->assertSee('Budi')
        ->call('setStatus', $reports[0]->id, 'cocok');

    expect($reports[0]->fresh()->status)->toBe(TripReportStatus::Cocok);
});

it('redirects guests away from the verification page', function () {
    $session = WorkSession::factory()->create();

    $this->get(route('work-sessions.verify', $session))->assertRedirect(route('login'));
});