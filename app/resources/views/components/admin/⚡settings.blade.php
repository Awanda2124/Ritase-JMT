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

<div class="mx-auto max-w-4xl">

    {{-- Header --}}
    <div class="mb-6">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <span>Operasional</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">Pengaturan</span>
        </nav>
        <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Pengaturan</h1>
        <p class="mt-1 text-sm text-slate-500">Konfigurasi tarif, radius check-in, dan titik koordinat JMT.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
        </div>
    @endif

    <form wire:submit="save" class="space-y-5">

        {{-- SECTION 1 --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Tarif &amp; radius</h2>
                        <p class="text-xs text-slate-500">Nilai pembayaran per ritase dan batas jarak check-in.</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rate_per_trip" class="block text-sm font-medium text-slate-700">Tarif per ritase</label>
                        <div style="position: relative; margin-top: 6px;">
                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 14px; font-weight: 600; color: #64748b; pointer-events: none;">Rp</span>
                            <input id="rate_per_trip" type="number" min="0" wire:model="rate_per_trip"
                                class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                style="padding-left: 44px; padding-right: 12px;">
                        </div>
                        @error('rate_per_trip') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="radius_m" class="block text-sm font-medium text-slate-700">Radius JMT</label>
                        <div class="relative mt-1.5">
                            <input id="radius_m" type="number" min="1" wire:model="radius_m"
                                   class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-3.5 pr-12 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-sm font-semibold text-slate-500">m</span>
                        </div>
                        @error('radius_m') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2 --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500 text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Lokasi JMT</h2>
                        <p class="text-xs text-slate-500">Koordinat pusat geofence untuk validasi check-in.</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jmt_lat" class="block text-sm font-medium text-slate-700">Latitude</label>
                        <input id="jmt_lat" type="text" wire:model="jmt_lat" placeholder="-6.105414"
                               class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 font-mono text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                        @error('jmt_lat') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="jmt_lng" class="block text-sm font-medium text-slate-700">Longitude</label>
                        <input id="jmt_lng" type="text" wire:model="jmt_lng" placeholder="106.959231"
                               class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 font-mono text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                        @error('jmt_lng') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Dapatkan koordinat dari Google Maps dengan klik kanan di titik JMT.</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-slate-800 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="save">Simpan pengaturan</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/>
                    </svg>
                    Menyimpan...
                </span>
            </button>
        </div>

    </form>
</div>