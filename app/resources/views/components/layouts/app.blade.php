<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Dasbor' }} — {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4">
                <div>
                    <p class="text-sm font-semibold">{{ config('app.name') }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()?->name }} · {{ auth()->user()?->getRoleNames()->join(', ') }}</p>
                </div>
                <nav class="flex flex-wrap items-center gap-3 text-sm">
                    <a href="{{ route('dashboard') }}" class="underline">Dasbor</a>
                    <a href="{{ route('work-sessions.index') }}" class="underline">Sesi kerja</a>
                    <a href="{{ route('settings.edit') }}" class="underline">Pengaturan</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                            Keluar
                        </button>
                    </form>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>
        @livewireScripts
    </body>
</html>
