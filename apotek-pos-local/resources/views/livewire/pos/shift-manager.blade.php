<div class="max-w-2xl mx-auto py-10 px-4 sm:px-6">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <!-- Header Card -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 px-6 py-5 text-white flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl">
                    ⏱️
                </div>
                <div>
                    <h1 class="text-lg font-black tracking-tight">Manajemen Shift Kasir</h1>
                    <p class="text-xs text-slate-400">Terminal: <span class="font-mono text-indigo-400 font-bold">{{ config('license.terminal_id', 'POS01') }}</span> | Cabang: {{ config('license.branch_id', 'BR01') }}</p>
                </div>
            </div>
            @if($activeShift)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Shift Aktif
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span> Shift Tertutup
                </span>
            @endif
        </div>

        <!-- Flash Notifications -->
        @if(session('success'))
            <div class="m-6 mb-0 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold">
                {{ session('success') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="m-6 mb-0 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-xs font-semibold">
                {{ session('warning') }}
            </div>
        @endif
        @if($errorMessage)
            <div class="m-6 mb-0 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-semibold">
                {{ $errorMessage }}
            </div>
        @endif

        <div class="p-6">
            @if(! $activeShift)
                <!-- BUKA SHIFT BARU -->
                <form wire:submit="openShift" class="space-y-5">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900">Buka Shift Kasir Baru</h2>
                        <p class="text-xs text-slate-500 mt-1">Masukkan jumlah modal kasir awal (uang receh kembalian di laci kasir).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Modal Awal Kasir (Rp) *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-mono font-bold text-sm">
                                Rp
                            </span>
                            <input
                                type="number"
                                wire:model="openingCash"
                                min="0"
                                step="1000"
                                class="w-full pl-11 pr-4 py-3 text-xl font-mono font-black text-slate-900 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                placeholder="100000"
                                autofocus
                            >
                        </div>
                        @error('openingCash') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl text-sm shadow-md shadow-indigo-600/20 transition cursor-pointer flex items-center justify-center gap-2">
                            <span>🚀</span> Buka Shift Sekarang & Masuk Kasir POS
                        </button>
                    </div>
                </form>
            @else
                <!-- INFO SHIFT AKTIF & BLIND CLOSING -->
                <div class="space-y-6">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">Waktu Buka Shift</div>
                            <div class="text-sm font-black font-mono text-slate-800 mt-0.5">{{ $activeShift->opened_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">Modal Awal</div>
                            <div class="text-sm font-black font-mono text-slate-800 mt-0.5">Rp {{ number_format((float) $activeShift->opening_cash, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">Kasir Bertugas</div>
                            <div class="text-sm font-bold text-indigo-700 mt-0.5">{{ auth()->user()?->name ?? 'Kasir Terminal' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] text-slate-400 uppercase font-bold tracking-wider">Total Transaksi</div>
                            <div class="text-sm font-black font-mono text-slate-800 mt-0.5">{{ $activeShift->transactions()->count() }} Nota</div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('pos.index') }}" class="flex-1 text-center bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-3 px-4 rounded-xl shadow-xs transition flex items-center justify-center gap-2">
                            <span>🛒</span> Kembali ke Layar Kasir POS
                        </a>
                    </div>

                    <!-- BLIND CLOSING TUTUP SHIFT -->
                    <div class="pt-5 border-t border-slate-200 space-y-3">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">Tutup Shift Kasir (Blind Closing)</h3>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                                Hitung seluruh uang tunai fisik yang ada di laci kasir saat ini, lalu masukkan jumlah totalnya di bawah ini:
                            </p>
                        </div>

                        <form wire:submit="closeShift" class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Total Uang Fisik di Laci Kasir (Rp) *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-mono font-bold text-sm">
                                        Rp
                                    </span>
                                    <input
                                        type="number"
                                        wire:model="actualCashClosing"
                                        min="0"
                                        step="1000"
                                        class="w-full pl-11 pr-4 py-3 text-xl font-mono font-black text-slate-900 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-rose-500 focus:outline-none transition"
                                        placeholder="Contoh: 250000"
                                    >
                                </div>
                                @error('actualCashClosing') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            <button
                                type="submit"
                                onclick="return confirm('Apakah Anda yakin ingin menutup shift kasir saat ini?');"
                                class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 px-4 rounded-xl text-xs shadow-md shadow-rose-600/20 transition cursor-pointer flex items-center justify-center gap-2"
                            >
                                <span>🔒</span> Konfirmasi & Tutup Shift Kasir
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
