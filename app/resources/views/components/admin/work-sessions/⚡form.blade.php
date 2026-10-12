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

    public string $quota = '50';

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
        $this->quota = (string) $workSession->quota;
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

<div>
    <h1 class="text-2xl font-semibold">{{ $this->workSessionId ? 'Ubah sesi' : 'Buat sesi' }}</h1>

    <form wire:submit="save" class="mt-6 max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <div>
            <label for="date" class="block text-sm font-medium">Tanggal</label>
            <input id="date" type="date" wire:model="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="route" class="block text-sm font-medium">Rute</label>
            <input id="route" type="text" wire:model="route" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('route') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="open_time" class="block text-sm font-medium">Jam buka</label>
                <input id="open_time" type="time" wire:model="open_time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('open_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="close_time" class="block text-sm font-medium">Jam tutup</label>
                <input id="close_time" type="time" wire:model="close_time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('close_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="quota" class="block text-sm font-medium">Kuota</label>
            <input id="quota" type="number" min="1" wire:model="quota" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('quota') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Simpan
            </button>
            <a href="{{ route('work-sessions.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Batal</a>
        </div>
    </form>
</div>
