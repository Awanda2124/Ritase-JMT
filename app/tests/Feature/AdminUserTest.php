<?php

use App\Models\User;
use App\Models\WorkSession;
use App\Services\AdminUserService;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function makeAdminUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole(RoleName::ADMIN);

    return $user;
}

it('redirects guests away from the users page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('forbids the users page for users without the admin role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('users.index'))
        ->assertForbidden();
});

it('lists the admins on the users page', function () {
    $admin = makeAdminUser(['name' => 'Admin Satu']);
    makeAdminUser(['name' => 'Admin Dua']);
    User::factory()->create(['name' => 'Bukan Admin']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Admin Satu')
        ->assertSee('Admin Dua')
        ->assertDontSee('Bukan Admin');
});

it('shows the users link in the settings submenu', function () {
    $admin = makeAdminUser();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('users.index'))
        ->assertSee('Pengguna')
        ->assertSee('Umum');
});

it('lets an admin add another admin with a hashed password and an audit log', function () {
    $admin = makeAdminUser();

    Livewire::actingAs($admin)
        ->test('admin.users.index')
        ->set('name', 'Admin Baru')
        ->set('email', 'Baru@Example.com')
        ->set('password', 'rahasia123')
        ->set('password_confirmation', 'rahasia123')
        ->call('save')
        ->assertHasNoErrors();

    $created = User::query()->where('email', 'baru@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole(RoleName::ADMIN))->toBeTrue()
        ->and(Hash::check('rahasia123', $created->password))->toBeTrue()
        ->and($created->password)->not->toBe('rahasia123')
        ->and(Activity::query()->where('description', 'Menambah admin')->exists())->toBeTrue();
});

it('validates the new admin form', function () {
    $admin = makeAdminUser(['email' => 'sudah@example.com']);

    Livewire::actingAs($admin)
        ->test('admin.users.index')
        ->set('name', '')
        ->set('email', 'sudah@example.com')
        ->set('password', 'pendek')
        ->set('password_confirmation', 'beda')
        ->call('save')
        ->assertHasErrors(['name', 'email', 'password']);

    expect(User::query()->count())->toBe(1);
});

it('lets an admin delete another admin and writes an audit log', function () {
    $admin = makeAdminUser();
    $other = makeAdminUser(['name' => 'Admin Lain']);

    Livewire::actingAs($admin)
        ->test('admin.users.index')
        ->call('remove', $other->id);

    expect(User::query()->whereKey($other->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('description', 'Menghapus admin')->exists())->toBeTrue();
});

it('does not let an admin delete their own account', function () {
    $admin = makeAdminUser();
    makeAdminUser();

    Livewire::actingAs($admin)
        ->test('admin.users.index')
        ->call('remove', $admin->id);

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});

it('does not delete the last admin', function () {
    $lastAdmin = makeAdminUser();
    $outsider = User::factory()->create();

    expect(fn () => app(AdminUserService::class)->delete($lastAdmin, $outsider))
        ->toThrow(DomainException::class, 'Admin terakhir tidak boleh dihapus.');

    expect(User::query()->whereKey($lastAdmin->id)->exists())->toBeTrue();
});

it('does not delete an admin who created work sessions', function () {
    $admin = makeAdminUser();
    $creator = makeAdminUser();
    WorkSession::factory()->create(['created_by' => $creator->id]);

    Livewire::actingAs($admin)
        ->test('admin.users.index')
        ->call('remove', $creator->id);

    expect(User::query()->whereKey($creator->id)->exists())->toBeTrue();
});