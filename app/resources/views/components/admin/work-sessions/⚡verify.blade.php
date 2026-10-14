<?php

use App\Enums\TripReportStatus;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\TripVerificationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public WorkSession $workSession;

    #[Url]
    public string $filter = 'semua';

    /** @var array<int, string> */
    public array $notes = [];

    /** @var array<int, string> */
    public array $edits = [];

    public function mount(WorkSession $workSession): void
    {
        $this->workSession = $workSession;

        foreach (SessionDriver::query()->where('work_session_id', $workSession->id)->get() as $row) {
            $this->notes[$row->id] = (string) $row->note;
        }
    }

    #[Computed]
    public function reportsByDriver(): Collection
    {
        return TripReport::query()
            ->where('work_session_id', $this->workSession->id)
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get()
            ->groupBy('driver_id');
    }

    #[Computed]
    public function drivers(): Collection
    {
        $reports = $this->reportsByDriver;

        return SessionDriver::query()
            ->with('driver')
            ->where('work_session_id', $this->workSession->id)
            ->whereIn('driver_id', $reports->keys())
            ->get()
            ->filter(function (SessionDriver $sd) use ($reports) {
                if ($this->filter !== 'belum') {
                    return true;
                }

                return $reports[$sd->driver_id]->contains('status', TripReportStatus::Menunggu);
            })
            ->sortBy(fn (SessionDriver $sd) => $sd->driver->name)
            ->values();
    }

    /** @return array<string, int> */
    #[Computed]
    public function summary(): array
    {
        $all = $this->reportsByDriver->flatten();

        return [
            'menunggu' => $all->where('status', TripReportStatus::Menunggu)->count(),
            'cocok' => $all->where('status', TripReportStatus::Cocok)->count(),
            'tidak_cocok' => $all->where('status', TripReportStatus::TidakCocok)->count(),
            'void' => $all->where('status', TripReportStatus::Void)->count(),
        ];
    }

    #[Computed]
    public function duplicates(): array
    {
        return $this->reportsByDriver->flatten()
            ->where('status', '!=', TripReportStatus::Void)
            ->countBy('chassis_last4')
            ->filter(fn (int $n) => $n > 1)
            ->keys()
            ->all();
    }

    public function setStatus(int $reportId, string $status): void
    {
        $enum = TripReportStatus::tryFrom($status);
        abort_if($enum === null, 422);

        try {
            app(TripVerificationService::class)->setStatus($this->findReport($reportId), $enum, auth()->user());
            $this->resetErrorBag('action');
        } catch (\DomainException $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function approveAll(int $driverId): void
    {
        app(TripVerificationService::class)->approveAllPending($this->workSession, $driverId, auth()->user());
    }

    public function saveChassis(int $reportId): void
    {
        $this->validate(
            ["edits.$reportId" => ['required', 'digits:4']],
            [],
            ["edits.$reportId" => 'Nomor sasis'],
        );

        try {
            app(TripVerificationService::class)->correctChassis(
                $this->findReport($reportId),
                $this->edits[$reportId],
                auth()->user(),
            );
        } catch (\DomainException $e) {
            $this->addError("edits.$reportId", $e->getMessage());

            return;
        }

        unset($this->edits[$reportId]);
        $this->dispatch('chassis-saved');
    }

    public function saveNote(int $sessionDriverId): void
    {
        $this->validate(
            ["notes.$sessionDriverId" => ['nullable', 'string', 'max:500']],
        );

        $sessionDriver = SessionDriver::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($sessionDriverId);

        app(TripVerificationService::class)->saveNote($sessionDriver, $this->notes[$sessionDriverId] ?? null, auth()->user());
    }

    private function findReport(int $reportId): TripReport
    {
        return TripReport::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($reportId);
    }
};
?>

<div>

    {{-- Header --}}
    <div class="mb-6">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <a href="{{ route('work-sessions.index') }}" class="hover:text-slate-900">Sesi kerja</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('work-sessions.show', $workSession) }}" class="hover:text-slate-900">{{ $workSession->date->translatedFormat('d M Y') }}</a>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">Cek tiket</span>
        </nav>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Cek tiket</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $workSession->route }} · {{ $workSession->date->translatedFormat('d M Y') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('work-sessions.export', $workSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Excel
                </a>
                <a href="{{ route('work-sessions.show', $workSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali
                </a>
            </div>
        </div>
    </div>

    @if ($this->summary['menunggu'] > 0)
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
            <p class="text-sm font-medium text-amber-800">
                {{ $this->summary['menunggu'] }} laporan belum dicek — tidak ikut terhitung di Excel.
            </p>
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Menunggu</p>
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $this->summary['menunggu'] }}</p>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Cocok</p>
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $this->summary['cocok'] }}</p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-red-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </span>
                <p class="text-xs font-semibold uppercase tracking-wider text-red-700">Tidak cocok</p>
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $this->summary['tidak_cocok'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </span>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-600">Void</p>
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $this->summary['void'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mt-4 flex gap-2">
        <button type="button" wire:click="$set('filter', 'semua')"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition-all {{ $filter === 'semua' ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            Semua
        </button>
        <button type="button" wire:click="$set('filter', 'belum')"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition-all {{ $filter === 'belum' ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            Belum dicek
        </button>
    </div>

    @error('action')
        <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{{ $message }}</p>
        </div>
    @enderror

    {{-- List per driver --}}
    @forelse ($this->drivers as $sd)
        @php
            $reports = $this->reportsByDriver[$sd->driver_id];
            $pending = $reports->where('status', \App\Enums\TripReportStatus::Menunggu)->count();
            $active = $reports->where('status', '!=', \App\Enums\TripReportStatus::Void)->count();
        @endphp

        <section wire:key="driver-{{ $sd->id }}" class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

            {{-- Driver header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">{{ $sd->driver->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $active }} ritase aktif dari {{ $reports->count() }} laporan</p>
                </div>
                @if ($pending > 0)
                    <button type="button" wire:click="approveAll({{ $sd->driver_id }})" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-emerald-700 disabled:opacity-70">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Cocok semua ({{ $pending }})
                    </button>
                @endif
            </div>

            {{-- Report list --}}
            <ul class="divide-y divide-slate-100">
                @foreach ($reports as $r)
                    @php
                        $badge = match ($r->status) {
                            \App\Enums\TripReportStatus::Cocok => 'bg-emerald-100 text-emerald-700',
                            \App\Enums\TripReportStatus::TidakCocok => 'bg-red-100 text-red-700',
                            \App\Enums\TripReportStatus::Void => 'bg-slate-200 text-slate-600',
                            default => 'bg-amber-100 text-amber-700',
                        };
                    @endphp
                    <li wire:key="report-{{ $r->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                        x-data="{ edit: false }" x-on:chassis-saved.window="edit = false">

                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Sasis --}}
                            <span x-show="!edit"
                                  class="inline-flex h-12 w-16 items-center justify-center rounded-lg bg-slate-900 font-mono text-lg font-bold tracking-widest text-white {{ $r->status === \App\Enums\TripReportStatus::Void ? 'opacity-40 line-through' : '' }}">
                                {{ $r->chassis_last4 }}
                            </span>

                            <form x-show="edit" x-cloak wire:submit="saveChassis({{ $r->id }})" class="flex items-center gap-1">
                                <input type="text" inputmode="numeric" maxlength="4" wire:model="edits.{{ $r->id }}" placeholder="{{ $r->chassis_last4 }}"
                                       class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-center font-mono text-sm">
                                <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Simpan</button>
                                <button type="button" x-on:click="edit = false" class="text-xs text-slate-500 underline">Batal</button>
                            </form>

                            <div class="flex flex-col">
                                <span class="text-xs font-medium text-slate-500">{{ $r->reported_at->format('H:i') }}</span>
                                @if ($r->status !== \App\Enums\TripReportStatus::Void && in_array($r->chassis_last4, $this->duplicates, true))
                                    <span class="mt-0.5 inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">Sasis kembar</span>
                                @endif
                            </div>

                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge }}">
                                {{ str_replace('_', ' ', $r->status->value) }}
                            </span>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-wrap items-center gap-1">
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'cocok')"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-500 hover:text-white hover:border-emerald-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Cocok
                            </button>
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'tidak_cocok')"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-red-200 bg-white px-2.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-500 hover:text-white hover:border-red-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Tidak cocok
                            </button>
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'void')" wire:confirm="Void sasis {{ $r->chassis_last4 }}?"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-900 hover:text-white hover:border-slate-900">
                                Void
                            </button>
                            @if ($r->status !== \App\Enums\TripReportStatus::Menunggu)
                                <button type="button" wire:click="setStatus({{ $r->id }}, 'menunggu')"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-900" title="Reset">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            @endif
                            @if ($r->status !== \App\Enums\TripReportStatus::Void)
                                <button type="button" x-on:click="edit = true"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-slate-900" title="Ubah sasis">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        @error("edits.$r->id")
                            <p class="w-full text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </li>
                @endforeach
            </ul>

            {{-- Note --}}
            <div class="border-t border-slate-100 bg-slate-50/50 p-3">
                <div class="flex gap-2">
                    <input type="text" wire:model="notes.{{ $sd->id }}" maxlength="500" placeholder="Keterangan (opsional)"
                           class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    <button type="button" wire:click="saveNote({{ $sd->id }})"
                            class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        Simpan
                    </button>
                </div>
                @error("notes.$sd->id")
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>
    @empty
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <p class="mt-3 text-sm font-semibold text-slate-700">
                {{ $filter === 'belum' ? 'Semua laporan sudah dicek' : 'Belum ada laporan masuk' }}
            </p>
        </div>
    @endforelse

</div>