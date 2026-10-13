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

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">No. KTP</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Daftar</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Check-in</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($workSession->sessionDrivers()->with('driver')->latest('registered_at')->get() as $roster)
                    <tr wire:key="roster-{{ $roster->id }}" class="transition-colors hover:bg-slate-50/70">

                        <td class="px-4 py-3.5">
                            <span class="font-semibold text-slate-900">{{ $roster->driver->name }}</span>
                        </td>

                        <td class="px-4 py-3.5">
                            <div
                                class="relative inline-block"
                                x-data="{ show: false }"
                                x-on:mouseenter="show = true"
                                x-on:mouseleave="show = false"
                                x-on:click="show = !show"
                            >
                                <span class="cursor-pointer select-none font-mono text-xs text-slate-500 transition-colors hover:text-slate-900">
                                    <span x-show="!show">{{ $roster->driver->maskedKtp() }}</span>
                                    <span x-show="show" x-cloak>{{ $roster->driver->ktp }}</span>
                                </span>
                            </div>
                        </td>

                        <td class="px-4 py-3.5 text-slate-600">{{ $roster->registered_at->format('H:i') }}</td>

                        <td class="px-4 py-3.5">
                            @if ($roster->checked_in_at)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                    {{ $roster->checked_in_at->format('H:i') }}
                                    @if ($roster->checked_in_by)
                                        <span class="font-normal text-green-600">· manual</span>
                                    @endif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Belum
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (! $roster->checked_in_at)
                                    <button
                                        type="button"
                                        wire:click="checkInManually({{ $roster->id }})"
                                        wire:confirm="Catat {{ $roster->driver->name }} sebagai hadir (check-in manual)?"
                                        title="Check-in manual"
                                        aria-label="Check-in manual"
                                        class="group inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 transition-all duration-150 hover:border-green-500 hover:bg-green-500 hover:text-white hover:shadow-sm active:scale-95"
                                    >
                                        <svg class="h-4 w-4 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    wire:click="remove({{ $roster->id }})"
                                    wire:confirm="Hapus supir dari roster?"
                                    title="Hapus dari roster"
                                    aria-label="Hapus dari roster"
                                    class="group inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 transition-all duration-150 hover:border-red-500 hover:bg-red-500 hover:text-white hover:shadow-sm active:scale-95"
                                >
                                    <svg class="h-4 w-4 transition-transform group-hover:scale-110 group-hover:rotate-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <p class="mt-3 text-sm font-medium text-slate-700">Belum ada supir terdaftar</p>
                            <p class="mt-1 text-xs text-slate-500">Bagikan link registrasi di atas untuk memulai.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>