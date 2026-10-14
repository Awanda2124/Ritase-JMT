<?php

use App\Http\Requests\StoreWorkSessionRequest;
use App\Models\WorkSession;
use App\Services\WorkSessionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $workSessionId = null;

    public string $date = '';

    public string $route = WorkSessionService::DEFAULT_ROUTE;

    public string $open_time = '06:00';

    public string $close_time = '18:00';

    public string $checkin_closes_at = '';

    public string $report_closes_at = '';

    /** @var array<int, array{id: ?int, name: string, quota: int|string}> */
    public array $groups = [
        ['id' => null, 'name' => 'Yayasan', 'quota' => 30],
        ['id' => null, 'name' => 'Akamsi', 'quota' => 20],
    ];

    public function mount(?WorkSession $workSession = null): void
    {
        if ($workSession === null || ! $workSession->exists) {
            $this->date = now()->toDateString();

            return;
        }

        $this->workSessionId = $workSession->id;
        $this->date = $workSession->date->toDateString();
        $this->route = $workSession->route;
        $this->open_time = $workSession->opens_at->format('H:i');
        $this->close_time = $workSession->closes_at->format('H:i');
        $this->checkin_closes_at = $workSession->checkin_closes_at?->format('Y-m-d\TH:i') ?? '';
        $this->report_closes_at = $workSession->report_closes_at?->format('Y-m-d\TH:i') ?? '';
        $this->groups = $workSession->groups()->get()
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'quota' => $g->quota])
            ->all();
    }

    public function updatedDate(): void
    {
        $this->checkin_closes_at = '';
        $this->report_closes_at = '';
    }

    public function addGroup(): void
    {
        $this->groups[] = ['id' => null, 'name' => '', 'quota' => 10];
    }

    public function removeGroup(int $index): void
    {
        unset($this->groups[$index]);
        $this->groups = array_values($this->groups);
    }

    public function save(WorkSessionService $sessions): void
    {
        $form = new StoreWorkSessionRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        if ($this->workSessionId) {
            $session = WorkSession::query()->findOrFail($this->workSessionId);
            $sessions->update($session, $validated);
            session()->flash('status', 'Sesi diperbarui.');
            $this->redirect(route('work-sessions.show', $session), navigate: true);

            return;
        }

        $session = $sessions->create($validated, auth()->user());
        session()->flash('status', 'Sesi dibuat.');
        $this->redirect(route('work-sessions.show', $session), navigate: true);
    }

    public function getTitleProperty(): string
    {
        return $this->workSessionId ? 'Ubah sesi' : 'Buat sesi';
    }
};
?>

<div class="mx-auto max-w-5xl">

    {{-- ===== HEADER ===== --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('work-sessions.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition-colors hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Sesi kerja
            </a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $this->workSessionId ? 'Ubah sesi' : 'Buat sesi baru' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Konfigurasi jadwal, kuota, dan kelompok supir.</p>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- ===== KOLOM KIRI: FORM (2/3) ===== --}}
            <div class="space-y-5 lg:col-span-2">

                {{-- SECTION 1: INFO DASAR --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Info dasar</h2>
                                <p class="text-xs text-slate-500">Tanggal dan rute sesi kerja.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="date" class="block text-sm font-medium text-slate-700">Tanggal <span class="text-red-500">*</span></label>
                                <input
                                    id="date"
                                    type="date"
                                    wire:model.live="date"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                @error('date') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="route" class="block text-sm font-medium text-slate-700">Rute <span class="text-red-500">*</span></label>
                                <input
                                    id="route"
                                    type="text"
                                    wire:model="route"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                @error('route') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 2: WAKTU REGISTRASI --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Waktu registrasi</h2>
                                <p class="text-xs text-slate-500">Periode supir bisa mendaftar lewat link registrasi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="open_time" class="block text-sm font-medium text-slate-700">Buka</label>
                                <input
                                    id="open_time"
                                    type="time"
                                    wire:model="open_time"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                @error('open_time') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="close_time" class="block text-sm font-medium text-slate-700">Tutup</label>
                                <input
                                    id="close_time"
                                    type="time"
                                    wire:model="close_time"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                @error('close_time') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 3: BATAS WAKTU --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Batas waktu</h2>
                                <p class="text-xs text-slate-500">Kosongkan untuk pakai nilai default.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="checkin_closes_at" class="block text-sm font-medium text-slate-700">Batas check-in di JMT</label>
                                <input
                                    id="checkin_closes_at"
                                    type="datetime-local"
                                    wire:model="checkin_closes_at"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                <p class="mt-1.5 text-xs text-slate-500">
                                    <span class="font-medium text-slate-700">Default: 20:00</span> pada tanggal sesi.
                                </p>
                                @error('checkin_closes_at') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="report_closes_at" class="block text-sm font-medium text-slate-700">Batas lapor ritase</label>
                                <input
                                    id="report_closes_at"
                                    type="datetime-local"
                                    wire:model="report_closes_at"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                >
                                <p class="mt-1.5 text-xs text-slate-500">
                                    <span class="font-medium text-slate-700">Default: 06:00</span> hari berikutnya. Boleh lewat tengah malam.
                                </p>
                                @error('report_closes_at') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SECTION 4: KELOMPOK & KUOTA --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500 text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Kelompok &amp; kuota</h2>
                                <p class="text-xs text-slate-500">Tiap kelompok punya link registrasi dan kuota sendiri.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="space-y-2.5">
                            @foreach ($groups as $i => $g)
                                <div wire:key="group-{{ $i }}" class="group relative rounded-xl border border-slate-200 bg-slate-50/60 p-3 transition-colors hover:bg-slate-50">
                                    <div class="flex items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Nama kelompok</label>
                                            <input
                                                type="text"
                                                wire:model="groups.{{ $i }}.name"
                                                placeholder="Contoh: Yayasan"
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                            >
                                        </div>
                                        <div class="w-20 shrink-0">
                                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500">Kuota</label>
                                            <input
                                                type="number"
                                                min="1"
                                                wire:model="groups.{{ $i }}.quota"
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center text-sm font-semibold text-slate-900 transition-colors focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                            >
                                        </div>
                                        @if (count($groups) > 1)
                                            <button
                                                type="button"
                                                wire:click="removeGroup({{ $i }})"
                                                wire:confirm="Hapus kelompok ini?"
                                                aria-label="Hapus kelompok"
                                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition-all hover:border-red-500 hover:bg-red-500 hover:text-white active:scale-95"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    @error("groups.$i.name") <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @error("groups.$i.quota") <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            wire:click="addGroup"
                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-white py-3 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-900 hover:bg-slate-50 hover:text-slate-900 sm:w-auto sm:justify-start sm:px-4"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah kelompok
                        </button>
                        @error('groups') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

            </div>

            {{-- ===== KOLOM KANAN: SIDEBAR STICKY (1/3) ===== --}}
            <div class="lg:col-span-1">
                <div class="space-y-4 lg:sticky lg:top-6">

                    {{-- Ringkasan --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ringkasan</h3>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-slate-600">Total kuota</span>
                                <span class="text-2xl font-bold text-slate-900">{{ collect($groups)->sum(fn ($g) => (int) ($g['quota'] ?? 0)) }}</span>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm text-slate-600">Jumlah kelompok</span>
                                <span class="text-lg font-semibold text-slate-900">{{ count($groups) }}</span>
                            </div>
                            <div class="border-t border-slate-100 pt-3">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-sm text-slate-600">Jam registrasi</span>
                                    <span class="text-sm font-semibold text-slate-900">{{ $open_time }} – {{ $close_time }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Panduan --}}
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-blue-900">Tips</h3>
                                <p class="mt-1 text-xs leading-relaxed text-blue-800">
                                    Setiap kelompok punya <strong>link registrasi</strong> dan <strong>kuota terpisah</strong>, jadi supir dari kelompok berbeda tidak rebutan slot.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Tombol Aksi --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3.5 text-base font-semibold text-white transition-all hover:bg-slate-800 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <span wire:loading.remove wire:target="save">{{ $this->workSessionId ? 'Simpan perubahan' : 'Buat sesi' }}</span>
                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                                </svg>
                                Menyimpan...
                            </span>
                        </button>
                        <a
                            href="{{ route('work-sessions.index') }}"
                            class="mt-2 flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            Batal
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>