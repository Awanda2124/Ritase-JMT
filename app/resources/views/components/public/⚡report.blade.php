<?php

use App\Exceptions\DriverNotInRosterException;
use App\Exceptions\LocationNotConfiguredException;
use App\Exceptions\NotCheckedInException;
use App\Exceptions\OutsideRadiusException;
use App\Exceptions\ReportingClosedException;
use App\Exceptions\ReportLimitExceededException;
use App\Models\SessionDriver;
use App\Models\WorkSession;
use App\Services\GeofenceService;
use App\Services\TripReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Lapor ritase')] class extends Component
{
    public ?float $lat = null;

    public ?float $lng = null;

    public bool $locationOk = false;

    public ?string $locationError = null;

    // Hanya untuk tampilan. Identitas sebenarnya dibaca dari session server.
    public ?string $driverName = null;

    public ?string $sessionLabel = null;

    /** @var array<int, string> */
    public array $chassis = [''];

    public bool $done = false;

    public int $savedCount = 0;

    public function mount(): void
    {
        $reporter = session('reporter');

        if (! is_array($reporter)) {
            $this->redirectRoute('public.checkin');

            return;
        }

        $workSessionId = (int) ($reporter['work_session_id'] ?? 0);
        $driverId = (int) ($reporter['driver_id'] ?? 0);

        $session = WorkSession::query()->reportable()->find($workSessionId);
        $sessionDriver = SessionDriver::query()
            ->where('work_session_id', $workSessionId)
            ->where('driver_id', $driverId)
            ->with('driver:id,name')
            ->first();

        if ($session === null || $sessionDriver === null || $sessionDriver->checked_in_at === null) {
            session()->forget('reporter');
            $this->redirectRoute('public.checkin');

            return;
        }

        $this->driverName = $sessionDriver->driver?->name;
        $this->sessionLabel = $session->date->translatedFormat('d M Y').' · '.$session->route;
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
                : 'Anda sekitar '.round($e->distance).' m dari JMT. Lapor hanya bisa dari area JMT.';
        } catch (LocationNotConfiguredException) {
            $this->locationError = 'Titik lokasi JMT belum diatur. Hubungi admin.';
        }
    }

    public function addChassis(): void
    {
        if (count($this->chassis) < TripReportService::MAX_TRIPS_PER_SESSION) {
            $this->chassis[] = '';
        }
    }

    public function removeChassis(int $index): void
    {
        unset($this->chassis[$index]);
        $this->chassis = array_values($this->chassis) ?: [''];
    }

    public function submit(TripReportService $reports): void
    {
        $reporter = session('reporter');

        if (! is_array($reporter)) {
            $this->redirectRoute('public.checkin');

            return;
        }

        if (! $this->locationOk || $this->lat === null || $this->lng === null) {
            $this->locationError = 'Lokasi belum terverifikasi.';

            return;
        }

        $this->validate([
            'chassis' => ['required', 'array', 'min:1', 'max:'.TripReportService::MAX_TRIPS_PER_SESSION],
            'chassis.*' => ['required', 'digits:4'],
        ], [
            'chassis.*.required' => 'Isi 4 digit sasis.',
            'chassis.*.digits' => 'Sasis harus 4 digit angka.',
        ]);

        try {
            $this->savedCount = $reports->submit(
                (int) ($reporter['work_session_id'] ?? 0),
                (int) ($reporter['driver_id'] ?? 0),
                $this->chassis,
                $this->lat,
                $this->lng,
            );
            $this->done = true;
        } catch (OutsideRadiusException) {
            $this->locationOk = false;
            $this->locationError = 'Lokasi Anda di luar area JMT. Cek lokasi ulang.';
        } catch (LocationNotConfiguredException) {
            $this->locationOk = false;
            $this->locationError = 'Titik lokasi JMT belum diatur. Hubungi admin.';
        } catch (ReportingClosedException) {
            $this->addError('chassis', 'Pelaporan untuk sesi ini sudah ditutup.');
        } catch (DriverNotInRosterException|NotCheckedInException) {
            session()->forget('reporter');
            $this->redirectRoute('public.checkin');
        } catch (ReportLimitExceededException $e) {
            $this->addError('chassis', 'Maksimal '.TripReportService::MAX_TRIPS_PER_SESSION.' ritase per sesi. Sudah tercatat: '.$e->alreadyReported.'.');
        }
    }

    public function resetForm(): void
    {
        $this->reset(['done', 'savedCount']);
        $this->chassis = [''];
    }

    public function switchDriver(): void
    {
        session()->forget('reporter');
        $this->redirectRoute('public.checkin');
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
    @if (! $locationOk && ! $done) x-init="locate()" @endif
>
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Lapor ritase</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $driverName }} · {{ $sessionLabel }}</p>

        @if ($done)
            <p class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-800">
                {{ $savedCount }} ritase tercatat. Serahkan karcis fisik ke admin.
            </p>
            <button type="button" wire:click="resetForm" class="mt-4 w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                Lapor lagi
            </button>
        @elseif (! $locationOk)
            <p class="mt-4 text-sm text-slate-500">Memeriksa lokasi Anda. Lapor hanya bisa dari area JMT.</p>
            <button type="button" x-on:click="locate()" x-bind:disabled="loading" class="mt-4 w-full rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white disabled:opacity-60">
                <span x-show="!loading">Cek lokasi saya</span>
                <span x-show="loading" x-cloak>Mencari lokasi...</span>
            </button>
            <p x-show="clientError" x-text="clientError" class="mt-3 text-sm text-red-600"></p>
            @if ($locationError)
                <p class="mt-3 text-sm text-red-600">{{ $locationError }}</p>
            @endif
        @else
            <form wire:submit="submit" class="mt-6 space-y-4">
                <div>
                    <p class="block text-sm font-medium">4 digit terakhir sasis (satu per karcis)</p>
                    <div class="mt-1 space-y-2">
                        @foreach ($chassis as $i => $value)
                            <div wire:key="chassis-{{ $i }}" class="flex gap-2">
                                <input type="text" inputmode="numeric" maxlength="4" wire:model="chassis.{{ $i }}" placeholder="0000" class="w-full rounded-md border border-slate-300 px-3 py-3 text-base">
                                @if (count($chassis) > 1)
                                    <button type="button" wire:click="removeChassis({{ $i }})" class="rounded-md border border-slate-300 px-3 text-base" aria-label="Hapus">&times;</button>
                                @endif
                            </div>
                            @error("chassis.$i") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                    @error('chassis') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if (count($chassis) < \App\Services\TripReportService::MAX_TRIPS_PER_SESSION)
                        <button type="button" wire:click="addChassis" class="mt-2 text-sm underline">Tambah sasis</button>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-3 text-base font-medium text-white hover:bg-slate-800">
                    Kirim laporan
                </button>
            </form>
        @endif

        <button type="button" wire:click="switchDriver" class="mt-6 text-sm text-slate-500 underline">Bukan {{ $driverName }}? Ganti nama</button>
    </div>
</div>