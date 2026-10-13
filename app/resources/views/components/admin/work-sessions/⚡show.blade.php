<?php

use App\Models\SessionDriver;
use App\Models\WorkSession;
use App\Services\CheckInService;
use App\Services\DriverRegistrationService;
use App\Support\RoleName;
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

    public function checkInManually(int $sessionDriverId, CheckInService $checkIn): void
    {
        abort_unless(auth()->user()?->hasRole(RoleName::ADMIN), 403);

        $sessionDriver = SessionDriver::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($sessionDriverId);

        $checkIn->checkInManually($sessionDriver, auth()->user());
        $this->workSession->refresh();
        session()->flash('status', 'Check-in manual dicatat.');
    }
};
?>

<div>
    <p class="text-sm"><a href="{{ route('work-sessions.index') }}" class="underline">Sesi kerja</a></p>
    <h1 class="mt-2 text-2xl font-semibold">{{ $workSession->date->translatedFormat('d M Y') }}</h1>
    <p class="mt-1 text-slate-600">{{ $workSession->route }} · registrasi {{ $workSession->opens_at->format('H:i') }}–{{ $workSession->closes_at->format('H:i') }}</p>
    <p class="mt-1 text-sm text-slate-500">
        Batas check-in {{ $workSession->checkInDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
        · Batas lapor {{ $workSession->reportDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
    </p>
    <p class="mt-1 text-sm text-slate-500">
        Kuota terisi {{ $workSession->sessionDrivers()->count() }}/{{ $workSession->quota }}
        · Hadir {{ $workSession->sessionDrivers()->whereNotNull('checked_in_at')->count() }}
    </p>

    @if (session('status'))
        <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <div class="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-4">
        {{-- Link Registrasi --}}
        <div x-data="{
                copied: false,
                async copy(text) {
                    try {
                        await navigator.clipboard.writeText(text);
                    } catch (e) {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }">
            <h2 class="font-medium">Link registrasi</h2>
            <div class="mt-1 flex items-start gap-2">
                <p class="min-w-0 flex-1 break-all rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $workSession->registrationUrl() }}</p>
                <button
                    type="button"
                    x-on:click="copy('{{ $workSession->registrationUrl() }}')"
                    class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <span x-show="!copied" class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Salin
                    </span>
                    <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5 text-green-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Tersalin
                    </span>
                </button>
            </div>
        </div>

        {{-- Link Check-in --}}
        <div x-data="{
                copied: false,
                async copy(text) {
                    try {
                        await navigator.clipboard.writeText(text);
                    } catch (e) {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }">
            <h2 class="font-medium">Link check-in (di JMT)</h2>
            <div class="mt-1 flex items-start gap-2">
                <p class="min-w-0 flex-1 break-all rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ route('public.checkin') }}</p>
                <button
                    type="button"
                    x-on:click="copy('{{ route('public.checkin') }}')"
                    class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <span x-show="!copied" class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Salin
                    </span>
                    <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5 text-green-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Tersalin
                    </span>
                </button>
            </div>
        </div>

        <a href="{{ route('work-sessions.edit', $workSession) }}" class="inline-block text-sm underline">Ubah sesi</a>
    </div>

    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">No. KTP</th>
                    <th class="px-4 py-3 font-medium">Daftar</th>
                    <th class="px-4 py-3 font-medium">Check-in</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workSession->sessionDrivers()->with('driver')->latest('registered_at')->get() as $roster)
                    <tr wire:key="roster-{{ $roster->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $roster->driver->name }}</td>
                        <td class="px-4 py-3 font-mono">{{ $roster->driver->maskedKtp() }}</td>
                        <td class="px-4 py-3">{{ $roster->registered_at->format('H:i') }}</td>
                        <td class="px-4 py-3">
                            @if ($roster->checked_in_at)
                                {{ $roster->checked_in_at->format('H:i') }}
                                @if ($roster->checked_in_by)
                                    <span class="text-xs text-slate-500">(manual)</span>
                                @endif
                            @else
                                <span class="text-slate-400">Belum</span>
                            @endif
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right">
                            @if (! $roster->checked_in_at)
                                <button type="button" wire:click="checkInManually({{ $roster->id }})" wire:confirm="Catat {{ $roster->driver->name }} sebagai hadir (check-in manual)?" class="underline">
                                    Check-in manual
                                </button>
                            @endif
                            <button type="button" wire:click="remove({{ $roster->id }})" wire:confirm="Hapus supir dari roster?" class="text-red-700 underline">
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-slate-500">Belum ada supir terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>