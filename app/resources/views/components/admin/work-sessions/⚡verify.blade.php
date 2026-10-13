<?php

use App\Enums\TripReportStatus;
use App\Models\SessionDriver;
use App\Models\TripReport;
use App\Models\WorkSession;
use App\Services\TripVerificationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public WorkSession $workSession;

    #[Url]
    public string $filter = 'semua'; // semua | belum

    /** @var array<int, string> [session_driver_id => keterangan] */
    public array $notes = [];

    /** @var array<int, string> [trip_report_id => sasis baru] */
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

    /** Sasis kembar di sesi ini (hanya info, tidak memblokir). */
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

    // Selalu scoped ke sesi ini, jadi id dari sesi lain tidak bisa diubah.
    private function findReport(int $reportId): TripReport
    {
        return TripReport::query()
            ->where('work_session_id', $this->workSession->id)
            ->findOrFail($reportId);
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-6">
    <a href="{{ route('work-sessions.show', $workSession) }}" class="text-sm text-slate-500 underline">← Kembali ke sesi</a>
    <h1 class="mt-1 text-xl font-semibold">Cek tiket</h1>
    <p class="text-sm text-slate-500">{{ $workSession->route }} · {{ $workSession->date->format('d M Y') }}</p>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <span class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-900">Menunggu {{ $this->summary['menunggu'] }}</span>
        <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-900">Cocok {{ $this->summary['cocok'] }}</span>
        <span class="rounded-full bg-red-100 px-3 py-1 font-medium text-red-900">Tidak cocok {{ $this->summary['tidak_cocok'] }}</span>
        <span class="rounded-full bg-slate-200 px-3 py-1 font-medium text-slate-700">Void {{ $this->summary['void'] }}</span>
    </div>

    <div class="mt-4 flex gap-2 text-sm">
        <button type="button" wire:click="$set('filter', 'semua')"
            class="rounded-md border px-3 py-1 {{ $filter === 'semua' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300' }}">Semua</button>
        <button type="button" wire:click="$set('filter', 'belum')"
            class="rounded-md border px-3 py-1 {{ $filter === 'belum' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-300' }}">Belum dicek</button>
    </div>

    @error('action')
        <p class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @forelse ($this->drivers as $sd)
        @php
            $reports = $this->reportsByDriver[$sd->driver_id];
            $pending = $reports->where('status', \App\Enums\TripReportStatus::Menunggu)->count();
            $active = $reports->where('status', '!=', \App\Enums\TripReportStatus::Void)->count();
        @endphp

        <section wire:key="driver-{{ $sd->id }}" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold">{{ $sd->driver->name }}</h2>
                    <p class="text-xs text-slate-500">{{ $active }} ritase aktif dari {{ $reports->count() }} laporan</p>
                </div>
                @if ($pending > 0)
                    <button type="button" wire:click="approveAll({{ $sd->driver_id }})" wire:loading.attr="disabled"
                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white">Cocok semua ({{ $pending }})</button>
                @endif
            </div>

            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($reports as $r)
                    <li wire:key="report-{{ $r->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2"
                        x-data="{ edit: false }" x-on:chassis-saved.window="edit = false">
                        <div class="flex items-center gap-3">
                            <span x-show="!edit" class="font-mono text-2xl font-bold tracking-widest {{ $r->status === \App\Enums\TripReportStatus::Void ? 'text-slate-400 line-through' : '' }}">{{ $r->chassis_last4 }}</span>

                            <form x-show="edit" x-cloak wire:submit="saveChassis({{ $r->id }})" class="flex items-center gap-1">
                                <input type="text" inputmode="numeric" maxlength="4" wire:model="edits.{{ $r->id }}" placeholder="{{ $r->chassis_last4 }}"
                                    class="w-20 rounded border border-slate-300 px-2 py-1 font-mono">
                                <button class="rounded bg-slate-800 px-2 py-1 text-xs text-white">Simpan</button>
                                <button type="button" x-on:click="edit = false" class="text-xs text-slate-500 underline">Batal</button>
                            </form>

                            <span class="text-xs text-slate-500">{{ $r->reported_at->format('H:i') }}</span>

                            @if ($r->status !== \App\Enums\TripReportStatus::Void && in_array($r->chassis_last4, $this->duplicates, true))
                                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs text-sky-800">Sasis kembar</span>
                            @endif

                            @php
                                $badge = match ($r->status) {
                                    \App\Enums\TripReportStatus::Cocok => 'bg-emerald-100 text-emerald-900',
                                    \App\Enums\TripReportStatus::TidakCocok => 'bg-red-100 text-red-900',
                                    \App\Enums\TripReportStatus::Void => 'bg-slate-200 text-slate-700',
                                    default => 'bg-amber-100 text-amber-900',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ str_replace('_', ' ', $r->status->value) }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-1 text-sm">
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'cocok')" class="rounded border border-emerald-600 px-2 py-1 text-emerald-700">Cocok</button>
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'tidak_cocok')" class="rounded border border-red-600 px-2 py-1 text-red-700">Tidak cocok</button>
                            <button type="button" wire:click="setStatus({{ $r->id }}, 'void')" wire:confirm="Void sasis {{ $r->chassis_last4 }}?" class="rounded border border-slate-400 px-2 py-1 text-slate-600">Void</button>
                            @if ($r->status !== \App\Enums\TripReportStatus::Menunggu)
                                <button type="button" wire:click="setStatus({{ $r->id }}, 'menunggu')" class="px-1 text-xs text-slate-500 underline">Reset</button>
                            @endif
                            @if ($r->status !== \App\Enums\TripReportStatus::Void)
                                <button type="button" x-on:click="edit = true" class="px-1 text-xs text-slate-500 underline">Ubah</button>
                            @endif
                        </div>

                        @error("edits.$r->id")
                            <p class="w-full text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </li>
                @endforeach
            </ul>

            <div class="mt-3 flex gap-2">
                <input type="text" wire:model="notes.{{ $sd->id }}" maxlength="500" placeholder="Keterangan (opsional)"
                    class="w-full rounded border border-slate-300 px-3 py-1.5 text-sm">
                <button type="button" wire:click="saveNote({{ $sd->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Simpan</button>
            </div>
            @error("notes.$sd->id")
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </section>
    @empty
        <p class="mt-6 rounded-md bg-slate-50 p-4 text-sm text-slate-600">
            {{ $filter === 'belum' ? 'Semua laporan sudah dicek.' : 'Belum ada laporan masuk.' }}
        </p>
    @endforelse
</div>