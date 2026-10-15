<x-layouts.app title="Dashboard">

    {{-- ===== BREADCRUMB + HEADER ===== --}}
    <div class="mb-6">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500">
            <span>Operasional</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-900">Dashboard</span>
        </nav>
        <p class="mt-3 text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>

        <div class="mt-1 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Dashboard Operasional</h1>
                <p class="mt-1 text-sm text-slate-500">Pantau sesi kerja dan aktivitas ritase hari ini.</p>
            </div>
            <a href="{{ route('work-sessions.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-slate-800 active:scale-[0.98]">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Buat sesi
            </a>
        </div>
    </div>

    {{-- ===== SESI AKTIF ===== --}}
    @if ($activeSession)
        @php
            $filled = $activeSession->session_drivers_count;
            $quota = max((int) $activeSession->quota, 1);
            $percent = min(100, round(($filled / $quota) * 100));
        @endphp
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">

                {{-- Info sesi --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-500">
                            <span class="h-2 w-2 animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Sesi aktif</span>
                    </div>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ $activeSession->route }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $activeSession->date->translatedFormat('d M Y') }}
                        · {{ $activeSession->opens_at->format('H:i') }}–{{ $activeSession->closes_at->format('H:i') }}
                    </p>
                </div>

                {{-- Divider --}}
                <div class="hidden h-16 w-px bg-slate-200 sm:block"></div>

                {{-- Progress --}}
                <div class="w-full sm:w-64">
                    <div class="flex items-baseline justify-between">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ $filled }} <span class="font-normal text-slate-500">/ {{ $quota }} supir</span>
                        </p>
                        <p class="text-xs font-semibold text-emerald-700">{{ $percent }}%</p>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600" style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                {{-- CTA --}}
                <a href="{{ route('work-sessions.show', $activeSession) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition-all hover:border-slate-900 hover:shadow-sm">
                    Lihat sesi
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>

            </div>
        </div>
    @endif

    {{-- ===== STAT CARDS ===== --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tarif per ritase</p>
            </div>
            <p class="mt-3 text-2xl font-bold text-slate-900">Rp {{ number_format($ratePerTrip, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Berlaku per ritase supir.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Radius check-in</p>
            </div>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $radiusMeters }}<span class="ml-1 text-base font-semibold text-slate-500">m</span></p>
            <p class="mt-1 text-xs text-slate-500">Jarak check-in dari JMT.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total sesi</p>
            </div>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $totalSessions }}</p>
            <p class="mt-1 text-xs text-slate-500">Sesi kerja yang pernah dibuat.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Supir di sesi</p>
            </div>
            <p class="mt-3 text-2xl font-bold text-slate-900">{{ $totalDrivers }}</p>
            <p class="mt-1 text-xs text-slate-500">Terdaftar di sesi aktif</p>
        </div>

    </div>

    {{-- ===== BOTTOM GRID ===== --}}
    <div class="grid gap-5 lg:grid-cols-3">

        {{-- ===== AKTIVITAS SESI ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Aktivitas sesi</h2>
                    <p class="text-xs text-slate-500">Semua supir di sesi aktif ({{ $recentDrivers->count() }}). Geser untuk melihat semua.</p>
                </div>
                @if ($activeSession)
                    <a href="{{ route('work-sessions.show', $activeSession) }}"
                       class="inline-flex items-center gap-1 text-sm font-semibold text-slate-700 hover:text-slate-900">
                        Lihat semua
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @endif
            </div>

            @if ($recentDrivers->isEmpty())
                <div class="px-5 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                        <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">Belum ada aktivitas</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $activeSession ? 'Belum ada supir terdaftar di sesi ini.' : 'Belum ada sesi aktif hari ini.' }}
                    </p>
                </div>
            @else
                @php
                    $sortIcons = '<svg class="si si-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4"/></svg>'
                        .'<svg class="si si-asc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>'
                        .'<svg class="si si-desc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>';
                @endphp

                <style>
                    #activity-table th[data-sort] { cursor: pointer; user-select: none; transition: color .15s; }
                    #activity-table th[data-sort]:hover,
                    #activity-table th[data-sort][aria-sort="ascending"],
                    #activity-table th[data-sort][aria-sort="descending"] { color: #0f172a; }
                    #activity-table .sort-label { display: inline-flex; align-items: center; gap: 4px; }
                    #activity-table .si { display: none; width: 12px; height: 12px; flex-shrink: 0; }
                    #activity-table th[aria-sort="none"] .si-none { display: inline; opacity: .35; }
                    #activity-table th[aria-sort="none"]:hover .si-none { opacity: .8; }
                    #activity-table th[aria-sort="ascending"] .si-asc { display: inline; }
                    #activity-table th[aria-sort="descending"] .si-desc { display: inline; }
                </style>

                <div style="max-height: 440px; overflow: auto;">
                    <table id="activity-table" class="min-w-full text-left text-sm">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: 0 1px 0 #f1f5f9;">
                            <tr class="border-b border-slate-100">
                                <th data-sort="text" aria-sort="none" tabindex="0" class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <span class="sort-label">Nama supir {!! $sortIcons !!}</span>
                                </th>
                                <th data-sort="text" aria-sort="none" tabindex="0" class="px-3 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <span class="sort-label">KTP {!! $sortIcons !!}</span>
                                </th>
                                <th data-sort="date" aria-sort="none" tabindex="0" class="px-3 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <span class="sort-label">Check-in {!! $sortIcons !!}</span>
                                </th>
                                <th data-sort="text" aria-sort="none" tabindex="0" class="px-3 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <span class="sort-label">Status {!! $sortIcons !!}</span>
                                </th>
                                <th data-sort="number" aria-sort="none" tabindex="0" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <span class="sort-label">Total Ritase {!! $sortIcons !!}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentDrivers as $sd)
                                @php
                                    $initials = collect(explode(' ', $sd->driver->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
                                    $ritaseCount = $reportCounts[$sd->driver_id] ?? 0;
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="px-5 py-3" data-value="{{ $sd->driver->name }}">
                                        <div class="flex items-center gap-3">
                                            <!-- <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white">
                                                {{ mb_strtoupper($initials) }}
                                            </div> -->
                                            <span class="font-semibold text-slate-900">{{ $sd->driver->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs text-slate-500" data-value="{{ $sd->driver->maskedKtp() }}">{{ $sd->driver->maskedKtp() }}</td>
                                    <td class="px-3 py-3 text-slate-600 text-center" data-value="{{ $sd->checked_in_at?->timestamp }}">
                                        {{ $sd->checked_in_at ? $sd->checked_in_at->format('H:i') : '—' }}
                                    </td>
                                    <td class="px-3 py-3" data-value="{{ $sd->checked_in_at ? 'Di lokasi' : 'Belum check-in' }}">
                                        @if ($sd->checked_in_at)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Di lokasi
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                Belum check-in
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center font-semibold text-slate-900" data-value="{{ $ritaseCount }}">{{ $ritaseCount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <script>
                    (() => {
                        const table = document.getElementById('activity-table');
                        if (!table || table.dataset.sortReady) return;
                        table.dataset.sortReady = '1';

                        const tbody = table.tBodies[0];
                        const headers = Array.from(table.tHead.querySelectorAll('th[data-sort]'));
                        const collator = new Intl.Collator('id', { sensitivity: 'base', numeric: true });

                        // Simpan urutan asli untuk tie-break
                        Array.from(tbody.rows).forEach((row, i) => { row.dataset.index = i; });

                        const read = (row, col, type) => {
                            const raw = row.cells[col].dataset.value ?? '';
                            if (raw === '') return null;                // kosong selalu di paling bawah
                            return type === 'text' ? raw : Number(raw); // number & date (timestamp)
                        };

                        const sortBy = (th) => {
                            const col = th.cellIndex;
                            const type = th.dataset.sort;
                            const dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
                            const factor = dir === 'ascending' ? 1 : -1;

                            headers.forEach((h) => h.setAttribute('aria-sort', 'none'));
                            th.setAttribute('aria-sort', dir);

                            Array.from(tbody.rows)
                                .sort((a, b) => {
                                    const va = read(a, col, type);
                                    const vb = read(b, col, type);
                                    if (va === null && vb === null) return a.dataset.index - b.dataset.index;
                                    if (va === null) return 1;
                                    if (vb === null) return -1;
                                    const cmp = type === 'text' ? collator.compare(va, vb) : va - vb;
                                    return cmp !== 0 ? cmp * factor : a.dataset.index - b.dataset.index;
                                })
                                .forEach((row) => tbody.appendChild(row));
                        };

                        headers.forEach((th) => {
                            th.addEventListener('click', () => sortBy(th));
                            th.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    sortBy(th);
                                }
                            });
                        });
                    })();
                </script>
            @endif

        </div>

        {{-- ===== RINGKASAN HARI INI ===== --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-bold text-slate-900">Ringkasan hari ini</h2>
                <p class="text-xs text-slate-500">Statistik real-time.</p>
            </div>

            <div class="divide-y divide-slate-100">
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-slate-700">Check-in</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900">{{ $todayCheckIns }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-slate-700">Ritase tercatat</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900">{{ $todayReports }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-slate-700">Supir di lokasi</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900">{{ $hadirCount }}</span>
                </div>

                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-slate-700">Sesi aktif</span>
                    </div>
                    <span class="text-lg font-bold text-slate-900">{{ $activeSession?->isLive() ? 1 : 0 }}</span>
                </div>
            </div>

            @if ($activeSession)
                <div class="border-t border-slate-100 p-4">
                    <a href="{{ route('work-sessions.show', $activeSession) }}"
                       class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 transition-colors hover:bg-slate-100">
                        Lihat laporan
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                </div>
            @endif

        </div>

    </div>

    {{-- ===== FOOTER INFO ===== --}}
    <div class="mt-6 flex items-center justify-end gap-2 text-xs text-slate-400">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Data diperbarui otomatis
        <span class="text-slate-300">•</span>
        {{ now()->translatedFormat('d M Y H:i') }}
    </div>

</x-layouts.app>