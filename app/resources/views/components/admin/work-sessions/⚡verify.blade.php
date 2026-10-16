<?php

use App\Enums\TripReportStatus;
use App\Models\Receipt;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\ReceiptService;
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

    #[Url(as: 'q')]
    public string $search = '';

    /** Berapa supir yang ditampilkan (Load More). */
    public int $visibleCount = 10;

    /** Step tiap klik "Muat lagi". */
    public int $perPage = 10;

    /** @var array<int, string> */
    public array $notes = [];

    /** @var array<int, string> */
    public array $edits = [];

    /** @var array<int, string> alasan batal struk, key = receipt id */
    public array $voidReasons = [];

    public function mount(WorkSession $workSession): void
    {
        $this->workSession = $workSession;

        foreach (SessionDriver::query()->where('work_session_id', $workSession->id)->get() as $row) {
            $this->notes[$row->id] = (string) $row->note;
        }
    }

    public function loadMore(): void
    {
        $this->visibleCount += $this->perPage;
    }

    public function updatedFilter(): void
    {
        $this->visibleCount = $this->perPage;
    }

    public function updatedSearch(): void
    {
        $this->visibleCount = $this->perPage;
    }

    #[Computed]
    public function reportsByDriver(): Collection
    {
        return TripReport::query()
            ->select(['id', 'work_session_id', 'driver_id', 'chassis_last4', 'status', 'reported_at'])
            ->where('work_session_id', $this->workSession->id)
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get()
            ->groupBy('driver_id');
    }

    #[Computed]
    public function roster(): Collection
    {
        return SessionDriver::query()
            ->with('driver')
            ->where('work_session_id', $this->workSession->id)
            ->whereIn('driver_id', $this->reportsByDriver->keys())
            ->get();
    }

    #[Computed]
    public function drivers(): Collection
    {
        $needle = mb_strtolower(trim($this->search));

        return $this->roster
            ->filter(function (SessionDriver $sd) use ($needle) {
                if ($needle !== '' && ! str_contains(mb_strtolower($sd->driver->name), $needle)) {
                    return false;
                }

                $reports = $this->reportsByDriver[$sd->driver_id];

                return match ($this->filter) {
                    'belum' => $reports->contains('status', TripReportStatus::Menunggu),
                    'tidak_cocok' => $reports->contains('status', TripReportStatus::TidakCocok),
                    'kembar' => $reports->contains(
                        fn (TripReport $r) => $r->status !== TripReportStatus::Void
                            && in_array($r->chassis_last4, $this->duplicates, true)
                    ),
                    default => true,
                };
            })
            ->sortByDesc(fn (SessionDriver $sd) => $this->reportsByDriver[$sd->driver_id]->max('reported_at'))
            ->values();
    }

    #[Computed]
    public function visibleDrivers(): Collection
    {
        return $this->drivers->take($this->visibleCount);
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
            ->map(fn ($chassis) => (string) $chassis)
            ->all();
    }

    #[Computed]
    public function duplicateGroups(): array
    {
        $names = $this->roster->mapWithKeys(fn (SessionDriver $sd) => [$sd->driver_id => $sd->driver->name]);

        return $this->reportsByDriver->flatten()
            ->where('status', '!=', TripReportStatus::Void)
            ->filter(fn (TripReport $r) => in_array($r->chassis_last4, $this->duplicates, true))
            ->groupBy('chassis_last4')
            ->map(fn (Collection $group) => $group->groupBy('driver_id')
                ->map(fn (Collection $rows, $driverId) => ($names[$driverId] ?? '?').($rows->count() > 1 ? ' x'.$rows->count() : ''))
                ->values()
                ->all())
            ->sortKeys()
            ->all();
    }

    /** Struk aktif per supir (key = driver_id). */
    #[Computed]
    public function receipts(): Collection
    {
        return Receipt::query()
            ->active()
            ->where('work_session_id', $this->workSession->id)
            ->get()
            ->keyBy('driver_id');
    }

    public function issueReceipt(int $driverId): void
    {
        try {
            $receipt = app(ReceiptService::class)->issue($this->workSession, $driverId, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('action', $e->getMessage());

            return;
        }

        $this->resetErrorBag('action');
        unset($this->receipts);

        $this->redirect(route('receipts.print', [$this->workSession, $receipt]));
    }

    public function voidReceipt(int $receiptId): void
    {
        $receipt = Receipt::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($receiptId);

        try {
            app(ReceiptService::class)->void($receipt, (string) ($this->voidReasons[$receiptId] ?? ''), auth()->user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->addError("voidReasons.$receiptId", $e->getMessage());

            return;
        }

        unset($this->voidReasons[$receiptId], $this->receipts);
        $this->resetErrorBag();
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
        $reports = $this->reportsByDriver->get($driverId, collect());

        $blocked = $reports->contains(
            fn (TripReport $r) => $r->status === TripReportStatus::Menunggu
                && in_array($r->chassis_last4, $this->duplicates, true)
        );

        if ($blocked) {
            $this->addError('action', 'Ada sasis kembar di laporan yang menunggu. Cek satu per satu.');

            return;
        }

        try {
            app(TripVerificationService::class)->approveAllPending($this->workSession, $driverId, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('action', $e->getMessage());

            return;
        }

        $this->resetErrorBag('action');

        unset($this->reportsByDriver, $this->duplicates);
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

<div class="space-y-5" wire:poll.10s.visible>

    {{-- ===== HEADER ===== --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
                <a href="{{ route('work-sessions.index') }}" class="hover:text-slate-900">Sesi kerja</a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('work-sessions.show', $workSession) }}" class="hover:text-slate-900">{{ $workSession->date->translatedFormat('d M Y') }}</a>
                <span class="text-slate-300">/</span>
                <span class="text-slate-900">Cek tiket</span>
            </nav>
            <h1 class="mt-2 flex items-center gap-2 text-2xl font-bold text-slate-900">
                Cek tiket
                @if ($workSession->isLive())
                    <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #10b981; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 9999px;">
                        <span style="width: 6px; height: 6px; border-radius: 9999px; background: #10b981; animation: pulse 2s infinite;"></span>
                        Live
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 9999px;">Selesai</span>
                @endif
                <style>
                    @keyframes pulse {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.4; }
                    }
                </style>
            </h1>
            <p class="mt-1 text-sm text-slate-500">{{ $workSession->route }} &middot; {{ $workSession->date->translatedFormat('d M Y') }}</p>
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

    @if ($this->summary['menunggu'] > 0)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
            <p class="text-sm font-medium text-amber-800">
                {{ $this->summary['menunggu'] }} laporan belum dicek &mdash; tidak ikut terhitung di Excel.
            </p>
        </div>
    @endif

    @if (count($this->duplicateGroups) > 0)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-sm font-semibold text-sky-900">
                Sasis kembar ({{ count($this->duplicateGroups) }} nomor) &mdash; cocokkan dengan tiket fisik
            </p>
            <ul class="mt-2 grid gap-x-6 gap-y-1 text-sm text-sky-800 sm:grid-cols-2">
                @foreach ($this->duplicateGroups as $chassis => $holders)
                    <li><span class="font-mono font-bold">{{ $chassis }}</span>: {{ implode(', ', $holders) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $filters = [
            'semua' => ['Semua', null],
            'belum' => ['Belum dicek', $this->summary['menunggu']],
            'tidak_cocok' => ['Tidak cocok', $this->summary['tidak_cocok']],
            'kembar' => ['Sasis kembar', count($this->duplicates)],
        ];
        $totalFiltered = $this->drivers->count();
        $shown = $this->visibleDrivers->count();
        $remaining = max(0, $totalFiltered - $shown);
    @endphp
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- ===== SUMMARY CARDS (5 kolom) ===== --}}
        <div style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; padding: 12px; border-bottom: 1px solid #f1f5f9;">

            {{-- Total --}}
            <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 10px; padding: 8px 10px; min-width: 0;">
                <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #e2e8f0; color: #334155;">
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <div style="min-width: 0; overflow: hidden;">
                    <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Total</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">{{ array_sum($this->summary) }}</p>
                </div>
            </div>

            {{-- Menunggu --}}
            <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 10px; padding: 8px 10px; min-width: 0;">
                <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #fef3c7; color: #d97706;">
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div style="min-width: 0; overflow: hidden;">
                    <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #b45309; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Menunggu</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">{{ $this->summary['menunggu'] }}</p>
                </div>
            </div>

            {{-- Cocok --}}
            <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 10px; padding: 8px 10px; min-width: 0;">
                <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #d1fae5; color: #059669;">
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <div style="min-width: 0; overflow: hidden;">
                    <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #047857; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Cocok</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">{{ $this->summary['cocok'] }}</p>
                </div>
            </div>

            {{-- Tidak cocok --}}
            <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 8px 10px; min-width: 0;">
                <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #fee2e2; color: #dc2626;">
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </span>
                <div style="min-width: 0; overflow: hidden;">
                    <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #b91c1c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Tidak cocok</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">{{ $this->summary['tidak_cocok'] }}</p>
                </div>
            </div>

            {{-- Void --}}
            <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 10px; padding: 8px 10px; min-width: 0;">
                <span style="display: flex; width: 28px; height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 8px; background: #e2e8f0; color: #475569;">
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </span>
                <div style="min-width: 0; overflow: hidden;">
                    <p style="margin: 0; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Void</p>
                    <p style="margin: 0; font-size: 16px; font-weight: 700; line-height: 1.1; color: #0f172a;">{{ $this->summary['void'] }}</p>
                </div>
            </div>

        </div>

        {{-- ===== FILTER + SEARCH ===== --}}
        <div style="padding: 16px; display: flex; flex-direction: column; gap: 12px;">

            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">

                <div style="display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; background: #f1f5f9; border-radius: 12px;">
                    @foreach ($filters as $key => [$label, $count])
                        <button type="button" wire:click="$set('filter', '{{ $key }}')"
                                style="display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit;
                                       {{ $filter === $key ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(15,23,42,.08);' : 'background: transparent; color: #475569;' }}">
                            {{ $label }}
                            @if ($count !== null && $count > 0)
                                <span style="border-radius: 9999px; background: {{ $filter === $key ? '#e2e8f0' : '#cbd5e1' }}; padding: 1px 6px; font-size: 10px; color: #334155;">{{ $count }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div style="position: relative; flex: 1; min-width: 200px;">
                    <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94a3b8; pointer-events: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama supir..."
                           style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 8px 12px 8px 36px; font-size: 13px; color: #0f172a; outline: none;">
                </div>
            </div>

            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;" x-data>
                <span style="font-size: 12px; font-weight: 500; color: #64748b;">
                    Menampilkan <strong style="color: #0f172a;">{{ $shown }}</strong>
                    dari <strong style="color: #0f172a;">{{ $totalFiltered }}</strong> supir
                    @if ($remaining > 0)
                        <span style="color: #94a3b8;">&middot; {{ $remaining }} belum tampil</span>
                    @endif
                </span>
                @if ($shown > 1)
                    <div style="display: flex; gap: 8px;">
                        <button type="button" x-on:click="$dispatch('toggle-all', true)"
                                style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; font-family: inherit;">
                            <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                            Buka semua
                        </button>
                        <button type="button" x-on:click="$dispatch('toggle-all', false)"
                                style="display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; font-family: inherit;">
                            <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                            Tutup semua
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @error('action')
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">{{ $message }}</p>
        </div>
    @enderror

    @forelse ($this->visibleDrivers as $sd)
        @php
            $reports = $this->reportsByDriver[$sd->driver_id];
            $dups = $this->duplicates;
            $by = $reports->countBy(fn ($x) => $x->status->value);
            $menunggu = $by->get('menunggu', 0);
            $tidakCocok = $by->get('tidak_cocok', 0);
            $active = $reports->where('status', '!=', \App\Enums\TripReportStatus::Void)->count();
            $hasDup = $reports->contains(fn ($x) => $x->status !== \App\Enums\TripReportStatus::Void && in_array($x->chassis_last4, $dups, true));
            $dupPending = $reports->contains(fn ($x) => $x->status === \App\Enums\TripReportStatus::Menunggu && in_array($x->chassis_last4, $dups, true));
            $needsAttention = $menunggu > 0 || $tidakCocok > 0 || $hasDup;
            $cocokCount = $by->get('cocok', 0);
            $receipt = $this->receipts->get($sd->driver_id);
            $initials = collect(explode(' ', trim($sd->driver->name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
        @endphp

        <section wire:key="driver-{{ $sd->id }}"
                 x-data="{ open: {{ $needsAttention ? 'true' : 'false' }} }"
                 x-on:toggle-all.window="open = $event.detail"
                 class="overflow-hidden rounded-2xl border bg-white shadow-sm {{ $needsAttention ? 'border-amber-200' : 'border-slate-200' }}">

            <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-label="Buka atau tutup {{ $sd->driver->name }}"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700">
                    <svg class="h-4 w-4 transition-transform" x-bind:class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white">
                    {{ mb_strtoupper($initials) }}
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-900">{{ $sd->driver->name }}</p>
                    <p class="text-xs text-slate-500">{{ $active }} ritase aktif &middot; {{ $reports->count() }} laporan</p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($menunggu > 0)
                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-700">{{ $menunggu }} menunggu</span>
                    @endif
                    @if ($tidakCocok > 0)
                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-700">{{ $tidakCocok }} tidak cocok</span>
                    @endif
                    @if ($hasDup)
                        <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-bold text-sky-700">sasis kembar</span>
                    @endif
                    @if (! $needsAttention)
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">&#10003; Selesai</span>
                    @endif
                </div>

                @if ($menunggu > 0)
                    @if ($dupPending)
                        <span class="text-[11px] font-medium text-sky-700">Ada sasis kembar, cek satu per satu</span>
                    @else
                        <button type="button" wire:click="approveAll({{ $sd->driver_id }})"
                                wire:confirm="Tandai {{ $menunggu }} laporan {{ $sd->driver->name }} sebagai Cocok?"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-700 disabled:opacity-60">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Cocok semua ({{ $menunggu }})
                        </button>
                    @endif
                @endif
            </div>

            @if ($receipt)
                <div x-data="{ voiding: false }" class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-emerald-100 bg-emerald-50/60 px-4 py-2.5">
                    <p class="min-w-0 flex-1 text-xs text-emerald-900">
                        Struk <span class="font-mono font-bold">{{ $receipt->receipt_no }}</span>
                        &middot; dicetak {{ $receipt->print_count }}x &middot; laporan terkunci
                    </p>
                    <a href="{{ route('receipts.print', [$workSession, $receipt]) }}" target="_blank"
                       class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                        Lihat / cetak ulang
                    </a>
                    <button type="button" x-on:click="voiding = !voiding"
                            class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                        Batalkan struk
                    </button>
                    <form x-show="voiding" x-cloak wire:submit="voidReceipt({{ $receipt->id }})" class="flex w-full gap-2">
                        <input type="text" wire:model="voidReasons.{{ $receipt->id }}" maxlength="255" placeholder="Alasan batal (min. 5 huruf)"
                               class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm">
                        <button class="shrink-0 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Batalkan</button>
                    </form>
                    @error("voidReasons.$receipt->id")
                        <p class="w-full text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @elseif ($menunggu === 0 && $cocokCount > 0)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-2.5">
                    <p class="text-xs text-slate-600">Semua laporan sudah dicek ({{ $cocokCount }} cocok). Siap dibuatkan struk.</p>
                    <button type="button" wire:click="issueReceipt({{ $sd->driver_id }})"
                            wire:confirm="Terbitkan struk {{ $sd->driver->name }}? Setelah terbit, laporan supir ini terkunci."
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                        Terbitkan struk
                    </button>
                </div>
            @endif

            <div x-show="open" x-cloak class="border-t border-slate-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/50">
                            <tr>
                                <th class="px-4 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Sasis</th>
                                <th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Waktu</th>
                                <th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-2 text-right text-[10px] font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reports as $r)
                                @php
                                    $isVoid = $r->status === \App\Enums\TripReportStatus::Void;
                                    $badge = match ($r->status) {
                                        \App\Enums\TripReportStatus::Cocok => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        \App\Enums\TripReportStatus::TidakCocok => 'bg-red-50 text-red-700 border-red-200',
                                        \App\Enums\TripReportStatus::Void => 'bg-slate-100 text-slate-600 border-slate-200',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200',
                                    };
                                @endphp
                                <tr wire:key="report-{{ $r->id }}" x-data="{ edit: false }" x-on:chassis-saved.window="edit = false"
                                    class="transition-colors hover:bg-slate-50/70">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span x-show="!edit"
                                                  class="inline-flex h-8 min-w-[52px] items-center justify-center rounded-md bg-slate-900 px-2 font-mono text-sm font-bold tracking-widest text-white {{ $isVoid ? 'line-through opacity-40' : '' }}">
                                                {{ $r->chassis_last4 }}
                                            </span>
                                            <form x-show="edit" x-cloak wire:submit="saveChassis({{ $r->id }})" class="flex items-center gap-1">
                                                <input type="text" inputmode="numeric" maxlength="4" wire:model="edits.{{ $r->id }}" placeholder="{{ $r->chassis_last4 }}"
                                                       class="w-16 rounded-md border border-slate-300 px-2 py-1 text-center font-mono text-sm">
                                                <button class="rounded-md bg-slate-800 px-2 py-1 text-[11px] font-semibold text-white">OK</button>
                                                <button type="button" x-on:click="edit = false" class="text-[11px] text-slate-500 underline">Batal</button>
                                            </form>

                                            @if (! $isVoid && in_array($r->chassis_last4, $dups, true))
                                                <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">Kembar</span>
                                            @endif
                                        </div>
                                        @error("edits.$r->id")
                                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                        @enderror
                                    </td>

                                    <td class="px-3 py-2.5 text-xs text-slate-500">{{ $r->reported_at->format('H:i') }}</td>

                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badge }}">
                                            {{ str_replace('_', ' ', $r->status->value) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="setStatus({{ $r->id }}, 'cocok')" title="Cocok" aria-label="Tandai cocok"
                                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-emerald-200 bg-white text-emerald-600 transition-colors hover:border-emerald-500 hover:bg-emerald-500 hover:text-white {{ $r->status === \App\Enums\TripReportStatus::Cocok ? 'ring-2 ring-emerald-400' : '' }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            <button type="button" wire:click="setStatus({{ $r->id }}, 'tidak_cocok')" title="Tidak cocok" aria-label="Tandai tidak cocok"
                                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-white text-red-600 transition-colors hover:border-red-500 hover:bg-red-500 hover:text-white {{ $r->status === \App\Enums\TripReportStatus::TidakCocok ? 'ring-2 ring-red-400' : '' }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <button type="button" wire:click="setStatus({{ $r->id }}, 'void')" wire:confirm="Void sasis {{ $r->chassis_last4 }}?" title="Void" aria-label="Void sasis {{ $r->chassis_last4 }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:border-slate-700 hover:bg-slate-700 hover:text-white {{ $isVoid ? 'ring-2 ring-slate-400' : '' }}">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>

                                            @if ($r->status !== \App\Enums\TripReportStatus::Menunggu)
                                                <button type="button" wire:click="setStatus({{ $r->id }}, 'menunggu')" title="Reset ke menunggu" aria-label="Reset ke menunggu"
                                                        class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-100">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                            @endif

                                            @if (! $isVoid)
                                                <button type="button" x-on:click="edit = true" title="Ubah sasis" aria-label="Ubah sasis"
                                                        class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-100">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

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
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-slate-700">
                @if ($filter === 'belum' && $search === '')
                    Semua laporan sudah dicek
                @elseif ($this->reportsByDriver->isEmpty())
                    Belum ada laporan masuk
                @else
                    Tidak ada supir yang cocok dengan filter atau pencarian
                @endif
            </p>
        </div>
    @endforelse

    @if ($remaining > 0)
        <div class="flex justify-center pt-2">
            <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-900 hover:bg-slate-50 disabled:opacity-60">
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