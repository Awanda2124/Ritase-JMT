<?php

use App\Exceptions\AlreadyRegisteredException;
use App\Exceptions\QuotaFullException;
use App\Http\Requests\RegisterDriverRequest;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Exceptions\SessionNotOpenException;

new #[Layout('layouts.guest')] #[Title('Registrasi supir')] class extends Component
{
    public WorkSession $workSession;

    public string $name = '';

    public string $ktp = '';

    public bool $registered = false;

    public function mount(string $token): void
    {
        $this->workSession = WorkSession::query()->where('token', $token)->firstOrFail();
    }

    public function submit(DriverRegistrationService $registration): void
    {
        if (! $this->workSession->isOpen() || $this->workSession->isFull()) {
            return;
        }

        $form = new RegisterDriverRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        try {
            $registration->register($this->workSession, $validated['name'], $validated['ktp']);
            $this->registered = true;
            $this->workSession->refresh();
        } catch (QuotaFullException|SessionNotOpenException) {
            $this->workSession->refresh();
        } catch (AlreadyRegisteredException) {
            $this->addError('ktp', 'KTP ini sudah terdaftar pada sesi ini.');
        }
    }
};
?>

<div class="flex min-h-screen items-center justify-center px-4 py-8">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Registrasi supir</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $workSession->route }} · {{ $workSession->date->translatedFormat('d M Y') }}</p>

        @if ($registered)
            <div class="mt-6 rounded-xl bg-green-50 p-5">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 shrink-0 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-green-900">Pendaftaran berhasil</p>
                        <p class="mt-1 text-sm text-green-800">
                            Saat tiba di JMT, buka halaman check-in untuk konfirmasi kehadiran Anda.
                        </p>
                    </div>
                </div>

                <a
                    href="{{ route('public.checkin') }}"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white hover:bg-slate-800 active:bg-slate-950"
                >
                    Buka halaman Check-in
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        @elseif ($workSession->hasClosed())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Pendaftaran ditutup</p>
            <p class="mt-2 text-sm text-slate-600">Sesi ini sudah melewati jam penutupan.</p>
        @elseif (! $workSession->hasOpened())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Belum dibuka</p>
            <p class="mt-2 text-sm text-slate-600">Pendaftaran dibuka {{ $workSession->opens_at->translatedFormat('d M Y, H:i') }}.</p>
            @elseif ($workSession->isFull())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Penuh</p>
            <p class="mt-2 text-sm text-slate-600">Kuota sesi ini sudah habis.</p>
        @else
            <p class="mt-2 text-sm text-slate-500">Sisa kuota: {{ $workSession->remainingQuota() }}</p>
            <form wire:submit="submit" class="mt-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium">Nama</label>
                    <input
                        id="name"
                        type="text"
                        wire:model="name"
                        autocomplete="name"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base disabled:bg-slate-50 disabled:text-slate-400"
                    >
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ktp" class="block text-sm font-medium">No. KTP (16 digit)</label>
                    <input
                        id="ktp"
                        type="text"
                        inputmode="numeric"
                        maxlength="16"
                        wire:model="ktp"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base disabled:bg-slate-50 disabled:text-slate-400"
                    >
                    @error('ktp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white hover:bg-slate-800 disabled:opacity-70 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="submit">Daftar</span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="spinner-jmt" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"/>
                            <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                        </svg>
                        Mendaftarkan...
                    </span>
                </button>
            </form>
        @endif
    </div>
</div>

<style>
    .spinner-jmt {
        width: 16px;
        height: 16px;
        animation: spinner-jmt-spin 0.8s linear infinite;
        flex-shrink: 0;
    }
    @keyframes spinner-jmt-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>