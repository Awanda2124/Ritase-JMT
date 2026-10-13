<?php

use App\Exceptions\CheckInClosedException;
use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\LocationNotConfiguredException;
use App\Exceptions\OutsideRadiusException;
use App\Exceptions\ReportingClosedException;
use App\Models\SessionDriver;
use App\Models\WorkSession;
use App\Services\CheckInService;
use App\Services\GeofenceService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Check-in')] class extends Component
{
    public ?float $lat = null;
    public ?float $lng = null;
    public bool $locationOk = false;
    public ?string $locationError = null;
    public ?int $workSessionId = null;
    public ?int $driverId = null;
    public string $search = '';

    #[Computed]
    public function sessions(): Collection
    {
        return WorkSession::query()->reportable()->orderBy('date')->get();
    }

    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->search);

        if (! $this->locationOk
            || mb_strlen($term) < 2
            || ! $this->sessions->contains('id', $this->workSessionId)) {
            return collect();
        }

        $like = '%'.mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term)).'%';

        return SessionDriver::query()
            ->where('work_session_id', $this->workSessionId)
            ->whereHas('driver', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]))
            ->with('driver:id,name')
            ->limit(8)
            ->get()
            ->sortBy(fn (SessionDriver $sd) => $sd->driver?->name)
            ->values();
    }

    #[Computed]
    public function selected(): ?SessionDriver
    {
        if (! $this->locationOk || ! $this->driverId) {
            return null;
        }

        return SessionDriver::query()
            ->where('work_session_id', $this->workSessionId)
            ->where('driver_id', $this->driverId)
            ->with('driver:id,name')
            ->first();
    }

    public function mount(): void
    {
        if ($this->sessions->count() === 1) {
            $this->workSessionId = $this->sessions->first()->id;
        }
    }

    public function updatedWorkSessionId(): void
    {
        $this->driverId = null;
        $this->search = '';
    }

    public function selectDriver(int $driverId): void
    {
        $inRoster = SessionDriver::query()
            ->where('work_session_id', $this->workSessionId)
            ->where('driver_id', $driverId)
            ->exists();

        if (! $inRoster) {
            $this->addError('driverId', 'Nama tidak terdaftar di sesi ini.');
            return;
        }

        $this->resetErrorBag();
        $this->driverId = $driverId;
        $this->search = '';
    }

    public function clearDriver(): void
    {
        $this->resetErrorBag();
        $this->driverId = null;
        $this->search = '';
    }

    public function resetLocation(): void
    {
        $this->locationOk = false;
        $this->locationError = null;
        $this->driverId = null;
        $this->search = '';
        $this->resetErrorBag();
    }

    public function setLocation(float $lat, float $lng, GeofenceService $geofence): void
    {
        $this->locationOk = false;
        $this->locationError = null;

        try {
            $geofence->ensureWithinRadius($lat, $lng);
            $this->lat = $lat;
            $this->lng = $lng;
            $this->locationOk = true;
        } catch (OutsideRadiusException $e) {
            $this->locationError = $e->distance === null
                ? 'Lokasi tidak valid. Coba cek lokasi lagi.'
                : 'Kamu '.round($e->distance).' m dari JMT. Check-in hanya bisa dalam radius 50 m. Dekati JMT lalu coba lagi.';
        } catch (LocationNotConfiguredException) {
            $this->locationError = 'Titik lokasi JMT belum diatur. Hubungi admin.';
        }
    }

    public function submit(CheckInService $checkIn): void
    {
        if (! $this->locationOk || $this->lat === null || $this->lng === null) {
            $this->locationError = 'Lokasi belum terverifikasi.';
            return;
        }

        if (! $this->sessions->contains('id', $this->workSessionId)) {
            $this->addError('workSessionId', 'Sesi tidak tersedia.');
            return;
        }

        $this->validate(
            ['workSessionId' => ['required', 'integer'], 'driverId' => ['required', 'integer']],
            ['driverId.required' => 'Pilih nama Anda.'],
        );

        try {
            $checkIn->checkIn($this->workSessionId, $this->driverId, $this->lat, $this->lng);
        } catch (OutsideRadiusException) {
            $this->locationOk = false;
            $this->locationError = 'Kamu berada di luar area JMT. Cek lokasi ulang.';
            return;
        } catch (LocationNotConfiguredException) {
            $this->locationOk = false;
            $this->locationError = 'Titik lokasi JMT belum diatur. Hubungi admin.';
            return;
        } catch (CheckInClosedException) {
            $this->addError('driverId', 'Batas check-in sudah lewat. Hubungi admin untuk check-in manual.');
            return;
        } catch (ReportingClosedException) {
            $this->addError('workSessionId', 'Sesi ini sudah ditutup.');
            return;
        } catch (DriverNotInRosterException) {
            $this->addError('driverId', 'Nama tidak terdaftar di sesi ini.');
            return;
        }

        session()->put('reporter', [
            'work_session_id' => $this->workSessionId,
            'driver_id' => $this->driverId,
        ]);

        $this->redirectRoute('public.report');
    }
};
?>

<div
    class="mx-auto flex min-h-screen w-full max-w-md items-start px-4 pt-8 pb-10"
    x-data="{
        loading: false,
        clientError: '',
        locate() {
            if (!navigator.geolocation) {
                this.clientError = 'Browser tidak mendukung lokasi. Coba pakai Chrome (Android) atau Safari (iPhone).';
                return;
            }
            this.loading = true;
            this.clientError = '';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    $wire.setLocation(pos.coords.latitude, pos.coords.longitude)
                        .finally(() => { this.loading = false; });
                },
                (err) => {
                    this.loading = false;
                    if (err.code === 1) {
                        this.clientError = 'Izin lokasi ditolak. Aktifkan izin lokasi untuk browser ini, lalu coba lagi.';
                    } else {
                        this.clientError = 'Lokasi tidak ditemukan, coba lagi.';
                    }
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
    }"
>
    <div class="card w-full bg-base-100 shadow-sm border border-base-300">
        <div class="card-body gap-4">

            <h1 class="card-title text-2xl">Check-in</h1>

            @if ($this->sessions->isEmpty())
                <div role="alert" class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                    <div>
                        <h3 class="font-semibold">Belum ada sesi yang berjalan.</h3>
                        <p class="text-sm">Coba lagi nanti atau hubungi admin.</p>
                    </div>
                </div>
            @else

                {{-- ===== STEPPER (DaisyUI) ===== --}}
                <ul class="steps w-full">
                    <li class="step {{ $locationOk ? 'step-success' : 'step-primary' }}">Cek lokasi</li>
                    <li class="step {{ $locationOk ? 'step-primary' : '' }}">Pilih nama</li>
                </ul>

                <p class="text-sm text-base-content/70 text-center">
                    Langkah {{ $locationOk ? 2 : 1 }} dari 2: {{ $locationOk ? 'Pilih nama' : 'Cek lokasi' }}
                </p>

                @if (! $locationOk)
                    {{-- ===== LANGKAH 1: CEK LOKASI ===== --}}
                    <p class="text-base">
                        Check-in hanya bisa dilakukan dari area JMT. Ketuk tombol di bawah lalu pilih
                        <strong>Izinkan</strong> saat browser meminta akses lokasi.
                    </p>

                    <button
                        type="button"
                        x-on:click="locate()"
                        x-bind:disabled="loading"
                        class="btn btn-neutral btn-lg w-full"
                    >
                        <span x-show="!loading" class="inline-flex items-center gap-2">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                            Cek lokasi saya
                        </span>
                        <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Mencari lokasi... mohon tunggu
                        </span>
                    </button>

                    {{-- Error dari browser --}}
                    <div x-show="clientError" x-cloak role="alert" class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div class="w-full">
                            <p class="font-semibold" x-text="clientError"></p>

                            <details class="mt-3 text-sm">
                                <summary class="cursor-pointer font-medium underline">Petunjuk mengaktifkan lokasi</summary>
                                <div class="mt-2 space-y-1">
                                    <p><strong>iPhone (Safari):</strong> Pengaturan → Privasi &amp; Keamanan → Layanan Lokasi → Safari → pilih "Saat Menggunakan".</p>
                                    <p><strong>Android (Chrome):</strong> Ketuk ikon gembok di kolom alamat → Izin → Lokasi → Izinkan. Pastikan GPS menyala.</p>
                                </div>
                            </details>

                            <button type="button" x-on:click="locate()" class="btn btn-sm btn-outline mt-3">
                                Coba lagi
                            </button>
                        </div>
                    </div>

                    {{-- Error dari server --}}
                    @if ($locationError)
                        <div role="alert" class="alert alert-error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <div class="w-full">
                                <p class="font-semibold">{{ $locationError }}</p>
                                <button type="button" x-on:click="locate()" x-bind:disabled="loading" class="btn btn-sm btn-outline mt-3">
                                    Coba lagi
                                </button>
                            </div>
                        </div>
                    @endif
                @else
                    {{-- ===== LANGKAH 2: PILIH NAMA ===== --}}
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        <span class="font-semibold">Kamu di area JMT ✓</span>
                        <button type="button" wire:click="resetLocation" class="btn btn-xs btn-ghost ml-auto">
                            Cek ulang
                        </button>
                    </div>

                    @if ($this->sessions->count() > 1)
                        <div class="form-control w-full">
                            <label class="label" for="session">
                                <span class="label-text font-medium">Sesi</span>
                            </label>
                            <select id="session" wire:model.live="workSessionId" class="select select-bordered w-full">
                                <option value="">Pilih sesi</option>
                                @foreach ($this->sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->date->translatedFormat('d M Y') }} · {{ $session->route }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @error('workSessionId')
                        <div role="alert" class="alert alert-error">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    @if ($this->selected)
                        {{-- Konfirmasi Nama Terpilih --}}
                        @php $sudah = (bool) $this->selected->checked_in_at; @endphp
                        <form wire:submit="submit" class="space-y-4">
                            <div class="card bg-base-200">
                                <div class="card-body items-center text-center p-5">
                                    @if ($sudah)
                                        <p class="text-sm text-base-content/70">Kamu sudah check-in sebagai</p>
                                        <p class="text-2xl font-bold">{{ $this->selected->driver?->name }}</p>
                                        <div class="badge badge-success badge-outline mt-1">
                                            ✓ Check-in pukul {{ $this->selected->checked_in_at->format('H:i') }}
                                        </div>
                                    @else
                                        <p class="text-sm text-base-content/70">Check-in sebagai</p>
                                        <p class="text-2xl font-bold">{{ $this->selected->driver?->name }}?</p>
                                    @endif

                                    <button type="button" wire:click="clearDriver" class="btn btn-sm btn-ghost mt-2 underline">
                                        Bukan saya
                                    </button>
                                </div>
                            </div>

                            @error('driverId')
                                <div role="alert" class="alert alert-error">
                                    <span>{{ $message }}</span>
                                </div>
                            @enderror

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="btn btn-neutral btn-lg w-full"
                            >
                                <span wire:loading.remove wire:target="submit">{{ $sudah ? 'Lanjut ke Lapor ritase' : 'Check-in' }}</span>
                                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                                    <span class="loading loading-spinner loading-sm"></span>
                                    Memproses...
                                </span>
                            </button>
                        </form>
                    @else
                        {{-- Pencarian Nama (Search-as-you-type) --}}
                        <div class="form-control w-full">
                            <label class="label" for="search">
                                <span class="label-text font-medium">Ketik nama kamu</span>
                            </label>
                            <input
                                id="search"
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                autocomplete="off"
                                autocapitalize="words"
                                placeholder="Ketik nama kamu"
                                class="input input-bordered w-full"
                            />

                            <div wire:loading wire:target="search" class="mt-2 flex items-center gap-2 text-sm text-base-content/60">
                                <span class="loading loading-spinner loading-xs"></span>
                                Mencari...
                            </div>

                            @error('driverId')
                                <div role="alert" class="alert alert-error mt-3">
                                    <span>{{ $message }}</span>
                                </div>
                            @enderror

                            @if (mb_strlen(trim($search)) < 2)
                                <p class="mt-2 text-sm text-base-content/60">Ketik minimal 2 huruf untuk mencari nama.</p>
                            @elseif ($this->results->isEmpty())
                                <div wire:loading.remove wire:target="search" class="alert alert-warning mt-3">
                                    <span>Nama tidak ditemukan. Hubungi admin.</span>
                                </div>
                            @else
                                <ul class="mt-3 space-y-2">
                                    @foreach ($this->results as $row)
                                        <li wire:key="driver-{{ $row->driver_id }}">
                                            <button
                                                type="button"
                                                wire:click="selectDriver({{ $row->driver_id }})"
                                                class="btn btn-outline w-full justify-between normal-case h-auto py-3"
                                            >
                                                <span class="font-medium">{{ $row->driver?->name }}</span>
                                                @if ($row->checked_in_at)
                                                    <span class="badge badge-success badge-sm">✓ Sudah check-in</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                @endif
            @endif

        </div>
    </div>
</div>