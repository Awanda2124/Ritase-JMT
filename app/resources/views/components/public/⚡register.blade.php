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
            <!-- <p class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">Pendaftaran berhasil. Silakan datang ke JMT untuk lapor ritase.</p> -->
            <p class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">
                Pendaftaran berhasil. Saat tiba di JMT, buka <span class="font-medium">{{ route('public.checkin') }}</span> untuk check-in.
            </p>
        <!-- @elseif ($workSession->isFull())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Penuh</p>
            <p class="mt-2 text-sm text-slate-600">Kuota sesi ini sudah habis.</p> -->
        @elseif ($workSession->hasClosed())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Pendaftaran ditutup</p>
            <p class="mt-2 text-sm text-slate-600">Sesi ini sudah melewati jam penutupan.</p>
        @elseif (! $workSession->hasOpened())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-lg font-semibold text-amber-900">Belum dibuka</p>
            <p class="mt-2 text-sm text-slate-600">Pendaftaran dibuka {{ $workSession->opens_at->translatedFormat('d M Y, H:i') }}.</p>
        @elseif ($workSession->isFull())
        @else
            <p class="mt-2 text-sm text-slate-500">Sisa kuota: {{ $workSession->remainingQuota() }}</p>
            <form wire:submit="submit" class="mt-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium">Nama</label>
                    <input id="name" type="text" wire:model="name" autocomplete="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ktp" class="block text-sm font-medium">No. KTP (16 digit)</label>
                    <input id="ktp" type="text" inputmode="numeric" maxlength="16" wire:model="ktp" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                    @error('ktp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white hover:bg-slate-800">
                    Daftar
                </button>
            </form>
        @endif
    </div>
</div>
