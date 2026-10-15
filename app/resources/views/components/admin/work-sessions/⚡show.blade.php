<?php

use App\Models\SessionDriver;
use App\Models\SessionGroup;
use App\Models\WorkSession;
use App\Services\CheckInService;
use App\Services\DriverRegistrationService;
use App\Support\RoleName;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Roster sesi')] class extends Component
{
    public WorkSession $workSession;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'ci')]
    public string $checkInFilter = 'semua'; // semua | sudah | belum

    #[Url(as: 'grup')]
    public string $groupFilter = 'semua'; // semua | {id}

    public int $visibleCount = 10;

    public int $perPage = 10;

    /** @var array<int, bool> id SessionDriver yang KTP lengkapnya sedang dibuka */
    public array $revealedKtp = [];

    /** Pause auto-refresh saat user lagi search/filter biar gak ganggu input. */
    protected function getPollingInterval(): ?string
    {
        $isFiltering = ! empty($this->search)
            || $this->checkInFilter !== 'semua'
            || $this->groupFilter !== 'semua';

        return $isFiltering ? null : '5s';
    }

    /**
     * Buka/tutup KTP lengkap. Nomor lengkap baru dirender server setelah klik,
     * dan setiap pembukaan dicatat di activity log (tanpa menyimpan nomornya).
     */
    public function toggleKtp(int $sessionDriverId): void
    {
        abort_unless(auth()->user()?->hasRole(RoleName::ADMIN), 403);

        if (isset($this->revealedKtp[$sessionDriverId])) {
            unset($this->revealedKtp[$sessionDriverId]);

            return;
        }

        $sessionDriver = SessionDriver::query()
            ->with('driver:id,name')
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($sessionDriverId);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($sessionDriver->driver)
            ->withProperties(['work_session_id' => $this->workSession->id, 'driver' => $sessionDriver->driver->name])
            ->log('Melihat KTP lengkap');

        $this->revealedKtp[$sessionDriverId] = true;
    }

    public function mount(WorkSession $workSession): void
    {
        $this->workSession = $workSession;
    }

    public function loadMore(): void
    {
        $this->visibleCount += $this->perPage;
    }

    public function updatedSearch(): void
    {
        $this->visibleCount = $this->perPage;
    }

    public function updatedCheckInFilter(): void
    {
        $this->visibleCount = $this->perPage;
    }

    public function updatedGroupFilter(): void
    {
        $this->visibleCount = $this->perPage;
    }

    #[Computed]
    public function groups(): \Illuminate\Support\Collection
    {
        return SessionGroup::query()
            ->where('work_session_id', $this->workSession->id)
            ->withCount('sessionDrivers')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allRoster(): \Illuminate\Support\Collection
    {
        return $this->workSession
            ->sessionDrivers()
            ->with(['driver', 'group'])
            ->oldest('registered_at')
            ->get();
    }

    #[Computed]
    public function filteredRoster(): \Illuminate\Support\Collection
    {
        $needle = mb_strtolower(trim($this->search));
        $checkIn = $this->checkInFilter;
        $group = $this->groupFilter;

        return $this->allRoster
            ->filter(function (SessionDriver $sd) use ($needle, $checkIn, $group) {
                // Filter nama
                if ($needle !== '' && ! str_contains(mb_strtolower($sd->driver->name), $needle)) {
                    return false;
                }

                // Filter check-in
                $checkInOk = match ($checkIn) {
                    'sudah' => $sd->checked_in_at !== null,
                    'belum' => $sd->checked_in_at === null,
                    default => true,
                };
                if (! $checkInOk) {
                    return false;
                }

                // Filter kelompok
                if ($group !== 'semua' && (string) $sd->session_group_id !== (string) $group) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    #[Computed]
    public function visibleRoster(): \Illuminate\Support\Collection
    {
        return $this->filteredRoster->take($this->visibleCount);
    }

    #[Computed]
    public function countSudah(): int
    {
        return $this->allRoster->whereNotNull('checked_in_at')->count();
    }

    #[Computed]
    public function countBelum(): int
    {
        return $this->allRoster->whereNull('checked_in_at')->count();
    }

    public function remove(int $sessionDriverId, DriverRegistrationService $registration): void
    {
        $sessionDriver = SessionDriver::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($sessionDriverId);

        $registration->removeFromRoster($sessionDriver, auth()->user());
        $this->workSession->refresh();
        unset($this->allRoster, $this->filteredRoster, $this->visibleRoster, $this->countSudah, $this->countBelum, $this->groups);
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
        unset($this->allRoster, $this->filteredRoster, $this->visibleRoster, $this->countSudah, $this->countBelum, $this->groups);
        session()->flash('status', 'Check-in manual dicatat.');
    }
};
?>

@php
    $filled = $workSession->sessionDrivers()->count();
    $quota = max((int) $workSession->quota, 1);
    $hadir = $workSession->sessionDrivers()->whereNotNull('checked_in_at')->count();
    $percent = min(100, round(($filled / $quota) * 100));
    $totalFiltered = $this->filteredRoster->count();
    $shown = $this->visibleRoster->count();
    $remaining = max(0, $totalFiltered - $shown);
@endphp

<div style="display: flex; flex-direction: column; gap: 20px;" wire:poll.5s.visible>

    {{-- ===== HEADER ===== --}}
    <div>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <a href="{{ route('work-sessions.index') }}" class="hover:text-slate-900">Sesi Kerja</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">{{ $workSession->date->translatedFormat('d M Y') }}</span>
        </nav>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="flex flex-wrap items-center gap-2 text-2xl font-bold text-slate-900 sm:text-3xl">
                    {{ $workSession->route }}
                    <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #10b981; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 9999px;">
                        <span style="width: 6px; height: 6px; border-radius: 9999px; background: #10b981; animation: pulse 2s infinite;"></span>
                        Live
                    </span>
                </h1>
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

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>

    @if (session('status'))
        <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
        </div>
    @endif

    {{-- ===== STAT CARDS ===== --}}
    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px;">

        {{-- Terdaftar --}}
        <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 10px; min-width: 0; box-shadow: 0 1px 2px rgba(15,23,42,.04);">
            <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #dbeafe; color: #2563eb;">
                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </span>
            <div style="min-width: 0; overflow: hidden;">
                <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Terdaftar</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">
                    {{ $filled }}<span style="margin-left: 4px; font-size: 12px; font-weight: 600; color: #94a3b8;">/ {{ $quota }}</span>
                </p>
            </div>
        </div>

        {{-- Hadir (X/Y = hadir/terdaftar) --}}
        <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 10px; min-width: 0; box-shadow: 0 1px 2px rgba(15,23,42,.04);">
            <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #d1fae5; color: #059669;">
                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>
            <div style="min-width: 0; overflow: hidden;">
                <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Hadir</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">
                    {{ $hadir }}<span style="margin-left: 4px; font-size: 12px; font-weight: 600; color: #94a3b8;">/ {{ $filled }}</span>
                </p>
            </div>
        </div>

        {{-- Batas check-in --}}
        <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 10px; min-width: 0; box-shadow: 0 1px 2px rgba(15,23,42,.04);">
            <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #fef3c7; color: #d97706;">
                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <div style="min-width: 0; overflow: hidden;">
                <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Batas check-in</p>
                <p style="margin: 0; font-size: 13px; font-weight: 700; line-height: 1.2; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    {{ $workSession->checkInDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
                </p>
            </div>
        </div>

        {{-- Batas lapor --}}
        <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 10px; min-width: 0; box-shadow: 0 1px 2px rgba(15,23,42,.04);">
            <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #ede9fe; color: #7c3aed;">
                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </span>
            <div style="min-width: 0; overflow: hidden;">
                <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Batas lapor</p>
                <p style="margin: 0; font-size: 13px; font-weight: 700; line-height: 1.2; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    {{ $workSession->reportDeadline()?->translatedFormat('d M, H:i') ?? '-' }}
                </p>
            </div>
        </div>

    </div>

    {{-- ===== LINK PUBLIK (collapsible) ===== --}}
    <div
        class="rounded-2xl border border-slate-200 bg-white shadow-sm"
        x-data="{
            open: localStorage.getItem('jmt-links-open') !== '0',
            toggle() {
                this.open = !this.open;
                localStorage.setItem('jmt-links-open', this.open ? '1' : '0');
            },
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
        <button type="button" x-on:click="toggle()"
                class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 text-left transition-colors hover:bg-slate-50"
                x-bind:class="!open && 'border-b-0'">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-slate-900">Link publik</h2>
                <p class="text-xs text-slate-500">Bagikan ke supir untuk registrasi dan check-in.</p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="open" x-cloak x-transition.opacity.duration.200ms>
            <div class="space-y-4 p-5">
                @foreach ($this->groups as $group)
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
    </div>

    {{-- ===== ROSTER ===== --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Roster supir</h2>
                <p class="text-xs text-slate-500">Diurutkan dari yang paling awal mendaftar.</p>
            </div>

            {{-- Search --}}
            <div style="position: relative; min-width: 220px;">
                <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8; pointer-events: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama supir..."
                       style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 8px 12px 8px 36px; font-size: 13px; color: #0f172a; outline: none;">
            </div>
        </div>

        {{-- Filter bar: check-in + kelompok (1 baris) --}}
        <div style="padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; justify-content: space-between;">

            {{-- Kiri: filter check-in (segmented) + dropdown kelompok --}}
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">

                {{-- Filter check-in --}}
                <div style="display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #f1f5f9; border-radius: 12px;">
                    @php
                        $ciFilters = [
                            'semua' => ['Semua', $filled],
                            'sudah' => ['Sudah check-in', $this->countSudah],
                            'belum' => ['Belum check-in', $this->countBelum],
                        ];
                    @endphp
                    @foreach ($ciFilters as $key => [$label, $count])
                        <button type="button" wire:click="$set('checkInFilter', '{{ $key }}')"
                                style="display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit;
                                       {{ $checkInFilter === $key ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(15,23,42,.08);' : 'background: transparent; color: #475569;' }}">
                            {{ $label }}
                            <span style="border-radius: 9999px; background: {{ $checkInFilter === $key ? '#e2e8f0' : '#cbd5e1' }}; padding: 1px 6px; font-size: 10px; color: #334155;">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Dropdown kelompok (cuma muncul kalau >1 kelompok) --}}
                @if ($this->groups->count() > 1)
                    <div style="position: relative;">
                        <select wire:model.live="groupFilter"
                                style="display: block; appearance: none; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; padding: 8px 36px 8px 12px; font-size: 12px; font-weight: 600; color: #0f172a; outline: none; cursor: pointer; font-family: inherit;">
                            <option value="semua">Kelompok: Semua ({{ $filled }})</option>
                            @foreach ($this->groups as $group)
                                <option value="{{ $group->id }}">Kelompok: {{ $group->name }} ({{ $group->session_drivers_count }})</option>
                            @endforeach
                        </select>
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: #64748b; pointer-events: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                @endif
            </div>

            {{-- Kanan: counter --}}
            <span style="font-size: 12px; font-weight: 500; color: #64748b;">
                Menampilkan <strong style="color: #0f172a;">{{ $shown }}</strong> dari <strong style="color: #0f172a;">{{ $totalFiltered }}</strong> supir
                @if ($remaining > 0)
                    <span style="color: #94a3b8;">&middot; {{ $remaining }} belum tampil</span>
                @endif
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50/50">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">KTP</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Daftar</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Check-in</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->visibleRoster as $i => $roster)
                        <tr wire:key="roster-{{ $roster->id }}" class="transition-colors hover:bg-slate-50/70">

                            <td class="px-5 py-3.5 text-xs font-semibold text-slate-400">
                                {{ $i + 1 }}
                            </td>

                            <td class="px-4 py-3.5">
                                <span class="font-semibold text-slate-900">{{ $roster->driver->name }}</span>
                            </td>

                            <td class="px-4 py-3.5">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $roster->group?->name ?? '-' }}
                                </span>
                            </td>

                            <td class="px-4 py-3.5">
                                <div class="inline-flex items-center gap-2 font-mono text-xs text-slate-500">
                                    @if (isset($revealedKtp[$roster->id]))
                                        <span>{{ $roster->driver->ktp }}</span>
                                    @else
                                        <span>{{ $roster->driver->maskedKtp() }}</span>
                                    @endif
                                    <button type="button" wire:click="toggleKtp({{ $roster->id }})" wire:loading.attr="disabled" wire:target="toggleKtp({{ $roster->id }})"
                                            @if (isset($revealedKtp[$roster->id])) title="Sembunyikan KTP" aria-label="Sembunyikan KTP" @else title="Lihat KTP lengkap" aria-label="Lihat KTP lengkap" @endif
                                            class="flex h-6 w-6 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50">
                                        @if (isset($revealedKtp[$roster->id]))
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        @endif
                                    </button>
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
                            <td colspan="7" class="px-5 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                                    <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-slate-700">
                                    @if ($search !== '')
                                        Tidak ada supir yang cocok dengan "{{ $search }}"
                                    @elseif ($checkInFilter === 'sudah')
                                        Belum ada supir yang check-in
                                    @elseif ($checkInFilter === 'belum')
                                        Semua supir sudah check-in
                                    @elseif ($groupFilter !== 'semua')
                                        Belum ada supir di kelompok ini
                                    @else
                                        Belum ada supir terdaftar
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    @if ($search === '' && $checkInFilter === 'semua' && $groupFilter === 'semua')
                                        Bagikan link registrasi di atas untuk memulai.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Load More --}}
        @if ($remaining > 0)
            <div class="border-t border-slate-100 bg-slate-50/50 p-4 text-center">
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-900 hover:bg-slate-50 disabled:opacity-60">
                    <svg wire:loading.remove wire:target="loadMore" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    <svg wire:loading wire:target="loadMore" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                    </svg>
                    Muat {{ min($perPage, $remaining) }} supir lagi
                    <span class="text-slate-400">({{ $remaining }} tersisa)</span>
                </button>
            </div>
        @endif
    </div>

</div>