<?php

use App\Enums\TripReportStatus;
use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\LocationNotConfiguredException;
use App\Exceptions\NotCheckedInException;
use App\Exceptions\OutsideRadiusException;
use App\Exceptions\ReportingClosedException;
use App\Exceptions\ReportLimitExceededException;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\SettingService;
use App\Services\TripReportService;
use App\Support\SettingKey;

function reportSetup(array $sessionOverrides = []): array
{
    $settings = app(SettingService::class);
    $settings->set(SettingKey::JMT_LAT, '-6.105176');
    $settings->set(SettingKey::JMT_LNG, '106.959152');
    $settings->set(SettingKey::RADIUS_M, '50');

    $session = WorkSession::factory()->create($sessionOverrides);
    $driver = Driver::factory()->create();

    SessionDriver::query()->create([
        'work_session_id' => $session->id,
        'driver_id' => $driver->id,
        'registered_at' => now(),
        'checked_in_at' => now(),
    ]);

    return [$session, $driver];
}

it('stores reports as menunggu when inside the radius', function () {
    [$session, $driver] = reportSetup();

    $created = app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234', '0042'], -6.105176, 106.959152);

    expect($created)->toBe(2)
        ->and(TripReport::query()->where('driver_id', $driver->id)->count())->toBe(2);

    $report = TripReport::query()->where('chassis_last4', '0042')->first();

    expect($report->status)->toBe(TripReportStatus::Menunggu)
        ->and((float) $report->lat)->toEqualWithDelta(-6.105176, 0.000001);
});

it('accepts a report about 30 m from the JMT point', function () {
    [$session, $driver] = reportSetup();

    $created = app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1111'], -6.104906, 106.959152);

    expect($created)->toBe(1);
});

it('rejects a report from about 100 m away and stores nothing', function () {
    [$session, $driver] = reportSetup();

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.104276, 106.959152))
        ->toThrow(OutsideRadiusException::class);

    expect(TripReport::query()->count())->toBe(0);
});

it('fails closed when the JMT coordinates are not configured', function () {
    [$session, $driver] = reportSetup();
    app(SettingService::class)->set(SettingKey::JMT_LAT, '');

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.105176, 106.959152))
        ->toThrow(LocationNotConfiguredException::class);
});

it('rejects a driver who is not on the session roster', function () {
    [$session] = reportSetup();
    $stranger = Driver::factory()->create();

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $stranger->id, ['1234'], -6.105176, 106.959152))
        ->toThrow(DriverNotInRosterException::class);
});

it('rejects a driver who has not checked in', function () {
    [$session, $driver] = reportSetup();
    SessionDriver::query()
        ->where('work_session_id', $session->id)
        ->where('driver_id', $driver->id)
        ->update(['checked_in_at' => null]);

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.105176, 106.959152))
        ->toThrow(NotCheckedInException::class);

    expect(TripReport::query()->count())->toBe(0);
});

it('accepts seven reports in one batch', function () {
    [$session, $driver] = reportSetup();

    $created = app(TripReportService::class)
        ->submit($session->id, $driver->id, ['0001', '0002', '0003', '0004', '0005', '0006', '0007'], -6.105176, 106.959152);

    expect($created)->toBe(7);
});

it('rejects more than seven reports per driver per session', function () {
    [$session, $driver] = reportSetup();
    $service = app(TripReportService::class);

    $service->submit($session->id, $driver->id, ['0001', '0002', '0003', '0004', '0005'], -6.105176, 106.959152);

    expect(fn () => $service->submit($session->id, $driver->id, ['0006', '0007', '0008'], -6.105176, 106.959152))
        ->toThrow(ReportLimitExceededException::class);

    expect(TripReport::query()->where('driver_id', $driver->id)->count())->toBe(5);
});

it('does not count void reports toward the limit', function () {
    [$session, $driver] = reportSetup();
    $service = app(TripReportService::class);

    $service->submit($session->id, $driver->id, ['0001', '0002', '0003', '0004', '0005', '0006', '0007'], -6.105176, 106.959152);
    TripReport::query()->where('chassis_last4', '0001')->update(['status' => TripReportStatus::Void->value]);

    $created = $service->submit($session->id, $driver->id, ['0008'], -6.105176, 106.959152);

    expect($created)->toBe(1);
});

it('rejects reports after the reporting window closes', function () {
    [$session, $driver] = reportSetup([
        'opens_at' => now()->subHours(8),
        'closes_at' => now()->subHour(),
    ]);

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.105176, 106.959152))
        ->toThrow(ReportingClosedException::class);
});

it('allows reports after closes_at while report_closes_at is still ahead', function () {
    [$session, $driver] = reportSetup([
        'opens_at' => now()->subHours(8),
        'closes_at' => now()->subHour(),
        'report_closes_at' => now()->addHours(2),
    ]);

    $created = app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.105176, 106.959152);

    expect($created)->toBe(1);
});

it('rejects reports before the session opens', function () {
    [$session, $driver] = reportSetup([
        'opens_at' => now()->addHour(),
        'closes_at' => now()->addHours(8),
    ]);

    expect(fn () => app(TripReportService::class)
        ->submit($session->id, $driver->id, ['1234'], -6.105176, 106.959152))
        ->toThrow(ReportingClosedException::class);
});
