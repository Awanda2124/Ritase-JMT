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

<div>
    <h1 class="text-2xl font-semibold">Pengaturan</h1>
    <p class="mt-1 text-sm text-slate-500">Tarif, radius, dan titik JMT tidak di-hardcode di kode.</p>

    @if (session('status'))
        <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <form wire:submit="save" class="mt-6 max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <div>
            <label for="rate_per_trip" class="block text-sm font-medium">Tarif per ritase (Rp)</label>
            <input id="rate_per_trip" type="number" min="0" wire:model="rate_per_trip" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('rate_per_trip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="radius_m" class="block text-sm font-medium">Radius JMT (meter)</label>
            <input id="radius_m" type="number" min="1" wire:model="radius_m" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('radius_m') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="jmt_lat" class="block text-sm font-medium">Latitude JMT</label>
            <input id="jmt_lat" type="text" wire:model="jmt_lat" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('jmt_lat') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="jmt_lng" class="block text-sm font-medium">Longitude JMT</label>
            <input id="jmt_lng" type="text" wire:model="jmt_lng" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('jmt_lng') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Simpan
        </button>
    </form>
</div>
