<?php

use App\Enums\TripReportStatus;
use App\Models\Driver;
use App\Models\Receipt;
use App\Models\TripReport;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\ReceiptService;
use App\Services\TripVerificationService;
use App\Support\RoleName;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

function receiptAdmin(): User
{
    Role::findOrCreate(RoleName::ADMIN, 'web');

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    return $admin;
}

/**
 * Satu sesi + satu supir dengan laporan berstatus sesuai urutan (sasis 1001, 1002, ...).
 *
 * @param  array<int, string>  $statuses
 * @return array{0: WorkSession, 1: Driver}
 */
function receiptFixture(array $statuses): array
{
    $session = WorkSession::factory()->create(['rate_per_trip' => 60000]);
    $driver = Driver::factory()->create();
    $now = now();

    foreach ($statuses as $i => $status) {
        TripReport::query()->insert([
            'work_session_id' => $session->id,
            'driver_id' => $driver->id,
            'chassis_last4' => (string) (1001 + $i),
            'status' => $status,
            'lat' => -6.1,
            'lng' => 106.9,
            'reported_at' => $now->copy()->addMinutes($i),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    return [$session, $driver];
}

it('issues a receipt with a snapshot of cocok reports only', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok', 'cocok', 'void', 'tidak_cocok', 'cocok']);

    $receipt = app(ReceiptService::class)->issue($session, $driver->id, $admin);

    expect($receipt->total_trips)->toBe(3)
        ->and($receipt->rate_per_trip)->toBe(60000)
        ->and($receipt->amount)->toBe(180000)
        ->and($receipt->chassis)->toBe(['1001', '1002', '1005'])
        ->and($receipt->receipt_no)->toStartWith('RS-')
        ->and($receipt->printed_by)->toEqual($admin->id)
        ->and($receipt->active_flag)->toBeTrue()
        ->and($receipt->print_count)->toBe(0);
});

it('refuses a receipt while a report is still menunggu', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok', 'menunggu']);

    expect(fn () => app(ReceiptService::class)->issue($session, $driver->id, $admin))
        ->toThrow(DomainException::class, 'belum dicek');

    expect(Receipt::query()->count())->toBe(0);
});

it('refuses a receipt when there is no cocok report', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['tidak_cocok', 'void']);

    expect(fn () => app(ReceiptService::class)->issue($session, $driver->id, $admin))
        ->toThrow(DomainException::class, 'Tidak ada ritase');
});

it('returns the same active receipt when issued twice', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok', 'cocok']);

    $first = app(ReceiptService::class)->issue($session, $driver->id, $admin);
    $second = app(ReceiptService::class)->issue($session, $driver->id, $admin);

    expect($second->id)->toEqual($first->id)
        ->and(Receipt::query()->count())->toBe(1);
});

it('locks the reports of a driver once a receipt is issued', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok', 'cocok']);
    app(ReceiptService::class)->issue($session, $driver->id, $admin);

    $report = TripReport::query()->where('driver_id', $driver->id)->first();
    $verification = app(TripVerificationService::class);

    expect(fn () => $verification->setStatus($report, TripReportStatus::TidakCocok, $admin))
        ->toThrow(DomainException::class, 'Struk sudah terbit');

    expect(fn () => $verification->correctChassis($report, '9999', $admin))
        ->toThrow(DomainException::class, 'Struk sudah terbit');

    expect(fn () => $verification->approveAllPending($session, $driver->id, $admin))
        ->toThrow(DomainException::class, 'Struk sudah terbit');

    expect($report->fresh()->status)->toBe(TripReportStatus::Cocok)
        ->and($report->fresh()->chassis_last4)->toBe('1001');
});

it('unlocks the reports and allows a replacement receipt after the receipt is voided', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok', 'cocok']);
    $service = app(ReceiptService::class);

    $first = $service->issue($session, $driver->id, $admin);
    $voided = $service->void($first, 'Salah ketik sasis', $admin);

    expect($voided->active_flag)->toBeNull()
        ->and($voided->voided_by)->toEqual($admin->id)
        ->and($voided->void_reason)->toBe('Salah ketik sasis');

    $report = TripReport::query()->where('driver_id', $driver->id)->first();
    app(TripVerificationService::class)->setStatus($report, TripReportStatus::TidakCocok, $admin);

    $second = $service->issue($session, $driver->id, $admin);

    expect($second->id)->not->toEqual($first->id)
        ->and($second->receipt_no)->not->toBe($first->receipt_no)
        ->and($second->total_trips)->toBe(1)
        ->and(Receipt::query()->count())->toBe(2)
        ->and(Receipt::query()->active()->count())->toBe(1);
});

it('requires a reason of at least five characters to void a receipt', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok']);
    $service = app(ReceiptService::class);
    $receipt = $service->issue($session, $driver->id, $admin);

    expect(fn () => $service->void($receipt, 'abc', $admin))
        ->toThrow(DomainException::class, 'minimal 5');

    expect($receipt->fresh()->isActive())->toBeTrue();
});

it('does not void the same receipt twice', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok']);
    $service = app(ReceiptService::class);
    $receipt = $service->issue($session, $driver->id, $admin);
    $service->void($receipt, 'Salah supir', $admin);

    expect(fn () => $service->void($receipt, 'Salah supir lagi', $admin))
        ->toThrow(DomainException::class, 'sudah dibatalkan');
});

it('counts prints and refuses to print a voided receipt', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok']);
    $service = app(ReceiptService::class);
    $receipt = $service->issue($session, $driver->id, $admin);

    $service->recordPrint($receipt, $admin);
    $service->recordPrint($receipt, $admin);

    expect($receipt->fresh()->print_count)->toBe(2);

    $service->void($receipt, 'Salah supir', $admin);

    expect(fn () => $service->recordPrint($receipt, $admin))
        ->toThrow(DomainException::class, 'dibatalkan');
});

it('forbids receipt actions for users without the admin role', function () {
    $admin = receiptAdmin();
    [$session, $driver] = receiptFixture(['cocok']);
    $receipt = app(ReceiptService::class)->issue($session, $driver->id, $admin);
    $outsider = User::factory()->create();

    expect(fn () => app(ReceiptService::class)->issue($session, $driver->id, $outsider))
        ->toThrow(HttpException::class);

    expect(fn () => app(ReceiptService::class)->void($receipt, 'Alasan panjang', $outsider))
        ->toThrow(HttpException::class);

    expect(fn () => app(ReceiptService::class)->recordPrint($receipt, $outsider))
        ->toThrow(HttpException::class);
});