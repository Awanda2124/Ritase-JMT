<?php

use App\Models\WorkSession;
use App\Services\WorkSessionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Sesi kerja')] class extends Component
{
    public function delete(int $sessionId, WorkSessionService $sessions): void
    {
        $session = WorkSession::query()->findOrFail($sessionId);

        try {
            $sessions->delete($session);
            session()->flash('status', 'Sesi dihapus.');
        } catch (\RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }
};
?>

<div>

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
                <span>Operasional</span>
                <span class="text-slate-300">/</span>
                <span class="text-slate-900">Sesi kerja</span>
            </nav>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Sesi kerja</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola semua sesi ritase yang pernah dibuat.</p>
        </div>
        <a href="{{ route('work-sessions.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-slate-800 active:scale-[0.98]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Buat sesi
        </a>
    </div>

    {{-- Flash --}}
    @if (session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    @php
        $sessions = App\Models\WorkSession::query()
            ->withCount('sessionDrivers')
            ->latest('date')
            ->get();
    @endphp

    @if ($sessions->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <p class="mt-4 text-base font-semibold text-slate-900">Belum ada sesi kerja</p>
            <p class="mt-1 text-sm text-slate-500">Buat sesi pertama untuk memulai registrasi supir.</p>
            <a href="{{ route('work-sessions.create') }}"
               class="mt-5 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Buat sesi
            </a>
        </div>
    @else
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));">
            @foreach ($sessions as $session)
                @php
                    $filled = $session->session_drivers_count;
                    $quota = max((int) $session->quota, 1);
                    $percent = min(100, round(($filled / $quota) * 100));
                    $isToday = $session->date->isToday();
                    $isPast = $session->date->isPast() && ! $isToday;
                @endphp
                <div wire:key="session-{{ $session->id }}"
                     class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg">

                    {{-- Date header --}}
                    <div class="flex items-center justify-between border-b border-slate-100 p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl {{ $isToday ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-md shadow-emerald-500/30' : 'bg-slate-900' }} text-white">
                                <span class="text-lg font-bold leading-none">{{ $session->date->format('d') }}</span>
                                <span class="mt-0.5 text-[10px] font-semibold uppercase leading-none {{ $isToday ? 'text-emerald-100' : 'text-slate-400' }}">{{ $session->date->translatedFormat('M') }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $session->date->translatedFormat('d M Y') }}</p>
                                <p class="text-xs text-slate-500">{{ $session->opens_at->format('H:i') }}–{{ $session->closes_at->format('H:i') }}</p>
                            </div>
                        </div>
                        @if ($isToday)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-700">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                Hari ini
                            </span>
                        @elseif ($isPast)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase text-slate-500">Selesai</span>
                        @else
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold uppercase text-blue-700">Akan datang</span>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="flex-1 p-5">
                        <p class="text-base font-semibold text-slate-900">{{ $session->route }}</p>

                        <div class="mt-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs font-medium text-slate-500">Kuota terisi</span>
                                <span class="text-sm font-bold text-slate-900">
                                    {{ $filled }}<span class="font-normal text-slate-400">/{{ $quota }}</span>
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $isToday ? 'bg-gradient-to-r from-emerald-500 to-emerald-600' : 'bg-slate-900' }} transition-all" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 border-t border-slate-100 bg-slate-50/50 p-3">
                        <a href="{{ route('work-sessions.show', $session) }}"
                           class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-900 hover:text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Roster
                        </a>
                        <a href="{{ route('work-sessions.edit', $session) }}"
                           class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm transition-colors hover:bg-slate-900 hover:text-white"
                           title="Ubah">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <button type="button"
                                wire:click="delete({{ $session->id }})"
                                wire:confirm="Hapus sesi ini?"
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm transition-colors hover:bg-red-500 hover:text-white"
                                title="Hapus">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>