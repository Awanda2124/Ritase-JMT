<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Masuk' }} — {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')

        <style>
            .jmt-guest-page {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            }

            .jmt-guest-brand {
                padding: 24px 16px 0;
                text-align: center;
            }

            .jmt-guest-brand-logo {
                width: 48px;
                height: 48px;
                margin: 0 auto;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 14px;
                background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
                color: #fff;
                box-shadow: 0 10px 20px -8px rgba(15, 23, 42, 0.4);
            }

            .jmt-guest-brand-title {
                margin: 10px 0 0;
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
            }

            .jmt-guest-brand-sub {
                margin: 2px 0 0;
                font-size: 11px;
                color: #64748b;
            }

            .jmt-guest-main {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 32px 16px 40px;
            }

            .jmt-guest-container {
                width: 100%;
                max-width: 480px;
            }

            .jmt-guest-footer {
                padding: 0 16px 20px;
                text-align: center;
                font-size: 11px;
                color: #94a3b8;
            }
        </style>
    </head>
    <body>
        <div class="jmt-guest-page">

            {{-- Brand --}}
            <div class="jmt-guest-brand">
                <div class="jmt-guest-brand-logo">
                    <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <p class="jmt-guest-brand-title">Absensi Ritase</p>
                <p class="jmt-guest-brand-sub">JMT Operasional</p>
            </div>

            {{-- Main content --}}
            <main class="jmt-guest-main">
                <div class="jmt-guest-container">
                    {{ $slot }}
                </div>
            </main>

            {{-- Footer --}}
            <div class="jmt-guest-footer">
                &copy; {{ date('Y') }} JMT · Sistem Absensi Ritase @by <a href="https://www.linkedin.com/in/awandasw" target="_blank">AwanTech - JMT</a>
            </div>

        </div>

        @livewireScripts
        <div x-data="{ show: false }"
            x-on:net-error.window="show = true; setTimeout(() => show = false, 5000)"
            x-show="show" x-cloak role="alert"
            style="position: fixed; top: 16px; left: 16px; right: 16px; z-index: 100; border-radius: 12px; background: #dc2626; padding: 16px; text-align: center; color: #fff; font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
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