<?php

use App\Exceptions\SessionNotOpenException;
use App\Models\Driver;
use App\Models\SessionGroup;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;

function windowGroup(array $overrides = []): SessionGroup
{
    $session = WorkSession::factory()->create(array_merge([
        'quota' => 50,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHours(5),
    ], $overrides));

    return groupFor($session);
}

it('rejects registration before the session opens', function () {
    $group = windowGroup(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(5)]);

    app(DriverRegistrationService::class)->register($group, 'Budi', '3171010101010001');
})->throws(SessionNotOpenException::class);

it('rejects registration after the session closes', function () {
    $group = windowGroup(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    app(DriverRegistrationService::class)->register($group, 'Budi', '3171010101010002');
})->throws(SessionNotOpenException::class);

it('does not create a driver when the session is closed', function () {
    $group = windowGroup(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    try {
        app(DriverRegistrationService::class)->register($group, 'Budi', '3171010101010003');
    } catch (SessionNotOpenException) {
    }

    expect(Driver::query()->where('ktp', '3171010101010003')->exists())->toBeFalse();
});

it('shows a closed message on the public page', function () {
    $group = windowGroup(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    $this->get(route('public.register', $group->token))->assertSee('Pendaftaran ditutup');
});

it('shows a not-yet-open message on the public page', function () {
    $group = windowGroup(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(5)]);

    $this->get(route('public.register', $group->token))->assertSee('Belum dibuka');
});

it('accepts registration while the session is open', function () {
    $group = windowGroup();

    $registered = app(DriverRegistrationService::class)->register($group, 'Budi', '3171010101010004');

    expect($registered->work_session_id)->toBe($group->work_session_id)
        ->and($registered->session_group_id)->toBe($group->id);
});