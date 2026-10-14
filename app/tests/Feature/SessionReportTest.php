<?php

use App\Enums\TripReportStatus;
use App\Models\Driver;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\SessionReportBuilder;
use App\Services\SettingService;
use App\Support\RoleName;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

function exportSetup(): array
{
    Role::findOrCreate(RoleName::ADMIN, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    $session = WorkSession::factory()->create(['date' => '2026-10-13', 'rate_per_trip' => 60000]);

    $enroll = function (string $name, string $ktp, int $order, ?string $note = null) use ($session): Driver {
        $driver = Driver::create(['name' => $name, 'ktp' => $ktp]);
        SessionDriver::create([
            'work_session_id' => $session->id,
            'driver_id' => $driver->id,
            'registered_at' => now()->addSeconds($order),
            'note' => $note,
        ]);

        return $driver;
    };

    $report = function (Driver $driver, string $chassis, TripReportStatus $status, int $minute) use ($session): void {
        TripReport::create([
            'work_session_id' => $session->id,
            'driver_id' => $driver->id,
            'chassis_last4' => $chassis,
            'status' => $status,
            'reported_at' => now()->startOfMinute()->addMinutes($minute),
        ]);
    };

    $budi = $enroll('Budi', '3201010101010001', 1, 'BAWA UNIT BERMASALAH');
    $andi = $enroll('Andi', '3201010101010002', 2);

    $report($budi, '1003', TripReportStatus::Cocok, 5);
    $report($budi, '1001', TripReportStatus::Cocok, 1);
    $report($budi, '1002', TripReportStatus::TidakCocok, 3);
    $report($andi, '2001', TripReportStatus::Menunggu, 1);
    $report($andi, '2002', TripReportStatus::Void, 2);

    return [$admin, $session];
}

it('counts only cocok reports in report order and multiplies by the rate', function () {
    [, $session] = exportSetup();

    $data = app(SessionReportBuilder::class)->build($session);

    expect($data['rows'])->toHaveCount(2)
        ->and($data['rows'][0])->toMatchArray([
            'no' => 1,
            'name' => 'Budi',
            'chassis' => ['1001', '1003'],
            'count' => 2,
            'cost' => 120000,
            'note' => 'BAWA UNIT BERMASALAH',
        ])
        ->and($data['rows'][1])->toMatchArray(['no' => 2, 'name' => 'Andi', 'chassis' => [], 'count' => 0, 'cost' => 0])
        ->and($data['total_trips'])->toBe(2)
        ->and($data['total_cost'])->toBe(120000)
        ->and($data['pending'])->toBe(1);
});

it('uses the rate stored on the session', function () {
    $session = WorkSession::factory()->create(['rate_per_trip' => 50000]);

    expect($session->ratePerTrip())->toBe(50000);
});

it('freezes the current setting rate on creation and falls back for old sessions', function () {
    $current = (int) app(SettingService::class)->ratePerTrip();

    $session = WorkSession::factory()->create();
    expect($session->rate_per_trip)->toBe($current);

    $old = WorkSession::factory()->create();
    $old->forceFill(['rate_per_trip' => null])->saveQuietly();

    expect($old->fresh()->ratePerTrip())->toBe($current);
});

it('exports an excel file in the original layout with ktp and chassis as text', function () {
    [$admin, $session] = exportSetup();

    $response = $this->actingAs($admin)->get(route('work-sessions.export', $session));
    $response->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'ritase');
    file_put_contents($path, $response->streamedContent());
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getCell('A1')->getValue())->toBe('TANGGAL: 13-14 OKTOBER 2026')
        ->and($sheet->getCell('B5')->getValue())->toBe('Budi')
        ->and($sheet->getCell('C5')->getValue())->toBe('3201010101010001')
        ->and($sheet->getCell('D5')->getValue())->toBe("\u{2713}")
        ->and($sheet->getCell('E5')->getValue())->toBe("\u{2713}")
        ->and($sheet->getCell('L5')->getValue())->toBe('BAWA UNIT BERMASALAH')
        ->and($sheet->getCell('K5')->getValue())->toEqual(120000)
        ->and($sheet->getCell('B6')->getValue())->toBe('Andi')
        ->and($sheet->getCell('K6')->getValue())->toEqual(0)
        ->and($sheet->getCell('D7')->getValue())->toBe('TOTAL RIT=')
        ->and($sheet->getCell('K7')->getValue())->toEqual(2)
        ->and($sheet->getCell('D8')->getValue())->toBe('TOTAL BIAYA DRIVER=')
        ->and($sheet->getCell('K8')->getValue())->toEqual(120000)
        ->and((string) $sheet->getCell('A10')->getValue())->toContain('1 laporan masih menunggu');

    expect(Activity::query()->where('description', 'Export laporan Excel')->exists())->toBeTrue();
});

it('redirects guests away from the export', function () {
    $session = WorkSession::factory()->create();

    $this->get(route('work-sessions.export', $session))->assertRedirect(route('login'));
});

it('forbids the export for users without the admin role', function () {
    $session = WorkSession::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('work-sessions.export', $session))
        ->assertForbidden();
});
