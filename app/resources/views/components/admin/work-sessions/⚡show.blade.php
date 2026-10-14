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

@php
    $filled = $workSession->sessionDrivers()->count();
    $quota = max((int) $workSession->quota, 1);
    $hadir = $workSession->sessionDrivers()->whereNotNull('checked_in_at')->count();
    $percent = min(100, round(($filled / $quota) * 100));
@endphp

<div style="display: flex; flex-direction: column; gap: 20px;">
    {{-- ===== HEADER ===== --}}
    <div>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <a href="{{ route('work-sessions.index') }}" class="hover:text-slate-900">Sesi Kerja</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">{{ $workSession->date->translatedFormat('d M Y') }}</span>
        </nav>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ $workSession->route }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $workSession->date->translatedFormat('d M Y') }}
                    · registrasi {{ $workSession->opens_at->format('H:i') }}–{{ $workSession->closes_at->format('H:i') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('work-sessions.verify', $workSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Cek tiket
                </a>
                <a href="{{ route('work-sessions.export', $workSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export
                </a>
                <a href="{{ route('work-sessions.edit', $workSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Ubah
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
        </div>
    @endif

    {{-- ===== STAT CARDS (pakai inline CSS grid, anti-gagal) ===== --}}
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
        <style>
            @media (min-width: 1024px) {
                .jmt-stat-grid { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
            }
        </style>
        <div class="jmt-stat-grid" style="display: contents;">

            {{-- Terdaftar --}}
            <div style="border: 1px solid #e2e8f0; background: #fff; border-radius: 16px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #dbeafe; color: #2563eb;">
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <p style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">Terdaftar</p>
                </div>
                <p style="margin: 10px 0 0; font-size: 20px; font-weight: 700; color: #0f172a;">
                    {{ $filled }}<span style="margin-left: 4px; font-size: 13px; font-weight: 600; color: #94a3b8;">/ {{ $quota }}</span>
                </p>
                <div style="margin-top: 8px; height: 4px; overflow: hidden; border-radius: 9999px; background: #f1f5f9;">
                    <div style="height: 100%; border-radius: 9999px; background: #3b82f6; width: {{ $percent }}%;"></div>
                </div>
            </div>

            {{-- Hadir --}}
            <div style="border: 1px solid #e2e8f0; background: #fff; border-radius: 16px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #d1fae5; color: #059669;">
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">Hadir</p>
                </div>
                <p style="margin: 10px 0 0; font-size: 20px; font-weight: 700; color: #0f172a;">{{ $hadir }}</p>
                <p style="margin: 2px 0 0; font-size: 11px; color: #64748b;">Check-in di JMT</p>
            </div>

            {{-- Batas check-in --}}
            <div style="border: 1px solid #e2e8f0; background: #fff; border-radius: 16px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #fef3c7; color: #d97706;">
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">Batas check-in</p>
                </div>
                <p style="margin: 10px 0 0; font-size: 14px; font-weight: 700; color: #0f172a;">
                    {{ $workSession->checkInDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
                </p>
            </div>

            {{-- Batas lapor --}}
            <div style="border: 1px solid #e2e8f0; background: #fff; border-radius: 16px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #ede9fe; color: #7c3aed;">
                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <p style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0;">Batas lapor</p>
                </div>
                <p style="margin: 10px 0 0; font-size: 14px; font-weight: 700; color: #0f172a;">
                    {{ $workSession->reportDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
                </p>
            </div>

        </div>
    </div>

    {{-- ===== LINK PUBLIK ===== --}}
    <div
        class="rounded-2xl border border-slate-200 bg-white shadow-sm"
        x-data="{
            copiedKey: null,
            async copy(key, text) {
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
                this.copiedKey = key;
                setTimeout(() => this.copiedKey = null, 2000);
            }
        }"
    >
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Link publik</h2>
            <p class="text-xs text-slate-500">Bagikan ke supir untuk registrasi dan check-in.</p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px; padding: 20px;">
            @foreach ($workSession->groups()->withCount('sessionDrivers')->get() as $group)
                <div wire:key="group-link-{{ $group->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">Registrasi {{ $group->name }}</h3>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                            {{ $group->session_drivers_count }}/{{ $group->quota }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-stretch gap-2">
                        <p class="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-600">{{ $group->registrationUrl() }}</p>
                        <button type="button" x-on:click="copy('group-{{ $group->id }}', '{{ $group->registrationUrl() }}')"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-900 hover:text-white hover:border-slate-900">
                            <svg x-show="copiedKey !== 'group-{{ $group->id }}'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copiedKey === 'group-{{ $group->id }}'" x-cloak class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-show="copiedKey !== 'group-{{ $group->id }}'">Salin</span>
                            <span x-show="copiedKey === 'group-{{ $group->id }}'" x-cloak>Tersalin</span>
                        </button>
                    </div>
                </div>
            @endforeach

            <div class="border-t border-slate-100 pt-4">
                <h3 class="text-sm font-semibold text-slate-900">Check-in (semua kelompok)</h3>
                <div class="mt-2 flex items-stretch gap-2">
                    <p class="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-600">{{ route('public.checkin') }}</p>
                    <button type="button" x-on:click="copy('checkin', '{{ route('public.checkin') }}')"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-900 hover:text-white hover:border-slate-900">
                        <svg x-show="copiedKey !== 'checkin'" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <svg x-show="copiedKey === 'checkin'" x-cloak class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-show="copiedKey !== 'checkin'">Salin</span>
                        <span x-show="copiedKey === 'checkin'" x-cloak>Tersalin</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== ROSTER ===== --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Roster supir</h2>
            <p class="text-xs text-slate-500">Semua supir yang terdaftar di sesi ini.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50/50">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">KTP</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Daftar</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Check-in</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($workSession->sessionDrivers()->with(['driver', 'group'])->latest('registered_at')->get() as $roster)
                        <tr wire:key="roster-{{ $roster->id }}" class="transition-colors hover:bg-slate-50/70">

                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-slate-900">{{ $roster->driver->name }}</span>
                            </td>

                            <td class="px-4 py-3.5">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $roster->group?->name ?? '-' }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="relative inline-block" x-data="{ show: false }"
                                     x-on:mouseenter="show = true" x-on:mouseleave="show = false" x-on:click="show = !show">
                                    <span class="cursor-pointer select-none font-mono text-xs text-slate-500 transition-colors hover:text-slate-900">
                                        <span x-show="!show">{{ $roster->driver->maskedKtp() }}</span>
                                        <span x-show="show" x-cloak>{{ $roster->driver->ktp }}</span>
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3.5 text-slate-600">{{ $roster->registered_at->format('H:i') }}</td>

                            <td class="px-4 py-3.5">
                                @if ($roster->checked_in_at)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        {{ $roster->checked_in_at->format('H:i') }}
                                        @if ($roster->checked_in_by)
                                            <span class="font-normal text-emerald-600">· manual</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Belum
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if (! $roster->checked_in_at)
                                        <button type="button" wire:click="checkInManually({{ $roster->id }})"
                                                wire:confirm="Catat {{ $roster->driver->name }} sebagai hadir (check-in manual)?"
                                                title="Check-in manual"
                                                class="group inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 transition-all duration-150 hover:border-emerald-500 hover:bg-emerald-500 hover:text-white hover:shadow-sm active:scale-95">
                                            <svg class="h-4 w-4 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="remove({{ $roster->id }})"
                                            wire:confirm="Hapus supir dari roster?"
                                            title="Hapus dari roster"
                                            class="group inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-400 transition-all duration-150 hover:border-red-500 hover:bg-red-500 hover:text-white hover:shadow-sm active:scale-95">
                                        <svg class="h-4 w-4 transition-transform group-hover:scale-110 group-hover:rotate-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada supir terdaftar</p>
                                <p class="mt-1 text-xs text-slate-500">Bagikan link registrasi di atas untuk memulai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>