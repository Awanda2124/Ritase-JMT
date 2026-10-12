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

    #[Computed]
    public function sessions(): Collection
    {
        return WorkSession::query()->reportable()->orderBy('date')->get();
    }

    #[Computed]
    public function roster(): Collection
    {
        if (! $this->locationOk || ! $this->sessions->contains('id', $this->workSessionId)) {
            return collect();
        }

        return SessionDriver::query()
            ->where('work_session_id', $this->workSessionId)
            ->with('driver:id,name')
            ->get()
            ->sortBy(fn (SessionDriver $sd) => $sd->driver?->name)
            ->values();
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
                ? 'Lokasi tidak valid.'
                : 'Anda sekitar '.round($e->distance).' m dari JMT. Check-in hanya bisa dari area JMT.';
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
            $this->locationError = 'Lokasi Anda di luar area JMT. Cek lokasi ulang.';

            return;
        } catch (LocationNotConfiguredException) {
            $this->locationOk = false;
            $this->locationError = 'Titik lokasi JMT belum diatur. Hubungi admin.';

            return;
        } catch (CheckInClosedException) {
            $this->addError('driverId', 'Batas check-in sudah lewat. Anda dianggap tidak hadir. Hubungi admin.');

            return;
        } catch (ReportingClosedException) {
            $this->addError('workSessionId', 'Sesi ini sudah ditutup.');

            return;
        } catch (DriverNotInRosterException) {
            $this->addError('driverId', 'Nama tidak terdaftar di sesi ini.');

            return;
        }

        // Identitas supir disimpan di session server (tidak bisa diubah dari browser).
        session()->put('reporter', [
            'work_session_id' => $this->workSessionId,
            'driver_id' => $this->driverId,
        ]);

        $this->redirectRoute('public.report');
    }
};
?>

<div
    class="flex min-h-screen items-center justify-center px-4 py-8"
    x-data="{
        loading: false,
        clientError: '',
        locate() {
            if (!navigator.geolocation) {
                this.clientError = 'Browser tidak mendukung lokasi.';
                return;
            }
            this.loading = true;
            this.clientError = '';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.loading = false;
                    $wire.setLocation(pos.coords.latitude, pos.coords.longitude);
                },
                () => {
                    this.loading = false;
                    this.clientError = 'Izin lokasi ditolak atau lokasi tidak tersedia. Aktifkan GPS lalu coba lagi.';
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
    }"
>
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Check-in</h1>

        @if ($this->sessions->isEmpty())
            <p class="mt-6 rounded-md bg-amber-50 p-4 text-sm text-amber-900">Tidak ada sesi yang sedang berjalan.</p>
        @elseif (! $locationOk)
            <p class="mt-2 text-sm text-slate-500">Check-in hanya bisa dilakukan dari area JMT. Izinkan akses lokasi di browser.</p>
            <button type="button" x-on:click="locate()" x-bind:disabled="loading" class="mt-6 w-full rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white disabled:opacity-60">
                <span x-show="!loading">Cek lokasi saya</span>
                <span x-show="loading" x-cloak>Mencari lokasi...</span>
            </button>
            <p x-show="clientError" x-text="clientError" class="mt-3 text-sm text-red-600"></p>
            @if ($locationError)
                <p class="mt-3 text-sm text-red-600">{{ $locationError }}</p>
            @endif
        @else
            <form wire:submit="submit" class="mt-6 space-y-4">
                @if ($this->sessions->count() > 1)
                    <div>
                        <label for="session" class="block text-sm font-medium">Sesi</label>
                        <select id="session" wire:model.live="workSessionId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                            <option value="">Pilih sesi</option>
                            @foreach ($this->sessions as $session)
                                <option value="{{ $session->id }}">{{ $session->date->translatedFormat('d M Y') }} · {{ $session->route }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @error('workSessionId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <div>
                    <label for="driver" class="block text-sm font-medium">Nama</label>
                    <select id="driver" wire:model="driverId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                        <option value="">Pilih nama</option>
                        @foreach ($this->roster as $sessionDriver)
                            <option value="{{ $sessionDriver->driver_id }}">
                                {{ $sessionDriver->driver?->name }}@if ($sessionDriver->checked_in_at) (sudah check-in)@endif
                            </option>
                        @endforeach
                    </select>
                    @error('driverId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white hover:bg-slate-800">
                    Check-in
                </button>
            </form>
        @endif
    </div>
</div>