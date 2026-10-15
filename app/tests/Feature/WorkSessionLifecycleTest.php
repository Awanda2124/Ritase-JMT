<?php

use App\Models\WorkSession;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

function lifecycleSession(): WorkSession
{
    return WorkSession::factory()->make([
        'opens_at' => '2026-10-14 12:00:00',
        'closes_at' => '2026-10-14 18:00:00',
        'checkin_closes_at' => '2026-10-14 15:00:00',
        'report_closes_at' => '2026-10-15 07:00:00',
    ]);
}

it('is not live before the session opens', function () {
    Carbon::setTestNow('2026-10-14 11:00:00');

    $session = lifecycleSession();

    expect($session->isLive())->toBeFalse()
        ->and($session->isFinished())->toBeFalse();
});

it('stays live until the report deadline even after registration closes', function () {
    $session = lifecycleSession();

    Carbon::setTestNow('2026-10-14 20:00:00');
    expect($session->isLive())->toBeTrue();

    Carbon::setTestNow('2026-10-15 06:59:00');
    expect($session->isLive())->toBeTrue()
        ->and($session->isFinished())->toBeFalse();
});

it('is finished after the report deadline', function () {
    Carbon::setTestNow('2026-10-15 17:06:00');

    $session = lifecycleSession();

    expect($session->isLive())->toBeFalse()
        ->and($session->isFinished())->toBeTrue();
});
