<?php

use App\Models\WorkSession;
use App\Services\WorkSessionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

new #[Layout('layouts.app')] #[Title('Sesi kerja')] class extends Component
{
    public function delete(int $sessionId, WorkSessionService $sessions): void
    {
        $session = WorkSession::query()->findOrFail($sessionId);

        try {
            $sessions->delete($session);
            session()->flash('status', 'Sesi dihapus.');
        } catch (RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }
};
?>

<div>
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Sesi kerja</h1>
        <a href="{{ route('work-sessions.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Buat sesi
        </a>
    </div>

    @if (session('status'))
        <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 text-sm text-red-700">{{ session('error') }}</p>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-medium">Tanggal</th>
                    <th class="px-4 py-3 font-medium">Rute</th>
                    <th class="px-4 py-3 font-medium">Jam</th>
                    <th class="px-4 py-3 font-medium">Kuota</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse (App\Models\WorkSession::query()->withCount('sessionDrivers')->latest('date')->get() as $session)
                    <tr wire:key="session-{{ $session->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $session->date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $session->route }}</td>
                        <td class="px-4 py-3">{{ $session->opens_at->format('H:i') }}–{{ $session->closes_at->format('H:i') }}</td>
                        <td class="px-4 py-3">{{ $session->session_drivers_count }}/{{ $session->quota }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('work-sessions.show', $session) }}" class="text-slate-700 underline">Roster</a>
                            <a href="{{ route('work-sessions.edit', $session) }}" class="ml-3 text-slate-700 underline">Ubah</a>
                            <button type="button" wire:click="delete({{ $session->id }})" wire:confirm="Hapus sesi ini?" class="ml-3 text-red-700 underline">
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-slate-500">Belum ada sesi kerja.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
