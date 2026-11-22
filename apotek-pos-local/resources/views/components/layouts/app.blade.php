<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Sistem POS Apotek Hybrid' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $currentTab = 'master';
    if (request()->routeIs(['pos.index', 'pos.shift'])) {
        $currentTab = 'kasir';
    } elseif (request()->routeIs(['products.*', 'doctors.*', 'suppliers.*', 'users.*'])) {
        $currentTab = 'master';
    } elseif (request()->routeIs(['purchases.*', 'reports.*'])) {
        $currentTab = 'laporan';
    } elseif (request()->routeIs(['settings.*', 'license.*'])) {
        $currentTab = 'pengaturan';
    }
@endphp
<body class="bg-slate-100 text-slate-800 antialiased font-sans min-h-full flex flex-col" x-data="{ mainTab: '{{ $currentTab }}', mobileMenuOpen: false }">
    @if (! request()->routeIs('pos.index'))
    <!-- Main Header Container (Sticky) -->
    <header class="sticky top-0 z-30 shadow-xs bg-white">
        <!-- TIER 1: Baris Menu Utama -->
        <div class="border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 gap-4">
                    <!-- Brand / Logo -->
                    <div class="flex items-center gap-4 shrink-0">
                        <a href="{{ route('pos.index') }}" class="flex items-center gap-2.5 group">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                                <span class="text-xl">💊</span>
                            </div>
                            <div class="leading-tight">
                                <span class="font-black text-slate-900 text-base tracking-tight block">APOTEK POS</span>
                                <span class="text-[10px] text-indigo-600 font-extrabold uppercase tracking-widest block">Hybrid System</span>
                            </div>
                        </a>
                    </div>

                    <!-- 4 Kelompok Menu Utama (Desktop) -->
                    <nav class="hidden md:flex items-center gap-2 text-xs">
                        <!-- 1. Kasir & Shift -->
                        <button
                            type="button"
                            @click="mainTab = 'kasir'"
                            :class="mainTab === 'kasir' ? 'bg-indigo-50 text-indigo-700 border-indigo-600 font-black shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold border-transparent'"
                            class="px-3.5 py-2 rounded-xl border transition flex items-center gap-2 cursor-pointer"
                        >
                            <span class="text-base">🛒</span>
                            <span>Kasir & Shift</span>
                            @if(request()->routeIs(['pos.*']))
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </button>

                        <!-- 2. Kelompok Master -->
                        <button
                            type="button"
                            @click="mainTab = 'master'"
                            :class="mainTab === 'master' ? 'bg-indigo-50 text-indigo-700 border-indigo-600 font-black shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold border-transparent'"
                            class="px-3.5 py-2 rounded-xl border transition flex items-center gap-2 cursor-pointer"
                        >
                            <span class="text-base">📦</span>
                            <span>Kelompok Master</span>
                            @if(request()->routeIs(['products.*', 'doctors.*', 'suppliers.*', 'users.*']))
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </button>

                        <!-- 3. Pengadaan & Laporan -->
                        <button
                            type="button"
                            @click="mainTab = 'laporan'"
                            :class="mainTab === 'laporan' ? 'bg-indigo-50 text-indigo-700 border-indigo-600 font-black shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold border-transparent'"
                            class="px-3.5 py-2 rounded-xl border transition flex items-center gap-2 cursor-pointer"
                        >
                            <span class="text-base">📑</span>
                            <span>Laporan & PBF</span>
                            @if(request()->routeIs(['purchases.*', 'reports.*']))
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </button>

                        <!-- 4. Pengaturan Sistem -->
                        <button
                            type="button"
                            @click="mainTab = 'pengaturan'"
                            :class="mainTab === 'pengaturan' ? 'bg-indigo-50 text-indigo-700 border-indigo-600 font-black shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold border-transparent'"
                            class="px-3.5 py-2 rounded-xl border transition flex items-center gap-2 cursor-pointer"
                        >
                            <span class="text-base">⚙️</span>
                            <span>Pengaturan</span>
                            @if(request()->routeIs(['settings.*', 'license.*']))
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </button>
                    </nav>

                    <!-- Right Badges & Mobile Hamburger Button -->
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('license.info') }}" class="text-xs bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold px-2.5 py-1.5 rounded-lg flex items-center gap-1.5 transition shadow-2xs" title="Informasi Lisensi Sistem">
                            <span>🔑</span> <span class="hidden sm:inline">Lisensi</span>
                        </a>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200" title="Terminal ID POS">
                            {{ config('license.terminal_id', 'POS01') }}
                        </span>

                        <!-- Mobile Hamburger Button -->
                        <button
                            type="button"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none cursor-pointer"
                        >
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': mobileMenuOpen, 'inline-flex': !mobileMenuOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': !mobileMenuOpen, 'inline-flex': mobileMenuOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TIER 2: Baris Sub-Menu Berjejer (Ribbon Bar) -->
        <div class="bg-slate-50/90 border-b border-slate-200 backdrop-blur-xs py-2">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Sub-Menu Kategori 1: Kasir & Shift -->
                <div x-show="mainTab === 'kasir'" x-cloak class="flex items-center gap-1.5 overflow-x-auto text-xs scrollbar-none py-0.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 mr-2 flex items-center gap-1">
                        <span>🛒</span> <span>Menu Kasir:</span>
                    </span>
                    <a
                        href="{{ route('pos.index') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('pos.index') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        🛒 Buka Layar Kasir POS
                    </a>
                    <a
                        href="{{ route('pos.shift') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('pos.shift') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        ⏱️ Manajemen Shift & Blind Closing
                    </a>
                </div>

                <!-- Sub-Menu Kategori 2: Kelompok Master Data -->
                <div x-show="mainTab === 'master'" x-cloak class="flex items-center gap-1.5 overflow-x-auto text-xs scrollbar-none py-0.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 mr-2 flex items-center gap-1">
                        <span>📦</span> <span>Kelompok Master:</span>
                    </span>
                    <a
                        href="{{ route('products.index') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('products.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        💊 Master Obat & Stok (3.810 Item)
                    </a>
                    <a
                        href="{{ route('doctors.index') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('doctors.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        🩺 Data Dokter Resep
                    </a>
                    <a
                        href="{{ route('suppliers.index') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('suppliers.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        🏢 Distributor & PBF Obat
                    </a>
                    <a
                        href="{{ route('users.index') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('users.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        👥 Pegawai & Hak Akses Kasir
                    </a>
                </div>

                <!-- Sub-Menu Kategori 3: Pengadaan & Laporan -->
                <div x-show="mainTab === 'laporan'" x-cloak class="flex items-center gap-1.5 overflow-x-auto text-xs scrollbar-none py-0.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 mr-2 flex items-center gap-1">
                        <span>📑</span> <span>Laporan & Pengadaan:</span>
                    </span>
                    <a
                        href="{{ route('purchases.create') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('purchases.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        📥 Penerimaan Faktur PBF
                    </a>
                    <a
                        href="{{ route('reports.profit') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('reports.profit') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        📊 Laporan Laba HPP (PRO)
                    </a>
                    <a
                        href="{{ route('reports.sipnap') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('reports.sipnap') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        📋 Laporan SIPNAP BPOM (PRO)
                    </a>
                </div>

                <!-- Sub-Menu Kategori 4: Pengaturan Sistem -->
                <div x-show="mainTab === 'pengaturan'" x-cloak class="flex items-center gap-1.5 overflow-x-auto text-xs scrollbar-none py-0.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 mr-2 flex items-center gap-1">
                        <span>⚙️</span> <span>Sistem & Hardware:</span>
                    </span>
                    <a
                        href="{{ route('settings.database') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('settings.database*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        💾 Backup & Restore Database
                    </a>
                    <a
                        href="{{ route('settings.fingerprint') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('settings.fingerprint') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        👆 Registrasi Sidik Jari U.are.U
                    </a>
                    <a
                        href="{{ route('settings.sync') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('settings.sync') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        ☁️ Monitor Sinkronisasi Cloud
                    </a>
                    <a
                        href="{{ route('license.info') }}"
                        class="px-3 py-1.5 rounded-xl transition whitespace-nowrap {{ request()->routeIs('license.*') ? 'bg-indigo-600 text-white font-extrabold shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border border-slate-200/80 shadow-2xs' }}"
                    >
                        🔑 Manajemen Lisensi
                    </a>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer (Responsif untuk HP / Tablet) -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-4 text-xs font-medium shadow-xl max-h-[85vh] overflow-y-auto">
            <!-- Grup Kasir -->
            <div>
                <div class="font-extrabold text-slate-400 text-[10px] uppercase tracking-wider mb-1">🛒 Kasir & Shift</div>
                <div class="space-y-1">
                    <a href="{{ route('pos.index') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('pos.index') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        🛒 Buka Layar Kasir POS
                    </a>
                    <a href="{{ route('pos.shift') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('pos.shift') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        ⏱️ Shift Kasir & Modal Awal
                    </a>
                </div>
            </div>

            <!-- Grup Master Data -->
            <div>
                <div class="font-extrabold text-slate-400 text-[10px] uppercase tracking-wider mb-1">📦 Kelompok Master Data</div>
                <div class="space-y-1">
                    <a href="{{ route('products.index') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('products.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        💊 Master Obat & Stok
                    </a>
                    <a href="{{ route('doctors.index') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('doctors.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        🩺 Data Dokter Resep
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('suppliers.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        🏢 Distributor & PBF Obat
                    </a>
                    <a href="{{ route('users.index') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        👥 Pegawai & Hak Akses Kasir
                    </a>
                </div>
            </div>

            <!-- Grup Laporan -->
            <div>
                <div class="font-extrabold text-slate-400 text-[10px] uppercase tracking-wider mb-1">📑 Pengadaan & Laporan</div>
                <div class="space-y-1">
                    <a href="{{ route('purchases.create') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('purchases.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        📥 Penerimaan Faktur PBF
                    </a>
                    <a href="{{ route('reports.profit') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('reports.profit') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        📊 Laporan Laba HPP (PRO)
                    </a>
                    <a href="{{ route('reports.sipnap') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('reports.sipnap') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        📋 Laporan SIPNAP BPOM (PRO)
                    </a>
                </div>
            </div>

            <!-- Grup Pengaturan -->
            <div>
                <div class="font-extrabold text-slate-400 text-[10px] uppercase tracking-wider mb-1">⚙️ Pengaturan & Sistem</div>
                <div class="space-y-1">
                    <a href="{{ route('settings.database') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('settings.database*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        💾 Backup & Restore Database
                    </a>
                    <a href="{{ route('settings.fingerprint') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('settings.fingerprint') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        👆 Registrasi Sidik Jari U.are.U
                    </a>
                    <a href="{{ route('settings.sync') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('settings.sync') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        ☁️ Monitor Sinkronisasi Cloud
                    </a>
                    <a href="{{ route('license.info') }}" class="block px-3 py-2 rounded-xl {{ request()->routeIs('license.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        🔑 Informasi Lisensi
                    </a>
                </div>
            </div>
        </div>
    </header>
    @endif

    <main class="flex-1 flex flex-col min-h-0">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
