<?php

use App\Http\Requests\Auth\LoginRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.blank')] #[Title('Masuk')] class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $form = new LoginRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        $form->authenticate(
            $validated['email'],
            $validated['password'],
            $this->remember,
        );

        $this->redirectIntended(route('dashboard'), navigate: true);
    }
};
?>

<div class="login-split">

    {{-- ===== LEFT PANEL ===== --}}
    <aside class="login-left">
        <div class="login-left-photo"></div>

        <svg class="login-route-svg" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <path d="M 100 800 Q 250 650 380 600 T 650 480" fill="none" stroke="#4a9eff" stroke-width="1.5" stroke-linecap="round" opacity="0.7"/>
            <g opacity="0.95">
                <circle cx="240" cy="660" r="16" fill="rgba(74, 158, 255, 0.15)" />
                <circle cx="240" cy="660" r="6" fill="#4a9eff" />
                <circle cx="240" cy="660" r="11" fill="none" stroke="#4a9eff" stroke-width="1.5" />
            </g>
            <g opacity="0.95">
                <circle cx="420" cy="580" r="16" fill="rgba(74, 158, 255, 0.15)" />
                <circle cx="420" cy="580" r="6" fill="#4a9eff" />
                <circle cx="420" cy="580" r="11" fill="none" stroke="#4a9eff" stroke-width="1.5" />
            </g>
            <g opacity="0.95">
                <circle cx="620" cy="500" r="16" fill="rgba(74, 158, 255, 0.15)" />
                <circle cx="620" cy="500" r="6" fill="#4a9eff" />
                <circle cx="620" cy="500" r="11" fill="none" stroke="#4a9eff" stroke-width="1.5" />
            </g>
        </svg>

        <div class="login-left-content">

            {{-- Brand --}}
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 8px 20px -6px rgba(37, 99, 235, 0.6);">
                    <svg style="width: 22px; height: 22px; color: #ffffff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; color: #ffffff;">Absensi Ritase</p>
                    <p style="margin: 2px 0 0; font-size: 12px; color: #93b4d9;">JMT Operasional</p>
                </div>
            </div>

            {{-- Hero --}}
            <div style="max-width: 480px;">
                <h1 style="margin: 0; font-size: 42px; font-weight: 700; line-height: 1.15; letter-spacing: -0.02em; color: #ffffff;">
                    Disiplin Hari Ini,
                    <span style="color: #4a9eff; display: block;">Ritase Lebih Baik</span>
                </h1>
                <p style="margin: 20px 0 0; font-size: 15px; line-height: 1.6; color: #b8cee6; max-width: 400px;">
                    Sistem absensi untuk mendukung kelancaran operasional ritase JMT.
                </p>
            </div>

            {{-- Features --}}
            <div style="display: flex; align-items: center;">
                <div style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
                    <svg style="width: 26px; height: 26px; color: #4a9eff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z" />
                    </svg>
                    <p style="margin: 0; font-size: 12px; font-weight: 500; color: #d0dff0; line-height: 1.35;">Tertib Absensi</p>
                </div>
                <div style="width: 1px; height: 48px; background: rgba(255, 255, 255, 0.15); flex-shrink: 0; margin-right: 20px;"></div>
                <div style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
                    <svg style="width: 26px; height: 26px; color: #4a9eff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M1 3h15v13H1zM16 8h4l3 3v5h-7z" />
                        <circle cx="5.5" cy="18.5" r="2" />
                        <circle cx="18.5" cy="18.5" r="2" />
                    </svg>
                    <p style="margin: 0; font-size: 12px; font-weight: 500; color: #d0dff0; line-height: 1.35;">Kelancaran Ritase</p>
                </div>
                <div style="width: 1px; height: 48px; background: rgba(255, 255, 255, 0.15); flex-shrink: 0; margin-right: 20px;"></div>
                <div style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
                    <svg style="width: 26px; height: 26px; color: #4a9eff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21V9m6 12V3m6 18v-8m6 8V6" />
                    </svg>
                    <p style="margin: 0; font-size: 12px; font-weight: 500; color: #d0dff0; line-height: 1.35;">Produktivitas Bersama</p>
                </div>
            </div>

        </div>
    </aside>

    {{-- ===== RIGHT PANEL ===== --}}
    <main class="login-right">
        <div class="login-right-accent-1"></div>
        <div class="login-right-accent-2"></div>

        <div class="login-right-inner">

            <div style="width: 100%; background: #ffffff; border-radius: 16px; padding: 32px 32px; border: 1px solid #eef2f7; box-shadow: 0 20px 40px -20px rgba(15, 23, 42, 0.12), 0 4px 12px -4px rgba(15, 23, 42, 0.05); box-sizing: border-box;">

                {{-- Header --}}
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="width: 56px; height: 56px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 10px 24px -8px rgba(37, 99, 235, 0.55);">
                        <svg style="width: 26px; height: 26px; color: #ffffff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h1 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a;">Absensi Ritase</h1>
                    <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">JMT Operasional</p>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #94a3b8;">Masuk ke panel admin Ritase JMT</p>
                </div>

                <form wire:submit="login">

                    {{-- Email --}}
                    <div style="display: flex; flex-direction: column; margin-bottom: 14px;">
                        <label for="email" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email</label>
                        <div style="position: relative;">
                            <svg style="position: absolute; top: 50%; left: 14px; transform: translateY(-50%); width: 18px; height: 18px; color: #94a3b8; pointer-events: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <input
                                id="email"
                                type="email"
                                wire:model="email"
                                autocomplete="username"
                                wire:loading.attr="disabled"
                                wire:target="login"
                                placeholder="admin@example.com"
                                class="login-input"
                            >
                        </div>
                        @error('email')
                            <p style="margin: 5px 0 0; font-size: 12px; color: #dc2626; font-weight: 500;">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div style="display: flex; flex-direction: column; margin-bottom: 14px;" x-data="{ show: false }">
                        <label for="password" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">Kata sandi</label>
                        <div style="position: relative;">
                            <svg style="position: absolute; top: 50%; left: 14px; transform: translateY(-50%); width: 18px; height: 18px; color: #94a3b8; pointer-events: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <input
                                id="password"
                                :type="show ? 'text' : 'password'"
                                wire:model="password"
                                autocomplete="current-password"
                                wire:loading.attr="disabled"
                                wire:target="login"
                                placeholder="••••••••"
                                class="login-input login-input-password"
                            >
                            <button type="button" x-on:click="show = !show" tabindex="-1" style="position: absolute; top: 50%; right: 8px; transform: translateY(-50%); background: transparent; border: 0; padding: 8px; cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                <svg x-show="!show" style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" x-cloak style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p style="margin: 5px 0 0; font-size: 12px; color: #dc2626; font-weight: 500;">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Remember --}}
                    <label style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #334155; cursor: pointer; user-select: none; margin-bottom: 14px;">
                        <input type="checkbox" wire:model="remember" style="width: 17px; height: 17px; border-radius: 5px; border: 1.5px solid #cbd5e1; cursor: pointer; accent-color: #2563eb; margin: 0;">
                        <span>Ingat saya</span>
                    </label>

                    {{-- Submit --}}
                    <button type="submit" wire:loading.attr="disabled" wire:target="login" class="login-btn">
                        <span class="login-btn-text">
                            Masuk
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </span>
                        <span class="login-btn-loading">
                            <span class="login-btn-spinner"></span>
                            <span>Memproses...</span>
                        </span>
                    </button>

                </form>
            </div>

            <div style="font-size: 12px; color: #94a3b8; text-align: center;">
                © {{ date('Y') }} JMT — Sistem Absensi Ritase · by
                <a href="https://awantech.co.id" target="_blank" rel="noopener" style="color: #2563eb; text-decoration: none; font-weight: 600;">
                    AwanTech - JMT
                </a>
            </div>

        </div>
    </main>

</div>