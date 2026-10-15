<?php

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\SettingKey;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Pengaturan')] class extends Component
{
    public string $rate_per_trip = '';

    public string $radius_m = '';

    public string $jmt_lat = '';

    public string $jmt_lng = '';

    public function mount(SettingService $settings): void
    {
        $this->rate_per_trip = (string) $settings->ratePerTrip();
        $this->radius_m = (string) $settings->radiusMeters();
        $this->jmt_lat = (string) ($settings->jmtLatitude() ?? '');
        $this->jmt_lng = (string) ($settings->jmtLongitude() ?? '');
    }

    public function save(SettingService $settings): void
    {
        $form = new UpdateSettingsRequest;
        $validated = $this->validate($form->rules(), $form->messages());

        $settings->set(SettingKey::RATE_PER_TRIP, (string) $validated['rate_per_trip']);
        $settings->set(SettingKey::RADIUS_M, (string) $validated['radius_m']);
        $settings->set(SettingKey::JMT_LAT, (string) $validated['jmt_lat']);
        $settings->set(SettingKey::JMT_LNG, (string) $validated['jmt_lng']);

        session()->flash('status', 'Pengaturan disimpan.');
    }
};
?>

<div class="mx-auto max-w-6xl">

    {{-- Header --}}
    <div style="margin-bottom: 16px;">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <span>Operasional</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">Pengaturan</span>
        </nav>
        <h1 style="margin: 6px 0 0; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.2;">Pengaturan</h1>
        <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Konfigurasi tarif, radius check-in, dan titik koordinat JMT.</p>
    </div>

    @if (session('status'))
        <div style="margin-bottom: 14px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid #a7f3d0; background: #ecfdf5; border-radius: 12px; padding: 10px 14px;">
            <svg style="width: 16px; height: 16px; flex-shrink: 0; color: #059669; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p style="margin: 0; font-size: 13px; font-weight: 500; color: #065f46;">{{ session('status') }}</p>
        </div>
    @endif

    <form wire:submit="save">

        {{-- ===== 2 CARD SEJAJAR ===== --}}
        <div class="jmt-settings-grid">

            {{-- ===== SECTION 1: TARIF & RADIUS ===== --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" style="height: 100%; display: flex; flex-direction: column;">
                <div class="border-b border-slate-100" style="padding: 12px 18px;">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center rounded-xl bg-emerald-500 text-white shadow-sm" style="width: 34px; height: 34px; flex-shrink: 0;">
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a;">Tarif &amp; radius</h2>
                            <p style="margin: 2px 0 0; font-size: 11px; color: #64748b;">Nilai pembayaran per ritase dan batas jarak check-in.</p>
                        </div>
                    </div>
                </div>

                <div style="padding: 16px 18px; flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; flex-direction: column; gap: 14px; flex: 1;">

                        {{-- Tarif --}}
                        <div>
                            <label for="rate_per_trip" class="jmt-label">Tarif per ritase</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 13px; font-weight: 600; color: #94a3b8; pointer-events: none;">Rp</span>
                                <input
                                    id="rate_per_trip"
                                    type="number"
                                    min="0"
                                    wire:model="rate_per_trip"
                                    class="jmt-input"
                                    style="padding: 0 14px 0 42px;"
                                >
                            </div>
                            @error('rate_per_trip') <p style="margin: 6px 0 0; font-size: 12px; color: #dc2626;">{{ $message }}</p> @enderror
                        </div>

                        {{-- Radius --}}
                        <div>
                            <label for="radius_m" class="jmt-label">Radius JMT</label>
                            <div style="position: relative;">
                                <input
                                    id="radius_m"
                                    type="number"
                                    min="1"
                                    wire:model="radius_m"
                                    class="jmt-input"
                                    style="padding: 0 42px 0 14px;"
                                >
                                <span style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-size: 13px; font-weight: 600; color: #94a3b8; pointer-events: none;">m</span>
                            </div>
                            @error('radius_m') <p style="margin: 6px 0 0; font-size: 12px; color: #dc2626;">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- ===== SECTION 2: LOKASI JMT ===== --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" style="height: 100%; display: flex; flex-direction: column;">
                <div class="border-b border-slate-100" style="padding: 12px 18px;">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center rounded-xl bg-blue-500 text-white shadow-sm" style="width: 34px; height: 34px; flex-shrink: 0;">
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a;">Lokasi JMT</h2>
                            <p style="margin: 2px 0 0; font-size: 11px; color: #64748b;">Koordinat pusat geofence untuk validasi check-in.</p>
                        </div>
                    </div>
                </div>

                <div style="padding: 16px 18px; flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; flex-direction: column; gap: 14px; flex: 1;">

                        {{-- Latitude --}}
                        <div>
                            <label for="jmt_lat" class="jmt-label">Latitude</label>
                            <input
                                id="jmt_lat"
                                type="text"
                                wire:model="jmt_lat"
                                placeholder="-6.105414"
                                class="jmt-input"
                                style="padding: 0 14px; font-family: 'Courier New', monospace;"
                            >
                            @error('jmt_lat') <p style="margin: 6px 0 0; font-size: 12px; color: #dc2626;">{{ $message }}</p> @enderror
                        </div>

                        {{-- Longitude --}}
                        <div>
                            <label for="jmt_lng" class="jmt-label">Longitude</label>
                            <input
                                id="jmt_lng"
                                type="text"
                                wire:model="jmt_lng"
                                placeholder="106.959231"
                                class="jmt-input"
                                style="padding: 0 14px; font-family: 'Courier New', monospace;"
                            >
                            @error('jmt_lng') <p style="margin: 6px 0 0; font-size: 12px; color: #dc2626;">{{ $message }}</p> @enderror
                        </div>

                        {{-- Info box --}}
                        <div style="margin-top: auto; display: flex; align-items: flex-start; gap: 8px; border: 1px solid #dbeafe; background: #eff6ff; border-radius: 10px; padding: 10px 12px;">
                            <svg style="width: 14px; height: 14px; flex-shrink: 0; color: #2563eb; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p style="margin: 0; font-size: 11px; line-height: 1.5; color: #1e40af;">
                                Klik kanan di titik JMT pada Google Maps, klik angka yang muncul untuk copy koordinat.
                            </p>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        {{-- Actions --}}
        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="jmt-save-btn"
                    style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 12px; background: #0f172a; padding: 12px 24px; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15); font-family: inherit; min-width: 180px; transition: background 0.15s;">
                <span class="jmt-save-text" style="display: inline-flex; align-items: center; gap: 8px;">
                    Simpan pengaturan
                </span>
                <span class="jmt-save-loading" style="display: none; align-items: center; gap: 8px;">
                    <svg style="width: 16px; height: 16px; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24" fill="none">
                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                    </svg>
                    Menyimpan...
                </span>
            </button>
        </div>

    </form>
</div>