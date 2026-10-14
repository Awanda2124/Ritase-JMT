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
    class="w-full"
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
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05);">

        {{-- Header --}}
        <div style="text-align: center;">
            <div style="width: 56px; height: 56px; margin: 0 auto; display: flex; align-items: center; justify-content: center; border-radius: 16px; background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; box-shadow: 0 10px 20px -8px rgba(15, 23, 42, 0.4);">
                <svg style="width: 26px; height: 26px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h1 style="margin: 16px 0 0; font-size: 22px; font-weight: 700; color: #0f172a;">Check-in</h1>
            <p style="margin: 4px 0 0; font-size: 14px; color: #64748b;">Konfirmasi kehadiran Anda di JMT.</p>
        </div>

        @if ($this->sessions->isEmpty())
            <div style="margin-top: 24px; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 14px; padding: 16px;">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <svg style="width: 20px; height: 20px; flex-shrink: 0; color: #d97706; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <div>
                        <p style="margin: 0; font-size: 14px; font-weight: 700; color: #92400e;">Belum ada sesi yang berjalan</p>
                        <p style="margin: 4px 0 0; font-size: 13px; color: #a16207;">Coba lagi nanti atau hubungi admin.</p>
                    </div>
                </div>
            </div>
        @else

            {{-- Stepper --}}
            <div style="margin-top: 24px;">
                <p style="margin: 0; text-align: center; font-size: 13px; font-weight: 600; color: #475569;">
                    Langkah {{ $locationOk ? 2 : 1 }} dari 2:
                    {{ $locationOk ? 'Pilih nama' : 'Cek lokasi' }}
                </p>
                <div style="margin-top: 8px; display: flex; gap: 8px;">
                    <div style="height: 6px; flex: 1; border-radius: 9999px; background: #0f172a;"></div>
                    <div style="height: 6px; flex: 1; border-radius: 9999px; background: {{ $locationOk ? '#0f172a' : '#e2e8f0' }};"></div>
                </div>
            </div>

            @if (! $locationOk)
                {{-- ===== LANGKAH 1: CEK LOKASI ===== --}}
                <p style="margin: 24px 0 0; font-size: 14px; line-height: 1.6; color: #334155;">
                    Check-in hanya bisa dilakukan dari area JMT. Ketuk tombol di bawah lalu pilih
                    <strong style="color: #0f172a;">Izinkan</strong> saat browser meminta akses lokasi.
                </p>

                <button
                    type="button"
                    x-on:click="locate()"
                    x-bind:disabled="loading"
                    class="jmt-locate-btn"
                    style="margin-top: 20px; display: flex; width: 100%; align-items: center; justify-content: center; border: 0; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer;"
                >
                    <span class="jmt-locate-text" style="display: inline-flex; align-items: center; gap: 8px;">
                        <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                        Cek lokasi saya
                    </span>
                    <span class="jmt-locate-loading" style="display: none; align-items: center; gap: 8px;">
                        <svg style="width: 18px; height: 18px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                        </svg>
                        Mencari lokasi... mohon tunggu
                    </span>
                </button>

                {{-- Error dari browser --}}
                <div x-show="clientError" x-cloak role="alert" style="margin-top: 16px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 14px; padding: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <svg style="width: 20px; height: 20px; flex-shrink: 0; color: #dc2626; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                        </svg>
                        <div style="flex: 1;">
                            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #991b1b;" x-text="clientError"></p>
                            <details style="margin-top: 12px; font-size: 13px; color: #7f1d1d;">
                                <summary style="cursor: pointer; font-weight: 500; text-decoration: underline;">Petunjuk mengaktifkan lokasi</summary>
                                <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 6px;">
                                    <p style="margin: 0;"><strong>iPhone:</strong> Pengaturan → Privasi &amp; Keamanan → Layanan Lokasi → Safari → "Saat Menggunakan".</p>
                                    <p style="margin: 0;"><strong>Android:</strong> Ketuk ikon gembok di kolom alamat → Izin → Lokasi → Izinkan.</p>
                                </div>
                            </details>
                            <button type="button" x-on:click="locate()" style="margin-top: 12px; border: 1px solid #fca5a5; background: #fff; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #991b1b; cursor: pointer;">
                                Coba lagi
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Error dari server --}}
                @if ($locationError)
                    <div role="alert" style="margin-top: 16px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 14px; padding: 16px;">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <svg style="width: 20px; height: 20px; flex-shrink: 0; color: #dc2626; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                            </svg>
                            <div style="flex: 1;">
                                <p style="margin: 0; font-size: 14px; font-weight: 600; color: #991b1b;">{{ $locationError }}</p>
                                <button type="button" x-on:click="locate()" x-bind:disabled="loading" style="margin-top: 12px; border: 1px solid #fca5a5; background: #fff; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #991b1b; cursor: pointer;">
                                    Coba lagi
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                {{-- ===== LANGKAH 2: PILIH NAMA ===== --}}
                <div style="margin-top: 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 12px; padding: 12px 16px;">
                    <p style="margin: 0; font-size: 14px; font-weight: 600; color: #065f46;">✓ Kamu di area JMT</p>
                    <button type="button" wire:click="resetLocation" style="border: 0; background: transparent; font-size: 13px; font-weight: 500; color: #065f46; text-decoration: underline; cursor: pointer;">
                        Cek ulang
                    </button>
                </div>

                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 16px;">
                    @if ($this->sessions->count() > 1)
                        <div>
                            <label for="session" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Sesi</label>
                            <select id="session" wire:model.live="workSessionId" style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 12px 14px; font-size: 15px; color: #0f172a;">
                                <option value="">Pilih sesi</option>
                                @foreach ($this->sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->date->translatedFormat('d M Y') }} · {{ $session->route }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @error('workSessionId')
                        <div role="alert" style="border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 12px; font-size: 14px; color: #991b1b;">{{ $message }}</div>
                    @enderror

                    @if ($this->selected)
                        {{-- Konfirmasi nama --}}
                        @php $sudah = (bool) $this->selected->checked_in_at; @endphp
                        <form wire:submit="submit" style="display: flex; flex-direction: column; gap: 16px;">
                            <div style="border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 14px; padding: 20px; text-align: center;">
                                @if ($sudah)
                                    <p style="margin: 0; font-size: 13px; color: #64748b;">Kamu sudah check-in sebagai</p>
                                    <p style="margin: 6px 0 0; font-size: 20px; font-weight: 700; color: #0f172a;">{{ $this->selected->driver?->name }}</p>
                                    <p style="margin: 8px 0 0; display: inline-block; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 9999px; padding: 4px 12px; font-size: 12px; font-weight: 600; color: #047857;">
                                        ✓ Check-in pukul {{ $this->selected->checked_in_at->format('H:i') }}
                                    </p>
                                @else
                                    <p style="margin: 0; font-size: 13px; color: #64748b;">Check-in sebagai</p>
                                    <p style="margin: 6px 0 0; font-size: 20px; font-weight: 700; color: #0f172a;">{{ $this->selected->driver?->name }}?</p>
                                @endif

                                <button type="button" wire:click="clearDriver" style="margin-top: 12px; border: 0; background: transparent; font-size: 13px; color: #64748b; text-decoration: underline; cursor: pointer;">
                                    Bukan saya
                                </button>
                            </div>

                            @error('driverId')
                                <div role="alert" style="border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 12px; font-size: 14px; color: #991b1b;">{{ $message }}</div>
                            @enderror

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="jmt-checkin-btn"
                                style="display: flex; width: 100%; align-items: center; justify-content: center; border: 0; border-radius: 12px; background: #0f172a; padding: 14px; font-size: 15px; font-weight: 600; color: #fff; cursor: pointer;"
                            >
                                <span class="jmt-checkin-text" style="display: inline-flex; align-items: center; gap: 8px;">
                                    {{ $sudah ? 'Lanjut ke Lapor ritase' : 'Check-in' }}
                                </span>
                                <span class="jmt-checkin-loading" style="display: none; align-items: center; gap: 8px;">
                                    <svg style="width: 18px; height: 18px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                                    </svg>
                                    Memproses...
                                </span>
                            </button>
                        </form>
                    @else
                        {{-- Search --}}
                        <div>
                            <label for="search" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Ketik nama kamu</label>
                            <input
                                id="search"
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                autocomplete="off"
                                autocapitalize="words"
                                placeholder="Contoh: Budi"
                                style="display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; padding: 12px 14px; font-size: 15px; color: #0f172a; outline: none;"
                            />

                            <div wire:loading wire:target="search" style="margin-top: 8px; display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b;">
                                <svg style="width: 14px; height: 14px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                                </svg>
                                Mencari...
                            </div>

                            @error('driverId')
                                <div role="alert" style="margin-top: 12px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; padding: 12px; font-size: 14px; color: #991b1b;">{{ $message }}</div>
                            @enderror

                            @if (mb_strlen(trim($search)) < 2)
                                <p style="margin: 8px 0 0; font-size: 13px; color: #64748b;">Ketik minimal 2 huruf untuk mencari nama.</p>
                            @elseif ($this->results->isEmpty())
                                <p wire:loading.remove wire:target="search" style="margin: 12px 0 0; border: 1px solid #fcd34d; background: #fffbeb; border-radius: 10px; padding: 12px; font-size: 14px; color: #92400e;">
                                    Nama tidak ditemukan. Hubungi admin.
                                </p>
                            @else
                                <ul style="margin: 12px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px;">
                                    @foreach ($this->results as $row)
                                        <li wire:key="driver-{{ $row->driver_id }}">
                                            <button
                                                type="button"
                                                wire:click="selectDriver({{ $row->driver_id }})"
                                                style="display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #cbd5e1; background: #fff; border-radius: 10px; padding: 14px 16px; text-align: left; cursor: pointer; font-size: 15px; color: #0f172a;"
                                            >
                                                <span style="font-weight: 600;">{{ $row->driver?->name }}</span>
                                                @if ($row->checked_in_at)
                                                    <span style="flex-shrink: 0; background: #ecfdf5; border-radius: 9999px; padding: 3px 10px; font-size: 11px; font-weight: 600; color: #047857;">✓ Sudah check-in</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        @endif

    </div>
</div>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .jmt-locate-btn[disabled] .jmt-locate-text { display: none !important; }
    .jmt-locate-btn[disabled] .jmt-locate-loading { display: inline-flex !important; }
    .jmt-checkin-btn[disabled] .jmt-checkin-text { display: none !important; }
    .jmt-checkin-btn[disabled] .jmt-checkin-loading { display: inline-flex !important; }
</style>