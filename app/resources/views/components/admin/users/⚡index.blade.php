<?php

use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
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
    // Form tambah
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Form edit
    public ?int $editId = null;
    public string $editName = '';
    public string $editEmail = '';
    public string $editPassword = '';
    public string $editPasswordConfirmation = '';

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

        $this->dispatch('notify', type: 'success', message: 'Admin berhasil ditambahkan.');
    }

    public function saveEdit(int $id, string $name, string $email, string $password = '', string $password_confirmation = ''): void
    {
        $this->editName = $name;
        $this->editEmail = Str::lower(trim($email));
        $this->editPassword = $password;
        $this->editPasswordConfirmation = $password_confirmation;
    
        $validated = $this->validate(
            UpdateAdminUserRequest::rulesFor($id),
            UpdateAdminUserRequest::messagesForUser(),
        );
    
        $target = User::role(RoleName::ADMIN)->findOrFail($id);
    
        app(AdminUserService::class)->update($target, [
            'name' => $validated['editName'],
            'email' => $validated['editEmail'],
            'password' => $validated['editPassword'] ?? null,
        ], auth()->user());
    
        unset($this->admins);
    
        $this->reset(['editId', 'editName', 'editEmail', 'editPassword', 'editPasswordConfirmation']);
    
        $this->dispatch('admin-saved');
        $this->dispatch('notify', type: 'success', message: 'Data admin berhasil diperbarui.');
    }

    public function remove(int $userId, AdminUserService $users): void
    {
        $target = User::role(RoleName::ADMIN)->findOrFail($userId);

        try {
            $users->delete($target, auth()->user());
            $this->dispatch('notify', type: 'success', message: 'Admin berhasil dihapus.');
        } catch (DomainException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }

        unset($this->admins);
    }
};
?>

<div
    class="mx-auto max-w-6xl"
    x-data="{
        // Modal edit
        editOpen: false,
        editUser: { id: null, name: '', email: '', password: '', password_confirmation: '' },
        openEdit(user) {
            this.editUser = {
                id: user.id,
                name: user.name,
                email: user.email,
                password: '',
                password_confirmation: '',
            };
            this.editOpen = true;
        },
        closeEdit() { this.editOpen = false; },
        submitEdit() {
            $wire.saveEdit(
                this.editUser.id,
                this.editUser.name,
                this.editUser.email,
                this.editUser.password,
                this.editUser.password_confirmation
            );
        },

        // Notifikasi
        notifShow: false,
        notifType: 'success',
        notifMessage: '',
        notifTimer: null,
        showNotif(type, message) {
            this.notifType = type;
            this.notifMessage = message;
            this.notifShow = true;
            if (this.notifTimer) clearTimeout(this.notifTimer);
            this.notifTimer = setTimeout(() => { this.notifShow = false; }, 4000);
        }
    }"
    x-on:admin-saved.window="closeEdit()"
    x-on:keydown.escape.window="if (editOpen) closeEdit()"
    x-on:notify.window="showNotif($event.detail.type, $event.detail.message)"
>

    {{-- ===== NOTIFIKASI TOAST (fixed top-center) ===== --}}
    <div
        x-show="notifShow"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 200; min-width: 280px; max-width: 90vw;"
    >
        <div
            :style="'display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); border: 1px solid ' + (notifType === 'success' ? '#a7f3d0' : '#fecaca') + '; background: ' + (notifType === 'success' ? '#ecfdf5' : '#fef2f2') + ';'"
        >
            {{-- Ikon sukses --}}
            <template x-if="notifType === 'success'">
                <svg style="width: 18px; height: 18px; flex-shrink: 0; color: #059669; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </template>
            {{-- Ikon error --}}
            <template x-if="notifType === 'error'">
                <svg style="width: 18px; height: 18px; flex-shrink: 0; color: #dc2626; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                </svg>
            </template>

            <p
                x-text="notifMessage"
                :style="'margin: 0; font-size: 13px; font-weight: 500; ' + (notifType === 'success' ? 'color: #065f46;' : 'color: #991b1b;')"
            ></p>

            <button
                type="button"
                x-on:click="notifShow = false"
                style="margin-left: auto; background: transparent; border: 0; cursor: pointer; padding: 2px;"
                :style="'color: ' + (notifType === 'success' ? '#059669' : '#dc2626') + ';'"
            >
                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div style="margin-bottom: 12px;">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <span>Pengaturan</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">Pengguna</span>
        </nav>
        <h1 style="margin: 4px 0 0; font-size: 20px; font-weight: 700; color: #0f172a; line-height: 1.2;">Pengguna</h1>
        <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">Kelola akun admin yang bisa masuk ke aplikasi.</p>
    </div>

    {{-- ===== 2 KOLOM ===== --}}
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start;">

        {{-- ===== FORM TAMBAH ADMIN ===== --}}
        <form wire:submit="save" style="flex: 0 1 320px; min-width: 280px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.04); display: flex; flex-direction: column; box-sizing: border-box;">
            <div style="border-bottom: 1px solid #f1f5f9; padding: 10px 14px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 28px; height: 28px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; box-shadow: 0 2px 5px rgba(99, 102, 241, 0.3);">
                        <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <h2 style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a;">Tambah admin</h2>
                </div>
            </div>

            <div style="padding: 12px 14px; display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <label for="name" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Nama</label>
                    <input id="name" type="text" wire:model="name" autocomplete="off" placeholder="Nama lengkap"
                           style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                    @error('name') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Email</label>
                    <input id="email" type="email" wire:model="email" autocomplete="off" placeholder="admin@example.com"
                           style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                    @error('email') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                </div>

                {{-- Password (dengan toggle show/hide) --}}
                <div x-data="{ show: false }">
                    <label for="password" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Password</label>
                    <div style="position: relative;">
                        <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password" placeholder="••••••••"
                               style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 40px 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                        <button type="button" x-on:click="show = !show" tabindex="-1"
                                style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: transparent; border: 0; padding: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                            <svg x-show="!show" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="show" x-cloak style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <p style="margin: 4px 0 0; font-size: 10px; color: #94a3b8;">Minimal 8 karakter.</p>
                    @error('password') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                </div>

                {{-- Konfirmasi password (dengan toggle show/hide) --}}
                <div x-data="{ show: false }">
                    <label for="password_confirmation" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Ulangi password</label>
                    <div style="position: relative;">
                        <input id="password_confirmation" :type="show ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" placeholder="••••••••"
                               style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 40px 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                        <button type="button" x-on:click="show = !show" tabindex="-1"
                                style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: transparent; border: 0; padding: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                            <svg x-show="!show" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="show" x-cloak style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Tombol submit dengan spinner --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="jmt-save-btn"
                    style="margin-top: 2px; display: flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 8px; background: #0f172a; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #fff; cursor: pointer; font-family: inherit; min-height: 38px;"
                >
                    <span class="jmt-save-text" style="display: inline-flex; align-items: center; gap: 6px;">
                        Tambah admin
                    </span>
                    <span class="jmt-save-loading" style="display: none; align-items: center; gap: 6px;">
                        <svg style="width: 14px; height: 14px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                        </svg>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </form>

        {{-- ===== DAFTAR ADMIN ===== --}}
        <div style="flex: 1 1 480px; min-width: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.04); overflow: hidden; box-sizing: border-box;">
            <div style="border-bottom: 1px solid #f1f5f9; padding: 10px 14px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 28px; height: 28px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: linear-gradient(135deg, #64748b 0%, #475569 100%); color: #fff; box-shadow: 0 2px 5px rgba(100, 116, 139, 0.3);">
                        <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h2 style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a;">Daftar admin</h2>
                    <span style="margin-left: auto; border-radius: 9999px; background: #f1f5f9; padding: 2px 8px; font-size: 11px; font-weight: 600; color: #475569;">{{ $this->admins->count() }}</span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                            <th style="padding: 8px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Nama</th>
                            <th style="padding: 8px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Email</th>
                            <th style="padding: 8px 14px; text-align: right; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->admins as $admin)
                            <tr wire:key="admin-{{ $admin->id }}" style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 14px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 28px; height: 28px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: {{ $admin->is(auth()->user()) ? 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)' : 'linear-gradient(135deg, #64748b 0%, #475569 100%)' }}; color: #fff; font-size: 11px; font-weight: 700;">
                                            {{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span style="font-weight: 600; color: #0f172a; font-size: 13px;">{{ $admin->name }}</span>
                                            @if ($admin->is(auth()->user()))
                                                <span style="background: #dbeafe; color: #1e40af; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 1px 6px; border-radius: 9999px;">Kamu</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 10px 14px; color: #64748b; font-size: 12px;">{{ $admin->email }}</td>
                                <td style="padding: 10px 14px; text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        {{-- Edit --}}
                                        <button type="button"
                                                x-on:click="openEdit({ id: {{ $admin->id }}, name: @js($admin->name), email: @js($admin->email) })"
                                                title="Edit admin"
                                                style="display: inline-flex; height: 26px; width: 26px; align-items: center; justify-content: center; border: 1px solid #e2e8f0; border-radius: 7px; background: #fff; color: #94a3b8; cursor: pointer; transition: all 0.15s;"
                                                onmouseover="this.style.background='#0f172a'; this.style.color='#fff'; this.style.borderColor='#0f172a';"
                                                onmouseout="this.style.background='#fff'; this.style.color='#94a3b8'; this.style.borderColor='#e2e8f0';">
                                            <svg style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        {{-- Hapus --}}
                                        @unless ($admin->is(auth()->user()))
                                            <button type="button" wire:click="remove({{ $admin->id }})"
                                                    wire:confirm="Hapus admin {{ $admin->name }}?" title="Hapus admin"
                                                    style="display: inline-flex; height: 26px; width: 26px; align-items: center; justify-content: center; border: 1px solid #e2e8f0; border-radius: 7px; background: #fff; color: #94a3b8; cursor: pointer; transition: all 0.15s;"
                                                    onmouseover="this.style.background='#dc2626'; this.style.color='#fff'; this.style.borderColor='#dc2626';"
                                                    onmouseout="this.style.background='#fff'; this.style.color='#94a3b8'; this.style.borderColor='#e2e8f0';">
                                                <svg style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ===== MODAL EDIT ===== --}}
    <div x-show="editOpen" x-cloak style="position: fixed; inset: 0; z-index: 100;">
        <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; padding: 16px; box-sizing: border-box;">

            {{-- Backdrop --}}
            <div x-on:click="closeEdit()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.5);"></div>

            {{-- Modal box --}}
            <div style="position: relative; width: 100%; max-width: 420px; background: #fff; border-radius: 14px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05); overflow: hidden;">

                {{-- Header --}}
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding: 12px 16px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 28px; height: 28px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; box-shadow: 0 2px 5px rgba(245, 158, 11, 0.3);">
                            <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h2 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">Edit admin</h2>
                    </div>
                    <button type="button" x-on:click="closeEdit()" title="Tutup"
                            style="display: inline-flex; width: 26px; height: 26px; align-items: center; justify-content: center; border: 0; border-radius: 6px; background: transparent; color: #94a3b8; cursor: pointer;">
                        <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Form --}}
                <form x-on:submit.prevent="submitEdit()" style="padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;">

                    <div>
                        <label for="editName" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Nama</label>
                        <input id="editName" type="text" x-model="editUser.name" autocomplete="off"
                               style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                        @error('editName') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="editEmail" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Email</label>
                        <input id="editEmail" type="email" x-model="editUser.email" autocomplete="off"
                               style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                        @error('editEmail') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password baru (dengan toggle) --}}
                    <div x-data="{ show: false }">
                        <label for="editPassword" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">
                            Password baru <span style="font-weight: 400; color: #94a3b8;">(opsional)</span>
                        </label>
                        <div style="position: relative;">
                            <input id="editPassword" :type="show ? 'text' : 'password'" x-model="editUser.password" autocomplete="new-password" placeholder="Kosongkan jika tidak ganti"
                                   style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 40px 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                            <button type="button" x-on:click="show = !show" tabindex="-1"
                                    style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: transparent; border: 0; padding: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                                <svg x-show="!show" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" x-cloak style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        @error('editPassword') <p style="margin: 4px 0 0; font-size: 11px; color: #dc2626;">{{ $message }}</p> @enderror
                    </div>

                    {{-- Konfirmasi password (dengan toggle) --}}
                    <div x-data="{ show: false }">
                        <label for="editPasswordConfirmation" style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Ulangi password baru</label>
                        <div style="position: relative;">
                            <input id="editPasswordConfirmation" :type="show ? 'text' : 'password'" x-model="editUser.password_confirmation" autocomplete="new-password" placeholder="••••••••"
                                   style="display: block; width: 100%; box-sizing: border-box; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0 40px 0 12px; font-size: 13px; color: #0f172a; outline: none; font-family: inherit;">
                            <button type="button" x-on:click="show = !show" tabindex="-1"
                                    style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: transparent; border: 0; padding: 4px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                                <svg x-show="!show" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" x-cloak style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px;">
                        <button type="button" x-on:click="closeEdit()"
                                style="border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 9px 16px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; font-family: inherit;">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveEdit"
                                class="jmt-save-btn"
                                style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 8px; background: #0f172a; padding: 9px 16px; font-size: 13px; font-weight: 600; color: #fff; cursor: pointer; font-family: inherit; min-width: 100px;">
                            <span class="jmt-save-text">Simpan</span>
                            <span class="jmt-save-loading" style="display: none; align-items: center; gap: 6px;">
                                <svg style="width: 14px; height: 14px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                                </svg>
                                Menyimpan...
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>