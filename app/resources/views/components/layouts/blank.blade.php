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
            /* RESET */
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100vh;
                overflow: hidden;
            }

            /* LIVE SPLIT WRAPPER */
            .login-split {
                display: flex;
                flex-direction: row;
                width: 100%;
                height: 100vh;
                overflow: hidden;
                background: #f8fafc;
            }

            /* LEFT PANEL */
            .login-left {
                position: relative;
                width: 51%;
                flex-shrink: 0;
                height: 100vh;
                overflow: hidden;
                background: #0a1628;
                color: #ffffff;
            }

            .login-left-photo {
                position: absolute;
                inset: 0;
                background-image:
                    linear-gradient(180deg, rgba(10, 22, 40, 0.55) 0%, rgba(10, 22, 40, 0.78) 60%, rgba(10, 22, 40, 0.95) 100%),
                    url('https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1800&q=80');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                z-index: 0;
            }

            .login-left-content {
                position: relative;
                z-index: 3;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                height: 100vh;
                padding: 40px 56px;
                box-sizing: border-box;
            }

            .login-route-svg {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                z-index: 2;
                pointer-events: none;
            }

            /* RIGHT PANEL */
            .login-right {
                position: relative;
                flex: 1;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 32px;
                background: #f8fafc;
                box-sizing: border-box;
                overflow: hidden;
            }

            .login-right-accent-1 {
                position: absolute;
                top: -20%;
                right: -30%;
                width: 700px;
                height: 700px;
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0) 60%);
                transform: rotate(-30deg);
                pointer-events: none;
                z-index: 0;
            }

            .login-right-accent-2 {
                position: absolute;
                bottom: -30%;
                left: -20%;
                width: 500px;
                height: 500px;
                background: linear-gradient(135deg, rgba(59, 130, 246, 0.04) 0%, rgba(59, 130, 246, 0) 60%);
                transform: rotate(-30deg);
                pointer-events: none;
                z-index: 0;
            }

            .login-right-inner {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: 440px;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }

            /* INPUT */
            .login-input {
                display: block;
                width: 100%;
                box-sizing: border-box;
                height: 48px;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
                background: #ffffff;
                padding: 0 14px 0 42px;
                font-size: 15px;
                color: #0f172a;
                outline: none;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
                font-family: inherit;
            }
            .login-input::placeholder { color: #a3b0c2; }
            .login-input:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
            .login-input:disabled { opacity: 0.6; cursor: not-allowed; }
            .login-input-password { padding-right: 46px; }

            /* BUTTON */
            .login-btn {
                display: flex;
                width: 100%;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border-radius: 10px;
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                padding: 14px;
                font-size: 15px;
                font-weight: 600;
                color: #ffffff;
                border: 0;
                cursor: pointer;
                box-shadow: 0 10px 20px -8px rgba(37, 99, 235, 0.55);
                font-family: inherit;
            }
            .login-btn:hover:not([disabled]) { box-shadow: 0 12px 24px -8px rgba(37, 99, 235, 0.7); }
            .login-btn:active:not([disabled]) { transform: scale(0.99); }
            .login-btn[disabled] { opacity: 0.75; cursor: not-allowed; }
            .login-btn-text { display: inline-flex; align-items: center; gap: 8px; }
            .login-btn-loading { display: none; align-items: center; gap: 8px; white-space: nowrap; }
            .login-btn[disabled] .login-btn-text { display: none; }
            .login-btn[disabled] .login-btn-loading { display: inline-flex; }
            .login-btn-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                box-sizing: border-box;
                border: 2px solid rgba(255, 255, 255, 0.35);
                border-top-color: #ffffff;
                border-radius: 50%;
                animation: login-btn-spin 0.7s linear infinite;
                flex-shrink: 0;
            }
            @keyframes login-btn-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            /* MOBILE — balik ke auto height biar bisa scroll */
            @media (max-width: 1023px) {
                html, body {
                    height: auto;
                    overflow: auto;
                }

                .login-split {
                    flex-direction: column;
                    height: auto;
                    min-height: 100vh;
                    overflow: visible;
                    position: relative;
                }

                /* Panel kiri jadi background fixed fullscreen */
                .login-left {
                    display: block !important;
                    position: fixed;
                    inset: 0;
                    width: 100%;
                    height: 100vh;
                    z-index: 0;
                }

                /* Overlay gelap tambahan biar form tetap kebaca */
                .login-left::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: rgba(10, 22, 40, 0.65);
                    z-index: 4;
                    pointer-events: none;
                }

                /* Sembunyikan konten teks di panel kiri (brand, hero, features) */
                .login-left-content,
                .login-route-svg {
                    display: none !important;
                }

                /* Panel kanan di atas background */
                .login-right {
                    position: relative;
                    width: 100%;
                    height: auto;
                    min-height: 100vh;
                    padding: 32px 20px;
                    overflow: visible;
                    background: transparent !important;
                    z-index: 1;
                }

                /* Sembunyikan aksen geometris di mobile */
                .login-right-accent-1,
                .login-right-accent-2 {
                    display: none;
                }

                /* Footer text jadi putih biar kebaca di atas bg gelap */
                .login-right-inner > div:last-child {
                    color: #cbd5e1 !important;
                }
            }
        </style>
    </head>
    <body>
        {{ $slot }}
        @livewireScripts
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ fail }) => {
                    fail(() => window.dispatchEvent(new CustomEvent('net-error')));
                });
            });
        </script>
    </body>
</html>