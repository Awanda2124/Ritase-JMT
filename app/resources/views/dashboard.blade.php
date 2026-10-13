<x-layouts.app title="Dasbor">
    <h1 class="text-2xl font-semibold">Dasbor</h1>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <dt class="text-sm text-slate-500">Tarif per ritase</dt>
            <dd class="mt-1 text-lg font-medium">Rp {{ number_format($ratePerTrip, 0, ',', '.') }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <dt class="text-sm text-slate-500">Radius JMT</dt>
            <dd class="mt-1 text-lg font-medium">{{ $radiusMeters }} m</dd>
        </div>
    </dl>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-medium">Sesi terbaru</h2>
        <a href="{{ route('work-sessions.create') }}" class="text-sm underline">Buat sesi</a>
    </div>

    <ul class="mt-3 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
        @forelse ($sessions as $session)
            <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <span>{{ $session->date->translatedFormat('d M Y') }} · {{ $session->session_drivers_count }}/{{ $session->quota }}</span>
                <a href="{{ route('work-sessions.show', $session) }}" class="underline">Roster</a>
            </li>
        @empty
            <li class="px-4 py-6 text-sm text-slate-500">Belum ada sesi kerja.</li>
        @endforelse
    </ul>
</x-layouts.app>