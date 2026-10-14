<?php

use App\Http\Requests\StoreAdminUserRequest;
use App\Models\User;
use App\Services\AdminUserService;
use App\Support\RoleName;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Pengguna')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Computed]
    public function admins(): Collection
    {
        return User::role(RoleName::ADMIN)->orderBy('name')->get();
    }

    public function save(AdminUserService $users): void
    {
        $this->email = Str::lower(trim($this->email));

        $form = new StoreAdminUserRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        $users->create($validated, auth()->user());

        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        unset($this->admins);

        session()->flash('status', 'Admin ditambahkan.');
    }

    public function remove(int $userId, AdminUserService $users): void
    {
        $target = User::role(RoleName::ADMIN)->findOrFail($userId);

        try {
            $users->delete($target, auth()->user());
            session()->flash('status', 'Admin dihapus.');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->admins);
    }
};
?>

<div>
    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
        <span>Pengaturan</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-900">Pengguna</span>
    </nav>
    <h1 class="mt-3 text-2xl font-bold text-slate-900 sm:text-3xl">Pengguna</h1>
    <p class="mt-1 text-sm text-slate-500">Kelola akun admin yang bisa masuk ke aplikasi.</p>

    @if (session('status'))
        <p class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</p>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Form tambah admin --}}
        <form wire:submit="save" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
            <h2 class="text-base font-bold text-slate-900">Tambah admin</h2>

            <div>
                <label for="name" class="block text-sm font-medium">Nama</label>
                <input id="name" type="text" wire:model="name" autocomplete="off"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="off"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="new-password"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter.</p>
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Ulangi password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <button type="submit"
                    wire:loading.attr="disabled" wire:target="save"
                    class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Tambah admin</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </form>

        {{-- Daftar admin --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->admins as $admin)
                        <tr wire:key="admin-{{ $admin->id }}" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3.5">
                                <span class="font-semibold text-slate-900">{{ $admin->name }}</span>
                                @if ($admin->is(auth()->user()))
                                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Kamu</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-slate-600">{{ $admin->email }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @unless ($admin->is(auth()->user()))
                                    <button type="button"
                                            wire:click="remove({{ $admin->id }})"
                                            wire:confirm="Hapus admin {{ $admin->name }}?"
                                            class="text-sm text-red-600 underline hover:text-red-800">
                                        Hapus
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>