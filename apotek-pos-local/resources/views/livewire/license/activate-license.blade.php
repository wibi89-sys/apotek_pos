<div class="py-10 px-4 flex items-center justify-center flex-1">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <!-- Card Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-5 text-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-xs flex items-center justify-center text-xl">
                    🔑
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight">Aktivasi Lisensi Apotek POS</h1>
                    <p class="text-xs text-indigo-100 mt-0.5">Aktivasi digital Ed25519 terikat Hardware ID (HWID)</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">
            <!-- Flash Errors & Messages -->
            @if(session('error'))
                <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            @if($errorMessage)
                <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs font-semibold">
                    <strong>Gagal Aktivasi:</strong> {{ $errorMessage }}
                </div>
            @endif

            @if($successMessage)
                <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold">
                    {{ $successMessage }}
                </div>
            @endif

            <!-- 1-Click Demo License Box -->
            <div class="p-4 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs">⚡</span>
                        <p class="text-xs font-black text-indigo-950 uppercase tracking-wider">Kunci Demo Siap Pakai (Paket PRO):</p>
                    </div>
                    <code class="text-xs font-mono font-black text-indigo-700 bg-white px-2 py-0.5 rounded border border-indigo-200 mt-1 inline-block">APT-DEMO-PRO-2026</code>
                    <p class="text-[11px] text-slate-500 mt-1">Lengkap: Biometrik U.are.U, Sync Cloud, Laba HPP, & SIPNAP.</p>
                </div>
                <button
                    type="button"
                    wire:click="fillDemoLicense"
                    class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold px-3.5 py-2 rounded-xl shadow-xs transition transform hover:-translate-y-0.5 self-start sm:self-auto cursor-pointer"
                >
                    ⚡ Pasang Otomatis
                </button>
            </div>

            <!-- Activation Form -->
            <form wire:submit="activate" class="space-y-4">
                <!-- Machine HWID -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Hardware ID Mesin Ini (HWID)</label>
                    <input
                        type="text"
                        readonly
                        value="{{ $currentHwid }}"
                        class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-mono text-slate-600 select-all cursor-text focus:outline-none"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">HWID dibentuk dari UUID Motherboard & Serial Drive PC kasir ini.</p>
                </div>

                <!-- License Key -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kunci Lisensi (License Key) *</label>
                    <input
                        type="text"
                        wire:model="licenseKey"
                        placeholder="Contoh: APT-DEMO-PRO-2026"
                        class="w-full uppercase font-mono font-bold tracking-wider bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                    >
                    @error('licenseKey') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Cloud Hub URL -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">URL Cloud Hub (Otentikasi) *</label>
                    <input
                        type="text"
                        wire:model="cloudHubUrl"
                        placeholder="http://127.0.0.1:8000"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2 text-xs font-mono text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                    >
                    @error('cloudHubUrl') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-md shadow-indigo-600/20 transition disabled:opacity-50 flex items-center justify-center cursor-pointer text-sm"
                    >
                        <span wire:loading.remove>🔐 Aktivasi Lisensi Sekarang</span>
                        <span wire:loading>Memvalidasi Sertifikat Digital...</span>
                    </button>
                </div>
            </form>

            <div class="pt-4 border-t border-slate-100 text-center text-[11px] text-slate-400 leading-relaxed">
                🔒 Sertifikat lisensi tersimpan terenkripsi di folder aman lokal. Setelah aktivasi, koneksi internet dapat diputus dan sistem berjalan 100% offline.
            </div>
        </div>
    </div>
</div>
