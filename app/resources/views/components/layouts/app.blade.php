<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Dasbor' }} — {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')

        <style>
            /* ===== SIDEBAR ===== */
            .jmt-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 256px;
                z-index: 40;
                background: #ffffff;
                border-right: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
                transform: translateX(-100%);
                transition: transform 0.2s ease-out, width 0.2s ease-out;
            }
            .jmt-sidebar.jmt-open {
                transform: translateX(0);
            }
            .jmt-sidebar.jmt-collapsed {
                width: 72px;
            }

            /* ===== HEADER ===== */
            .jmt-sidebar-header {
                height: 76px;
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0 20px;
                border-bottom: 1px solid #f1f5f9;
                flex-shrink: 0;
            }
            .jmt-sidebar.jmt-collapsed .jmt-sidebar-header {
                padding: 0 16px;
                justify-content: center;
            }

            .jmt-sidebar-logo {
                width: 40px;
                height: 40px;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
                color: #ffffff;
                box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.3);
            }
            .jmt-sidebar-logo svg {
                width: 20px;
                height: 20px;
            }

            .jmt-sidebar-brand {
                min-width: 0;
                flex: 1;
                overflow: hidden;
            }
            .jmt-sidebar-brand-title {
                margin: 0;
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.2;
            }
            .jmt-sidebar-brand-sub {
                margin: 2px 0 0;
                font-size: 12px;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.2;
            }
            .jmt-sidebar.jmt-collapsed .jmt-sidebar-brand {
                display: none;
            }

            /* ===== TOGGLE (hanya di layar >= 1024px) ===== */
            .jmt-sidebar-toggle {
                position: absolute;
                top: 24px;
                right: -14px;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 50;
                color: #475569;
                transition: transform 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
            }
            .jmt-sidebar-toggle:hover {
                color: #0f172a;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
                transform: scale(1.08);
            }
            .jmt-sidebar-toggle svg {
                width: 14px;
                height: 14px;
            }
            @media (max-width: 1023px) {
                .jmt-sidebar-toggle {
                    display: none !important;
                }
            }

            /* ===== NAV (no overflow, biar tooltip bisa keluar) ===== */
            .jmt-sidebar-nav {
                flex: 1;
                padding: 16px 12px;
            }

            .jmt-nav-link {
                position: relative;
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 12px;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                color: #475569;
                transition: background 0.15s ease, color 0.15s ease;
                margin-bottom: 4px;
                border: 0;
                background: transparent;
                cursor: pointer;
                font-family: inherit;
                text-align: left;
                width: 100%;
                box-sizing: border-box;
            }
            .jmt-nav-link:hover {
                background: #f8fafc;
                color: #0f172a;
            }
            .jmt-nav-link.jmt-active {
                background: #f1f5f9;
                color: #0f172a;
            }

            .jmt-nav-icon {
                width: 32px;
                height: 32px;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                color: #64748b;
                transition: background 0.15s ease, color 0.15s ease;
            }
            .jmt-nav-link.jmt-active .jmt-nav-icon {
                background: #ffffff;
                color: #0f172a;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            }
            .jmt-nav-icon svg {
                width: 16px;
                height: 16px;
            }

            .jmt-nav-text {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Panah dan submenu (sidebar lebar) */
            .jmt-chevron {
                margin-left: auto;
                width: 16px;
                height: 16px;
                flex-shrink: 0;
                color: #94a3b8;
                transition: transform 0.15s ease;
            }
            .jmt-chevron.jmt-rotated {
                transform: rotate(180deg);
            }
            .jmt-sidebar.jmt-collapsed .jmt-chevron {
                display: none;
            }
            .jmt-subnav {
                margin: 0 0 4px 28px;
                padding-left: 12px;
                border-left: 1px solid #e2e8f0;
            }
            .jmt-sublink {
                display: block;
                padding: 8px 12px;
                margin-bottom: 2px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                text-decoration: none;
                color: #64748b;
                transition: background 0.15s ease, color 0.15s ease;
            }
            .jmt-sublink:hover {
                background: #f8fafc;
                color: #0f172a;
            }
            .jmt-sublink.jmt-active {
                background: #f1f5f9;
                color: #0f172a;
                font-weight: 600;
            }

            /* Panel flyout submenu (sidebar collapsed, muncul saat hover) */
            .jmt-nav-group {
                position: relative;
            }
            .jmt-flyout {
                display: none;
                position: absolute;
                left: 100%;
                top: 0;
                padding-left: 12px; /* celah transparan supaya hover tidak putus */
                z-index: 100;
            }
            .jmt-flyout-box {
                min-width: 180px;
                padding: 8px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
            }
            .jmt-flyout-title {
                padding: 6px 12px 8px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: #94a3b8;
            }
            .jmt-sidebar.jmt-collapsed .jmt-nav-group:hover .jmt-flyout {
                display: block;
            }
            /* Tooltip bawaan dimatikan untuk menu induk, diganti judul di panel */
            .jmt-sidebar.jmt-collapsed .jmt-nav-group > .jmt-nav-link:hover::after,
            .jmt-sidebar.jmt-collapsed .jmt-nav-group > .jmt-nav-link:hover::before {
                display: none !important;
            }

            /* Collapsed state */
            .jmt-sidebar.jmt-collapsed .jmt-nav-link {
                justify-content: center;
                padding: 10px 0;
            }
            .jmt-sidebar.jmt-collapsed .jmt-nav-text {
                display: none;
            }

            /* Tooltip — sekarang bisa keluar karena nav gak ada overflow */
            .jmt-sidebar.jmt-collapsed .jmt-nav-link:hover::after {
                content: attr(data-label);
                position: absolute;
                left: calc(100% + 12px);
                top: 50%;
                transform: translateY(-50%);
                background: #0f172a;
                color: #ffffff;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                white-space: nowrap;
                pointer-events: none;
                z-index: 100;
                box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.3);
            }
            .jmt-sidebar.jmt-collapsed .jmt-nav-link:hover::before {
                content: '';
                position: absolute;
                left: calc(100% + 6px);
                top: 50%;
                transform: translateY(-50%);
                border: 6px solid transparent;
                border-right-color: #0f172a;
                pointer-events: none;
                z-index: 100;
            }

            /* ===== FOOTER SIDEBAR (logout) ===== */
            .jmt-sidebar-footer {
                padding: 12px;
                border-top: 1px solid #f1f5f9;
                flex-shrink: 0;
            }
            .jmt-logout-btn {
                color: #475569;
            }
            .jmt-logout-btn:hover {
                background: #fef2f2;
                color: #dc2626;
            }
            .jmt-logout-btn:hover .jmt-nav-icon {
                color: #dc2626;
            }

            /* ===== MAIN ===== */
            .jmt-main {
                min-height: 100vh;
                padding-left: 0;
                transition: padding-left 0.2s ease-out;
                display: flex;
                flex-direction: column;
            }

            /* ===== TOPBAR MOBILE ===== */
            .jmt-topbar-mobile {
                position: sticky;
                top: 0;
                z-index: 30;
                display: flex;
                align-items: center;
                justify-content: space-between;
                height: 76px;
                padding: 0 16px;
                background: #ffffff;
                border-bottom: 1px solid #e2e8f0;
            }

            /* ===== BACKDROP ===== */
            .jmt-backdrop {
                position: fixed;
                inset: 0;
                z-index: 35;
                background: rgba(15, 23, 42, 0.5);
                display: none;
            }
            .jmt-backdrop.jmt-open {
                display: block;
            }

            /* ===== BARU: >= 640px, sidebar selalu tampil sebagai strip ikon (72px),
                   tanpa hamburger. Di bawah 640px (HP) tetap laci + hamburger. ===== */
            @media (min-width: 640px) {
                .jmt-sidebar {
                    transform: translateX(0);
                }
                .jmt-main {
                    padding-left: 72px;
                }
                .jmt-topbar-mobile {
                    display: none;
                }
                .jmt-backdrop {
                    display: none !important;
                }
            }

            /* ===== BARU: >= 1024px, sidebar lebar (256px) kecuali diciutkan manual ===== */
            @media (min-width: 1024px) {
                .jmt-main {
                    padding-left: 256px;
                }
                .jmt-main.jmt-main-collapsed {
                    padding-left: 72px;
                }
            }

            /* ===== CONTENT ===== */
            .jmt-content {
                max-width: 1280px;
                margin: 0 auto;
                padding: 24px 16px;
                flex: 1;
                width: 100%;
                box-sizing: border-box;
            }
            @media (min-width: 640px) {
                .jmt-content {
                    padding: 32px 24px;
                }
            }

            /* ===== FOOTER ===== */
            .jmt-footer {
                border-top: 1px solid #e2e8f0;
                background: #ffffff;
                padding: 16px 24px;
                text-align: center;
                font-size: 12px;
                color: #94a3b8;
            }
            .jmt-footer a {
                color: #2563eb;
                text-decoration: none;
                font-weight: 600;
            }
            .jmt-footer a:hover {
                text-decoration: underline;
            }
        </style>
    </head>

    {{-- true kalau halaman aktif ada di bawah menu Pengaturan --}}
    @php($settingsActive = request()->routeIs('settings.*', 'users.*'))

    {{-- BARU: isWide (>=1024px) dan hasRail (>=640px).
         collapsed = true kalau:
           - layar 640-1023px (dipaksa strip ikon), atau
           - layar >= 1024px dan pengguna menciutkan sidebar sendiri.
         Di bawah 640px collapsed selalu false, jadi laci HP tampil penuh. --}}
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased"
          x-data="{
              sidebarOpen: false,
              sidebarCollapsed: localStorage.getItem('jmt-sidebar-collapsed') === '1',
              isWide: window.matchMedia('(min-width: 1024px)').matches,
              hasRail: window.matchMedia('(min-width: 640px)').matches,
              settingsOpen: {{ $settingsActive ? 'true' : 'false' }},
              get collapsed() {
                  return this.hasRail && (!this.isWide || this.sidebarCollapsed);
              },
              toggleSidebar() {
                  this.sidebarCollapsed = !this.sidebarCollapsed;
                  localStorage.setItem('jmt-sidebar-collapsed', this.sidebarCollapsed ? '1' : '0');
              }
          }"
          x-init="
              window.matchMedia('(min-width: 1024px)').addEventListener('change', e => isWide = e.matches);
              window.matchMedia('(min-width: 640px)').addEventListener('change', e => hasRail = e.matches);
          ">

        {{-- ===== MOBILE TOPBAR (hanya < 640px) ===== --}}
        <div class="jmt-topbar-mobile">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ config('app.name') }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()?->name }}</p>
                </div>
            </div>
            <button type="button" x-on:click="sidebarOpen = true" class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200">
                <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- ===== SIDEBAR ===== --}}
        <aside class="jmt-sidebar"
               :class="{ 'jmt-open': sidebarOpen, 'jmt-collapsed': collapsed }">

            {{-- Header --}}
            <div class="jmt-sidebar-header">
                <div class="jmt-sidebar-logo">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="jmt-sidebar-brand">
                    <p class="jmt-sidebar-brand-title">Absensi Ritase</p>
                    <p class="jmt-sidebar-brand-sub">{{ auth()->user()?->name }}</p>
                </div>
            </div>

            {{-- Toggle (hanya tampil di layar >= 1024px) --}}
            <button type="button"
                    class="jmt-sidebar-toggle"
                    x-on:click="toggleSidebar()"
                    title="Toggle sidebar">
                <svg x-show="!sidebarCollapsed" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <svg x-show="sidebarCollapsed" x-cloak fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            {{-- Nav --}}
            <nav class="jmt-sidebar-nav">

                <a href="{{ route('dashboard') }}"
                   data-label="Dashboard"
                   class="jmt-nav-link {{ request()->routeIs('dashboard') ? 'jmt-active' : '' }}">
                    <span class="jmt-nav-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </span>
                    <span class="jmt-nav-text">Dashboard</span>
                </a>

                <a href="{{ route('work-sessions.index') }}"
                   data-label="Sesi Kerja"
                   class="jmt-nav-link {{ request()->routeIs('work-sessions.*') ? 'jmt-active' : '' }}">
                    <span class="jmt-nav-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <span class="jmt-nav-text">Sesi Kerja</span>
                </a>

                {{-- Pengaturan = menu induk.
                     Sidebar lebar: klik = buka/tutup submenu.
                     Sidebar collapsed: hover = panel flyout, klik = ke halaman Umum. --}}
                <div class="jmt-nav-group">
                    <a href="{{ route('settings.edit') }}"
                       data-label="Pengaturan"
                       x-on:click="if (!collapsed) { $event.preventDefault(); settingsOpen = !settingsOpen }"
                       class="jmt-nav-link {{ $settingsActive ? 'jmt-active' : '' }}">
                        <span class="jmt-nav-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                        <span class="jmt-nav-text">Pengaturan</span>
                        <svg class="jmt-chevron" :class="{ 'jmt-rotated': settingsOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>

                    {{-- Submenu (sidebar lebar) --}}
                    <div class="jmt-subnav" x-show="settingsOpen && !collapsed" x-cloak>
                        <a href="{{ route('settings.edit') }}"
                           class="jmt-sublink {{ request()->routeIs('settings.*') ? 'jmt-active' : '' }}">
                            Umum
                        </a>
                        <a href="{{ route('users.index') }}"
                           class="jmt-sublink {{ request()->routeIs('users.*') ? 'jmt-active' : '' }}">
                            Pengguna
                        </a>
                    </div>

                    {{-- Panel flyout (sidebar collapsed, tampil saat hover lewat CSS) --}}
                    <div class="jmt-flyout">
                        <div class="jmt-flyout-box">
                            <p class="jmt-flyout-title">Pengaturan</p>
                            <a href="{{ route('settings.edit') }}"
                               class="jmt-sublink {{ request()->routeIs('settings.*') ? 'jmt-active' : '' }}">
                                Umum
                            </a>
                            <a href="{{ route('users.index') }}"
                               class="jmt-sublink {{ request()->routeIs('users.*') ? 'jmt-active' : '' }}">
                                Pengguna
                            </a>
                        </div>
                    </div>
                </div>

            </nav>

            {{-- Footer: Logout --}}
            <div class="jmt-sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            data-label="Keluar"
                            class="jmt-nav-link jmt-logout-btn"
                            style="margin-bottom: 0;">
                        <span class="jmt-nav-icon" style="color: inherit;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </span>
                        <span class="jmt-nav-text">Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- ===== BACKDROP ===== --}}
        <div class="jmt-backdrop" :class="{ 'jmt-open': sidebarOpen }" x-on:click="sidebarOpen = false"></div>

        {{-- ===== MAIN ===== --}}
        <main class="jmt-main" :class="{ 'jmt-main-collapsed': collapsed }">
            <div class="jmt-content">
                {{ $slot }}
            </div>

            <footer class="jmt-footer">
                &copy; {{ date('Y') }} JMT — Sistem Absensi Ritase · by
                <a href="https://www.linkedin.com/in/awandasw" target="_blank" rel="noopener">AwanTech - JMT</a>
            </footer>
        </main>

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