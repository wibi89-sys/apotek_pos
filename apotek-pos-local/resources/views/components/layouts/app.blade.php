<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sistem POS Apotek Hybrid' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-800 antialiased font-sans min-h-full flex flex-col" x-data="{ mobileMenuOpen: false }">
    @if (! request()->routeIs('pos.index'))
    <!-- Main Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-4 shrink-0">
                    <a href="{{ route('pos.index') }}" class="flex items-center gap-2 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                            <span class="text-xl">💊</span>
                        </div>
                        <div class="leading-tight">
                            <span class="font-black text-slate-900 text-base tracking-tight block">APOTEK POS</span>
                            <span class="text-[10px] text-indigo-600 font-extrabold uppercase tracking-widest block">Hybrid System</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Nav Links (Horizontal Scrollable on Medium, Spacious on Large) -->
                <nav class="hidden md:flex items-center gap-1 overflow-x-auto py-1 text-xs font-semibold scrollbar-none">
                    <a href="{{ route('pos.index') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('pos.index') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        🛒 Kasir POS
                    </a>
                    <a href="{{ route('pos.shift') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('pos.shift') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        ⏱️ Shift
                    </a>
                    <a href="{{ route('products.index') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('products.*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        📦 Master Obat
                    </a>
                    <a href="{{ route('doctors.index') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('doctors.*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        🩺 Dokter
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('suppliers.*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        🏢 Supplier
                    </a>
                    <a href="{{ route('users.index') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('users.*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        👥 Pegawai
                    </a>
                    <a href="{{ route('purchases.create') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('purchases.*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        📥 Faktur PBF
                    </a>
                    <a href="{{ route('reports.profit') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('reports.profit') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        📊 Laba HPP
                    </a>
                    <a href="{{ route('reports.sipnap') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('reports.sipnap') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        📋 SIPNAP
                    </a>
                    <a href="{{ route('settings.database') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('settings.database*') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        💾 Backup
                    </a>
                    <a href="{{ route('settings.sync') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('settings.sync') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        ☁️ Sync
                    </a>
                    <a href="{{ route('settings.fingerprint') }}" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ request()->routeIs('settings.fingerprint') ? 'bg-indigo-600 text-white font-bold shadow-xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                        👆 U.are.U
                    </a>
                </nav>

                <!-- Right Side Badges & Mobile Menu Button -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('license.info') }}" class="text-xs bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold px-2.5 py-1.5 rounded-lg flex items-center gap-1.5 transition shadow-2xs">
                        <span>🔑</span> <span class="hidden sm:inline">Lisensi</span>
                    </a>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ config('license.terminal_id', 'POS01') }}
                    </span>

                    <!-- Mobile Hamburger Button -->
                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none"
                    >
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': mobileMenuOpen, 'inline-flex': !mobileMenuOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': !mobileMenuOpen, 'inline-flex': mobileMenuOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Drawer (Smooth Slide Down) -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-t border-slate-200 bg-white px-4 pt-2 pb-4 space-y-1 text-sm font-medium shadow-lg">
            <a href="{{ route('pos.index') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('pos.index') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                🛒 Kasir POS
            </a>
            <a href="{{ route('pos.shift') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('pos.shift') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                ⏱️ Shift Kasir
            </a>
            <a href="{{ route('products.index') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('products.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                📦 Master Obat & Inventori
            </a>
            <a href="{{ route('doctors.index') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('doctors.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                🩺 Data Dokter Resep
            </a>
            <a href="{{ route('suppliers.index') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('suppliers.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                🏢 Distributor & PBF
            </a>
            <a href="{{ route('users.index') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                👥 Pegawai & Hak Akses Kasir
            </a>
            <a href="{{ route('purchases.create') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('purchases.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                📥 Penerimaan Faktur PBF
            </a>
            <a href="{{ route('reports.profit') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('reports.profit') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                📊 Laporan Laba HPP (PRO)
            </a>
            <a href="{{ route('reports.sipnap') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('reports.sipnap') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                📋 Laporan SIPNAP BPOM (PRO)
            </a>
            <a href="{{ route('settings.database') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('settings.database*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                💾 Backup & Restore Database
            </a>
            <a href="{{ route('settings.sync') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('settings.sync') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                ☁️ Sinkronisasi Cloud Hub
            </a>
            <a href="{{ route('settings.fingerprint') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('settings.fingerprint') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                👆 Registrasi Biometrik U.are.U
            </a>
            <a href="{{ route('license.info') }}" class="block px-3 py-2 rounded-lg {{ request()->routeIs('license.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                🔑 Informasi Lisensi
            </a>
        </div>
    </header>
    @endif

    <main class="flex-1 flex flex-col min-h-0">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
