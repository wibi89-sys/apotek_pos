<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                    ← Master Obat
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-xs text-slate-500 font-medium">Form Input</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Tambah Master Obat & Multi-Satuan</h1>
            <p class="text-xs text-slate-500 mt-0.5">Daftarkan data produk farmasi, zat aktif BPOM, dan hierarki multi-satuan bertingkat</p>
        </div>
        <a href="{{ route('products.index') }}" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-2xs">
            Batal & Kembali
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- Card 1: Data Identitas Obat -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <div class="border-b border-slate-100 pb-3 flex items-center gap-2">
                <span class="text-lg">📋</span>
                <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Informasi Pokok Produk Farmasi</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Nama Dagang Obat -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Dagang Obat *</label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Contoh: Sanmol Forte 650mg"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                        autofocus
                    >
                    @error('name') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Zat Aktif / Generik -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Zat Aktif (Generik)</label>
                    <input
                        type="text"
                        wire:model="genericName"
                        placeholder="Contoh: Paracetamol 650mg"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                </div>

                <!-- SKU -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">SKU / Kode Unik *</label>
                    <input
                        type="text"
                        wire:model="sku"
                        placeholder="Contoh: MED-SAN-01"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                    @error('sku') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Barcode -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Barcode Kemasan Utama</label>
                    <input
                        type="text"
                        wire:model="barcode"
                        placeholder="Scan atau ketik barcode kemasan (misal: 899...)"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                </div>

                <!-- Golongan Obat BPOM -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Golongan Obat BPOM *</label>
                    <select wire:model="drugClass" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                        @foreach($drugClasses as $dc)
                            <option value="{{ $dc->value }}">{{ $dc->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Satuan Dasar Terkecil -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Satuan Dasar Terkecil (Base Unit) *</label>
                    <input
                        type="text"
                        wire:model="baseUnitName"
                        placeholder="Tablet / Kapsul / Botol / Tube / Ampul"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                    @error('baseUnitName') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Lokasi Rak Penyimpanan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Lokasi Rak / Lemari Obat</label>
                    <input
                        type="text"
                        wire:model="rackLocation"
                        placeholder="Contoh: Rak Generik A-01"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                </div>

                <!-- Minimum Stok Peringatan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Ambang Batas Minimum Stok (Base Unit)</label>
                    <input
                        type="number"
                        wire:model="minStockBase"
                        min="0"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                    <p class="text-[11px] text-slate-400 mt-1">Peringatan otomatis muncul di kasir jika total stok di bawah angka ini.</p>
                </div>
            </div>
        </div>

        <!-- Card 2: Multi-Satuan Bertingkat & Harga Jual -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-lg">⚖️</span>
                        <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Multi-Satuan Bertingkat & Harga Jual</h2>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Definisikan hierarki kemasan (misal Box -> Strip -> Tablet) dan faktor konversi ke satuan dasar.</p>
                </div>
                <button
                    type="button"
                    wire:click="addUnit"
                    class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition self-start sm:self-auto cursor-pointer"
                >
                    <span>+</span> Tambah Satuan Baru
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3">Nama Satuan Kemasan</th>
                            <th class="py-2.5 px-3 w-40">Konversi (x Satuan Dasar)</th>
                            <th class="py-2.5 px-3 w-48">Harga Jual Kasir (Rp)</th>
                            <th class="py-2.5 px-3 text-center w-28">Default POS</th>
                            <th class="py-2.5 px-3 text-center w-16">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($units as $idx => $unit)
                            <tr class="hover:bg-slate-50/70 transition">
                                <!-- Unit Name -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="text"
                                        wire:model="units.{{ $idx }}.unit_name"
                                        placeholder="Contoh: Tablet / Strip / Box"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                    @error('units.' . $idx . '.unit_name') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Conversion Factor -->
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-400 font-bold text-xs">x</span>
                                        <input
                                            type="number"
                                            wire:model="units.{{ $idx }}.conversion_factor"
                                            min="1"
                                            placeholder="1"
                                            class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono font-bold focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        >
                                    </div>
                                    @error('units.' . $idx . '.conversion_factor') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Sell Price -->
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-slate-400 font-bold text-xs">Rp</span>
                                        <input
                                            type="number"
                                            wire:model="units.{{ $idx }}.sell_price"
                                            min="0"
                                            step="100"
                                            placeholder="2500"
                                            class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono font-extrabold text-emerald-700 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        >
                                    </div>
                                    @error('units.' . $idx . '.sell_price') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Default POS Checkbox -->
                                <td class="py-2.5 px-3 text-center">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="units.{{ $idx }}.is_default_pos"
                                            class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500 cursor-pointer"
                                        >
                                        <span class="text-[11px] font-bold text-slate-600">POS</span>
                                    </label>
                                </td>

                                <!-- Action Delete -->
                                <td class="py-2.5 px-3 text-center">
                                    @if(count($units) > 1)
                                        <button
                                            type="button"
                                            wire:click="removeUnit({{ $idx }})"
                                            class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-xs transition inline-flex items-center justify-center cursor-pointer"
                                            title="Hapus satuan ini"
                                        >
                                            ✕
                                        </button>
                                    @else
                                        <span class="text-slate-300 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit & Cancel Buttons -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="{{ route('products.index') }}" class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition">
                Batal
            </a>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove>💾 Simpan Master Obat</span>
                <span wire:loading>Menyimpan Data...</span>
            </button>
        </div>
    </form>
</div>
