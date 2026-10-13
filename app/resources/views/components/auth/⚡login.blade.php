<?php

use App\Http\Requests\Auth\LoginRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Masuk')] class extends Component
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

<div class="flex min-h-screen items-center justify-center px-4 py-8" style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);">
    <div class="login-card">

        {{-- Header --}}
        <div class="login-header">
            <div class="login-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="login-title">Selamat Datang</h1>
            <p class="login-subtitle">Masuk ke panel admin Ritase JMT</p>
        </div>

        <form wire:submit="login" class="login-form">
            {{-- Email --}}
            <div class="login-field">
                <label for="email" class="login-label">Email</label>
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
                @error('email')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="login-field" x-data="{ show: false }">
                <label for="password" class="login-label">Kata sandi</label>
                <div class="login-input-wrap">
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
                    <button
                        type="button"
                        x-on:click="show = !show"
                        tabindex="-1"
                        class="login-eye"
                    >
                        <svg x-show="!show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="show" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Ingat saya --}}
            <label class="login-remember">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="login-checkbox"
                >
                <span>Ingat saya</span>
            </label>

            {{-- Tombol Masuk --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="login"
                class="login-btn"
            >
                <span class="login-btn-text">Masuk</span>
                <span class="login-btn-loading">
                    <span class="login-btn-spinner"></span>
                    <span>Memproses...</span>
                </span>
            </button>
        </form>

    </div>
</div>

<style>
    /* ===== CARD ===== */
    .login-card {
        width: 100%;
        max-width: 420px;
        background: #ffffff;
        border-radius: 20px;
        padding: 40px 32px;
        box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.15), 0 0 0 1px rgba(15, 23, 42, 0.05);
    }

    /* ===== HEADER ===== */
    .login-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .login-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.35);
    }

    .login-icon svg {
        width: 30px;
        height: 30px;
        color: #ffffff;
    }

    .login-title {
        font-size: 26px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.02em;
        margin: 0;
    }

    .login-subtitle {
        margin-top: 6px;
        font-size: 14px;
        color: #64748b;
    }

    /* ===== FORM ===== */
    .login-form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .login-field {
        display: flex;
        flex-direction: column;
    }

    .login-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
        letter-spacing: 0.01em;
    }

    .login-input {
        display: block;
        width: 100%;
        box-sizing: border-box;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        padding: 13px 14px;
        font-size: 16px;
        color: #0f172a;
        outline: none;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        font-family: inherit;
    }

    .login-input::placeholder {
        color: #94a3b8;
    }

    .login-input:focus {
        border-color: #0f172a;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08);
    }

    .login-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .login-input-wrap {
        position: relative;
    }

    .login-input-password {
        padding-right: 46px;
    }

    .login-eye {
        position: absolute;
        top: 50%;
        right: 8px;
        transform: translateY(-50%);
        background: transparent;
        border: 0;
        padding: 8px;
        cursor: pointer;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: color 0.15s ease, background 0.15s ease;
    }

    .login-eye:hover {
        color: #0f172a;
        background: #f1f5f9;
    }

    .login-eye svg {
        width: 20px;
        height: 20px;
    }

    .login-error {
        margin-top: 6px;
        font-size: 13px;
        color: #dc2626;
        font-weight: 500;
    }

    /* ===== REMEMBER ===== */
    .login-remember {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: #334155;
        cursor: pointer;
        user-select: none;
        margin-top: -4px;
    }

    .login-checkbox {
        width: 18px;
        height: 18px;
        border-radius: 5px;
        border: 1.5px solid #cbd5e1;
        cursor: pointer;
        accent-color: #0f172a;
        margin: 0;
    }

    /* ===== BUTTON ===== */
    .login-btn {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 15px;
        font-size: 16px;
        font-weight: 600;
        color: #ffffff;
        border: 0;
        cursor: pointer;
        transition: opacity 0.15s ease, transform 0.1s ease;
        margin-top: 6px;
        box-shadow: 0 8px 16px -4px rgba(15, 23, 42, 0.35);
        font-family: inherit;
        letter-spacing: 0.01em;
    }

    .login-btn:hover:not([disabled]) {
        opacity: 0.95;
    }

    .login-btn:active:not([disabled]) {
        transform: scale(0.98);
    }

    .login-btn[disabled] {
        opacity: 0.75;
        cursor: not-allowed;
    }

    .login-btn-text {
        display: inline;
    }

    .login-btn-loading {
        display: none;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }

    .login-btn[disabled] .login-btn-text {
        display: none;
    }

    .login-btn[disabled] .login-btn-loading {
        display: inline-flex;
    }

    .login-btn-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        box-sizing: border-box;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: login-btn-spin 0.7s linear infinite;
        flex-shrink: 0;
    }

    @keyframes login-btn-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>