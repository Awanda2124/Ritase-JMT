<?php

use App\Exceptions\SessionNotOpenException;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;

function windowSession(array $overrides = []): WorkSession
{
    return WorkSession::factory()->create(array_merge([
        'quota' => 50,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHours(5),
    ], $overrides));
}

it('rejects registration before the session opens', function () {
    $session = windowSession(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(5)]);

    app(DriverRegistrationService::class)->register($session, 'Budi', '3171010101010001');
})->throws(SessionNotOpenException::class);

it('rejects registration after the session closes', function () {
    $session = windowSession(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    app(DriverRegistrationService::class)->register($session, 'Budi', '3171010101010002');
})->throws(SessionNotOpenException::class);

it('does not create a driver when the session is closed', function () {
    $session = windowSession(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    try {
        app(DriverRegistrationService::class)->register($session, 'Budi', '3171010101010003');
    } catch (SessionNotOpenException) {
    }

    expect(\App\Models\Driver::query()->where('ktp', '3171010101010003')->exists())->toBeFalse();
});

it('shows a closed message on the public page', function () {
    $session = windowSession(['opens_at' => now()->subHours(5), 'closes_at' => now()->subHour()]);

    $this->get(route('public.register', $session->token))->assertSee('Pendaftaran ditutup');
});

it('shows a not-yet-open message on the public page', function () {
    $session = windowSession(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(5)]);

    $this->get(route('public.register', $session->token))->assertSee('Belum dibuka');
});

it('accepts registration while the session is open', function () {
    $session = windowSession();

    $registered = app(DriverRegistrationService::class)->register($session, 'Budi', '3171010101010004');

    expect($registered->work_session_id)->toBe($session->id);
});