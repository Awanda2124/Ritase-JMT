<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Masuk' }} — {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        {{ $slot }}
        @livewireScripts
        <div x-data="{ show: false }"
            x-on:net-error.window="show = true; setTimeout(() => show = false, 5000)"
            x-show="show" x-cloak role="alert"
            class="fixed inset-x-4 top-4 z-50 rounded-lg bg-red-600 p-4 text-center text-base font-medium text-white shadow-lg">
            Koneksi bermasalah. Data belum terkirim, coba lagi.
        </div>

        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ fail }) => {
                    fail(() => window.dispatchEvent(new CustomEvent('net-error')));
                });
            });
        </script>
    </body>
</html>
