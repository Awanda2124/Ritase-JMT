<?php

use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Cache::flush();
});

it('redirects guests from the home page to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('shows the login page to guests', function () {
    $this->get(route('login'))->assertOk()->assertSee('Masuk');
});

it('redirects guests away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('authenticates an admin and sends them to the dashboard', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $user->assignRole(RoleName::ADMIN);

    Livewire::test('auth.login')
        ->set('email', 'admin@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    Livewire::test('auth.login')
        ->set('email', 'salah@example.com')
        ->set('password', 'bukan-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('forbids the dashboard for users without a role', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('allows an admin to open the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::ADMIN);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Operasional');
});

it('throttles repeated failed logins', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $user->assignRole(RoleName::ADMIN);

    foreach (range(1, 5) as $attempt) {
        Livewire::test('auth.login')
            ->set('email', 'admin@example.com')
            ->set('password', 'salah')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    Livewire::test('auth.login')
        ->set('email', 'admin@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(auth()->check())->toBeFalse();
});