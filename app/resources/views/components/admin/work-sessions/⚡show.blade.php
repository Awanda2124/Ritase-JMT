<?php

use App\Models\SessionDriver;
use App\Models\WorkSession;
use App\Services\DriverRegistrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Roster sesi')] class extends Component
{
    public WorkSession $workSession;

    public function mount(WorkSession $workSession): void
    {
        $this->workSession = $workSession;
    }

    public function remove(int $sessionDriverId, DriverRegistrationService $registration): void
    {
        $sessionDriver = SessionDriver::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($sessionDriverId);

        $registration->removeFromRoster($sessionDriver, auth()->user());
        $this->workSession->refresh();
        session()->flash('status', 'Supir dihapus dari roster.');
    }
};
?>

<div>
    <p class="text-sm"><a href="{{ route('work-sessions.index') }}" class="underline">Sesi kerja</a></p>
    <h1 class="mt-2 text-2xl font-semibold">{{ $workSession->date->translatedFormat('d M Y') }}</h1>
    <p class="mt-1 text-slate-600">{{ $workSession->route }} · {{ $workSession->opens_at->format('H:i') }}–{{ $workSession->closes_at->format('H:i') }}</p>
    <p class="mt-1 text-sm text-slate-500">Kuota terisi {{ $workSession->sessionDrivers()->count() }}/{{ $workSession->quota }}</p>

    @if (session('status'))
        <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="font-medium">Link registrasi</h2>
        <p class="mt-1 break-all text-sm text-slate-700">{{ $workSession->registrationUrl() }}</p>
        <a href="{{ route('work-sessions.edit', $workSession) }}" class="mt-3 inline-block text-sm underline">Ubah sesi</a>
    </div>

    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">No. KTP</th>
                    <th class="px-4 py-3 font-medium">Daftar</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workSession->sessionDrivers()->with('driver')->latest('registered_at')->get() as $roster)
                    <tr wire:key="roster-{{ $roster->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $roster->driver->name }}</td>
                        <td class="px-4 py-3 font-mono">{{ $roster->driver->maskedKtp() }}</td>
                        <td class="px-4 py-3">{{ $roster->registered_at->format('H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="remove({{ $roster->id }})" wire:confirm="Hapus supir dari roster?" class="text-red-700 underline">
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-slate-500">Belum ada supir terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
