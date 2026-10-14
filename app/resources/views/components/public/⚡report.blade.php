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
    class="w-full"
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
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05);">

        {{-- Header --}}
        <div style="text-align: center;">
            <div style="width: 56px; height: 56px; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; box-shadow: 0 10px 20px -8px rgba(15, 23, 42, 0.4);">
                <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h1 style="margin: 16px 0 0; font-size: 22px; font-weight: 700; color: #0f172a;">Lapor ritase</h1>
            <p style="margin: 4px 0 0; font-size: 14px; color: #64748b;">{{ $driverName }} · {{ $sessionLabel }}</p>
        </div>

        @if ($done)
            {{-- Success --}}
            <div style="margin-top: 24px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 16px; padding: 24px; text-align: center;">
                <div style="width: 56px; height: 56px; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #10b981; color: #fff; box-shadow: 0 8px 16px -4px rgba(16, 185, 129, 0.4);">
                    <svg style="width: 28px; height: 28px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p style="margin: 12px 0 0; font-size: 16px; font-weight: 700; color: #065f46;">{{ $savedCount }} ritase tercatat</p>
                <p style="margin: 4px 0 0; font-size: 13px; color: #047857;">Serahkan karcis fisik ke admin.</p>
            </div>

            <button type="button" wire:click="resetForm"
                    style="margin-top: 16px; display: flex; width: 100%; align-items: center; justify-content: center; border: 1px solid #cbd5e1; background: #fff; border-radius: 12px; padding: 14px; font-size: 15px; font-weight: 600; color: #334155; cursor: pointer;">
                Lapor lagi
            </button>
        @elseif (! $locationOk)
            {{-- Location --}}
            <div style="margin-top: 24px; text-align: center;">
                <div style="width: 64px; height: 64px; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #f1f5f9;">
                    <svg style="width: 30px; height: 30px; color: #64748b;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/>
                        <circle cx="12" cy="9.5" r="2.5"/>
                    </svg>
                </div>
                <p style="margin: 16px 0 0; font-size: 14px; line-height: 1.5; color: #475569;">Memverifikasi lokasi Anda. Lapor ritase harus dilakukan dari area JMT</p>
            </div>

            <button type="button" x-on:click="locate()" x-bind:disabled="loading" class="jmt-locate-btn"
                    style="margin-top: 20px; display: flex; width: 100%; align-items: center; justify-content: center; border: 0; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer;">
                <span class="jmt-locate-text" style="display: inline-flex; align-items: center; gap: 8px;">
                    <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/>
                        <circle cx="12" cy="9.5" r="2.5"/>
                    </svg>
                    Cek lokasi saya
                </span>
                <span class="jmt-locate-loading" style="display: none; align-items: center; gap: 8px;">
                    <svg style="width: 18px; height: 18px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                    </svg>
                    Mencari lokasi...
                </span>
            </button>

            <div x-show="clientError" x-cloak role="alert" style="margin-top: 16px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px; padding: 14px;">
                <p style="margin: 0; font-size: 13px; font-weight: 500; color: #991b1b;" x-text="clientError"></p>
            </div>

            @if ($locationError)
                <div role="alert" style="margin-top: 16px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px; padding: 14px;">
                    <p style="margin: 0; font-size: 13px; font-weight: 500; color: #991b1b;">{{ $locationError }}</p>
                </div>
            @endif
        @else
            {{-- Form --}}
            <form wire:submit="submit" style="margin-top: 24px; display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px;">
                        4 digit terakhir sasis <span style="font-weight: 400; color: #64748b;">(satu per karcis)</span>
                    </label>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @foreach ($chassis as $i => $value)
                            <div wire:key="chassis-{{ $i }}" style="display: flex; gap: 8px;">
                                <input type="text" inputmode="numeric" maxlength="4"
                                       name="chassis[]"
                                       id="chassis-{{ $i }}"
                                       aria-label="Sasis 4 digit"
                                       wire:model="chassis.{{ $i }}" placeholder="0000"
                                       style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; padding: 12px 14px; text-align: center; font-family: 'Courier New', monospace; font-size: 20px; font-weight: 700; letter-spacing: 0.2em; color: #0f172a; outline: none;">
                                @if (count($chassis) > 1)
                                    <button type="button" wire:click="removeChassis({{ $i }})"
                                            style="display: flex; width: 48px; height: 48px; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid #fecaca; background: #fff; border-radius: 12px; color: #dc2626; cursor: pointer;">
                                        <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                            @error("chassis.$i") <p style="margin: 0; font-size: 13px; color: #dc2626;">{{ $message }}</p> @enderror
                        @endforeach
                    </div>

                    @error('chassis') <p style="margin: 8px 0 0; font-size: 13px; color: #dc2626;">{{ $message }}</p> @enderror

                    @if (count($chassis) < \App\Services\TripReportService::MAX_TRIPS_PER_SESSION)
                        <button type="button" wire:click="addChassis"
                                style="margin-top: 12px; display: inline-flex; width: 100%; align-items: center; justify-content: center; gap: 8px; border: 2px dashed #cbd5e1; background: #fff; border-radius: 12px; padding: 10px; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer;">
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah sasis
                        </button>
                    @endif
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="jmt-submit-btn"
                        style="display: flex; width: 100%; align-items: center; justify-content: center; border: 0; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer;">
                    <span class="jmt-submit-text" style="display: inline-flex; align-items: center; gap: 8px;">
                        Kirim laporan
                    </span>
                    <span class="jmt-submit-loading" style="display: none; align-items: center; gap: 8px;">
                        <svg style="width: 18px; height: 18px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                        </svg>
                        Mengirim...
                    </span>
                </button>
            </form>
        @endif

        <button type="button" wire:click="switchDriver"
                style="margin-top: 24px; display: block; width: 100%; text-align: center; border: 0; background: transparent; font-size: 13px; color: #64748b; text-decoration: underline; cursor: pointer;">
            Bukan {{ $driverName }}? Ganti nama
        </button>
    </div>
</div>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .jmt-locate-btn[disabled] .jmt-locate-text { display: none !important; }
    .jmt-locate-btn[disabled] .jmt-locate-loading { display: inline-flex !important; }
    .jmt-submit-btn[disabled] .jmt-submit-text { display: none !important; }
    .jmt-submit-btn[disabled] .jmt-submit-loading { display: inline-flex !important; }
</style>