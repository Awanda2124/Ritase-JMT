<?php

use App\Models\Driver;
use App\Models\Receipt;
use App\Models\TripReport;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\ReceiptService;
use App\Support\RoleName;
use Spatie\Permission\Models\Role;

function printAdmin(): User
{
    Role::findOrCreate(RoleName::ADMIN, 'web');

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::ADMIN);

    return $admin;
}

/** @return array{0: User, 1: WorkSession, 2: Receipt} */
function printFixture(): array
{
    $admin = printAdmin();
    $session = WorkSession::factory()->create(['rate_per_trip' => 60000]);
    $driver = Driver::factory()->create();
    $now = now();

    foreach (['1001', '1002'] as $i => $chassis) {
        TripReport::query()->insert([
            'work_session_id' => $session->id,
            'driver_id' => $driver->id,
            'chassis_last4' => $chassis,
            'status' => 'cocok',
            'lat' => -6.1,
            'lng' => 106.9,
            'reported_at' => $now->copy()->addMinutes($i),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $receipt = app(ReceiptService::class)->issue($session, $driver->id, $admin);

    return [$admin, $session, $receipt];
}

it('shows the receipt with both copies to an admin', function () {
    [$admin, $session, $receipt] = printFixture();

    $this->actingAs($admin)
        ->get(route('receipts.print', [$session, $receipt]))
        ->assertOk()
        ->assertSee($receipt->receipt_no)
        ->assertSee('ASLI - UNTUK SUPIR')
        ->assertSee('ARSIP - ADMIN')
        ->assertSee('1001')
        ->assertSee('1002')
        ->assertSee('Rp 120.000')
        ->assertDontSee('( cetak ulang )');
});

it('defaults to 80 mm paper and accepts 58 mm and a4', function () {
    [$admin, $session, $receipt] = printFixture();
    $url = route('receipts.print', [$session, $receipt]);

    $this->actingAs($admin)->get($url)->assertSee('class="paper-80"', false);
    $this->actingAs($admin)->get($url.'?kertas=58')->assertSee('class="paper-58"', false);
    $this->actingAs($admin)->get($url.'?kertas=a4')->assertSee('class="paper-a4"', false);
    $this->actingAs($admin)->get($url.'?kertas=hack')->assertSee('class="paper-80"', false);
});

it('redirects guests away from the receipt', function () {
    [, $session, $receipt] = printFixture();

    $this->get(route('receipts.print', [$session, $receipt]))->assertRedirect();
});

it('forbids the receipt for users without the admin role', function () {
    [, $session, $receipt] = printFixture();

    $this->actingAs(User::factory()->create())
        ->get(route('receipts.print', [$session, $receipt]))
        ->assertForbidden();
});

it('does not open a receipt through another session', function () {
    [$admin, , $receipt] = printFixture();
    $other = WorkSession::factory()->create();

    $this->actingAs($admin)
        ->get(route('receipts.print', [$other, $receipt]))
        ->assertNotFound();
});

it('records a print and labels later copies as cetak ulang', function () {
    [$admin, $session, $receipt] = printFixture();

    $this->actingAs($admin)
        ->postJson(route('receipts.printed', [$session, $receipt]))
        ->assertOk()
        ->assertJson(['print_count' => 1]);

    expect($receipt->fresh()->print_count)->toBe(1);

    $this->actingAs($admin)
        ->get(route('receipts.print', [$session, $receipt]))
        ->assertOk()
        ->assertSee('( cetak ulang )');
});

it('marks a voided receipt as not valid and refuses printing it', function () {
    [$admin, $session, $receipt] = printFixture();
    app(ReceiptService::class)->void($receipt, 'Salah supir', $admin);

    $this->actingAs($admin)
        ->get(route('receipts.print', [$session, $receipt]))
        ->assertOk()
        ->assertSee('STRUK DIBATALKAN')
        ->assertSee('Salah supir');

    $this->actingAs($admin)
        ->postJson(route('receipts.printed', [$session, $receipt]))
        ->assertStatus(422);
});