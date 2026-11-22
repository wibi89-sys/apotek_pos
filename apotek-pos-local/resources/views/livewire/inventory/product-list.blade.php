<div x-data="{ showImportModal: false }" class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Session Alerts -->
    @if (session('success'))
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-2xl shadow-2xs flex items-center gap-3">
            <span class="text-emerald-600 font-bold text-lg">✓</span>
            <p class="text-xs text-emerald-800 font-bold">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-2xl shadow-2xs flex items-center gap-3">
            <span class="text-rose-600 font-bold text-lg">✕</span>
            <p class="text-xs text-rose-800 font-bold">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">📦</span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Master Obat & Inventori Multi-Satuan</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">Pencarian fulltext terindeks FEFO (< 50ms) dengan pemantauan kedaluwarsa BPOM</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Export Excel Button -->
            <a href="{{ route('products.export') }}" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-xs transition" title="Unduh seluruh database obat ke format Excel Murni (.xlsx)">
                <span>📊</span> <span>Export Excel (.xlsx)</span>
            </a>

            <!-- Import Excel Button -->
            <button
                type="button"
                @click="showImportModal = true"
                class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-xs transition cursor-pointer"
            >
                <span>📥</span> <span>Import Excel</span>
            </button>

            <a href="{{ route('purchases.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-xs transition">
                <span>📑</span> <span class="hidden md:inline">+ Faktur PBF</span>
            </a>

            <a href="{{ route('products.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-xs transition">
                <span>💊</span> <span>+ Tambah Obat</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-slate-200/80 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <!-- Search Bar (7 Cols) -->
            <div class="md:col-span-7">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Cari Obat / Zat Aktif / SKU / Barcode:</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                        🔍
                    </span>
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        placeholder="Ketik minimal 2 huruf (contoh: Paracetamol, Amoxicillin, Sanmol, atau scan barcode)..."
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition"
                        autofocus
                    >
                </div>
            </div>

            <!-- Filter Golongan (3 Cols) -->
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Golongan Obat:</label>
                <select wire:model.live="selectedDrugClass" class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                    <option value="">Semua Golongan Obat</option>
                    @foreach($drugClasses as $dc)
                        <option value="{{ $dc->value }}">{{ $dc->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Per Page (2 Cols) -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tampilkan:</label>
                <select wire:model.live="perPage" class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                    <option value="15">15 Baris</option>
                    <option value="25">25 Baris</option>
                    <option value="50">50 Baris</option>
                    <option value="100">100 Baris</option>
                </select>
            </div>
        </div>

        <!-- Result Stats Counter -->
        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs text-slate-500">
            <div>
                Menampilkan <strong class="text-slate-800">{{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }}</strong> dari total <strong class="text-indigo-600">{{ number_format($products->total()) }}</strong> obat terdaftar
            </div>
            @if($search || $selectedDrugClass)
                <button
                    wire:click="$set('search', ''); $set('selectedDrugClass', '')"
                    class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1 cursor-pointer"
                >
                    ✕ Reset Filter Pencarian
                </button>
            @endif
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3.5 px-4 w-12 text-center">#</th>
                        <th class="py-3.5 px-4">Nama Produk & Zat Aktif</th>
                        <th class="py-3.5 px-4 w-36">Golongan BPOM</th>
                        <th class="py-3.5 px-4">Multi-Satuan & Harga Jual</th>
                        <th class="py-3.5 px-4 text-center w-48">Batch Terdekat (FEFO)</th>
                        <th class="py-3.5 px-4 text-right w-36">Total Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $idx => $p)
                        <tr class="hover:bg-indigo-50/30 transition">
                            <!-- Index -->
                            <td class="py-3.5 px-4 text-center font-mono text-slate-400">
                                {{ $products->firstItem() + $idx }}
                            </td>

                            <!-- Product Name & Generic -->
                            <td class="py-3.5 px-4">
                                <div class="font-extrabold text-slate-900 text-sm leading-snug">{{ $p->name }}</div>
                                <div class="text-slate-500 text-xs mt-0.5">{{ $p->generic_name ?? '-' }}</div>
                                <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono mt-1">
                                    <span class="bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">SKU: {{ $p->sku }}</span>
                                    @if($p->barcode)
                                        <span class="bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">Barcode: {{ $p->barcode }}</span>
                                    @endif
                                    @if($p->rack_location)
                                        <span class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200">Rak: {{ $p->rack_location }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Drug Class Badge -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $p->drug_class->badgeColor() }}">
                                    {{ $p->drug_class->label() }}
                                </span>
                            </td>

                            <!-- Multi Units -->
                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($p->units as $u)
                                        <div class="inline-flex items-center gap-1.5 text-[11px] bg-slate-50 border border-slate-200 px-2 py-1 rounded-lg">
                                            <span class="font-bold text-slate-700">{{ $u->unit_name }} <span class="text-slate-400 font-mono font-normal">(x{{ $u->conversion_factor }})</span>:</span>
                                            <span class="text-emerald-700 font-extrabold font-mono">Rp {{ number_format((float) $u->sell_price, 0, ',', '.') }}</span>
                                            @if($u->is_default_pos)
                                                <span class="bg-indigo-600 text-white font-bold text-[9px] px-1 py-0.2 rounded uppercase">POS</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <!-- Nearest Batch FEFO -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @php $nearestBatch = $p->batches->first(); @endphp
                                @if($nearestBatch)
                                    <div class="inline-block text-left p-1.5 bg-slate-50 border border-slate-200 rounded-lg">
                                        <div class="font-mono font-bold text-slate-800 text-[11px]">{{ $nearestBatch->batch_number }}</div>
                                        @php $daysLeft = (int) now()->diffInDays($nearestBatch->expired_date, false); @endphp
                                        <div class="text-[10px] font-semibold {{ $daysLeft < 90 ? 'text-rose-600' : 'text-slate-500' }}">
                                            ED: {{ $nearestBatch->expired_date->format('d/m/Y') }} ({{ $nearestBatch->stock_base_unit }} {{ $p->base_unit_name }})
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Stok Habis
                                    </span>
                                @endif
                            </td>

                            <!-- Total Stock Base -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                @php $total = $p->batches->sum('stock_base_unit'); @endphp
                                <div class="font-black font-mono text-base {{ $total <= $p->min_stock_base ? 'text-rose-600' : 'text-slate-900' }}">
                                    {{ number_format($total) }}
                                </div>
                                <span class="text-slate-500 text-[11px] font-semibold">{{ $p->base_unit_name }}</span>
                                @if($total <= $p->min_stock_base)
                                    <div class="text-[10px] text-rose-500 font-bold">⚠️ Di bawah min stok ({{ $p->min_stock_base }})</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-slate-400">
                                <div class="text-4xl mb-2">🔍</div>
                                <p class="text-sm font-bold text-slate-600">Tidak ada obat yang cocok</p>
                                <p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci pencarian yang lain atau ubah filter golongan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Links -->
        <div class="p-4 border-t border-slate-200 bg-slate-50/50">
            {{ $products->links() }}
        </div>
    </div>

    <!-- Modal Import Excel / CSV -->
    <div
        x-show="showImportModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            @click.away="showImportModal = false"
            class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 border border-slate-200 relative transform transition-all"
        >
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">📥</span>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Import Master Data Obat</h3>
                        <p class="text-xs text-slate-500">Unggah file Excel (.xls, .xlsx) atau CSV</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="showImportModal = false"
                    class="text-slate-400 hover:text-slate-600 font-bold text-lg p-1 cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-xs text-indigo-900 space-y-2">
                    <div class="font-bold flex items-center justify-between flex-wrap gap-2">
                        <span>💡 Belum punya format tabel yang sesuai?</span>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('products.template') }}" class="inline-flex items-center gap-1 text-indigo-700 hover:text-indigo-900 font-extrabold underline bg-indigo-100/60 hover:bg-indigo-100 px-2 py-1 rounded-md">
                                📗 Template Excel (.xlsx)
                            </a>
                            <a href="{{ route('products.template.csv') }}" class="text-slate-500 hover:text-slate-700 text-[11px] underline">
                                (CSV)
                            </a>
                        </div>
                    </div>
                    <p class="text-[11px] text-indigo-800 leading-relaxed">
                        Mendukung multi-satuan bertingkat (Beli, Satuan 1-4, Rasio/Isi), harga eceran & resep, tanggal expired FEFO, serta pengelompokan golongan obat otomatis. Disertai sheet petunjuk pengisian di dalam file Excel.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Pilih File (.xls, .xlsx, atau .csv)
                    </label>
                    <input
                        type="file"
                        name="file"
                        accept=".csv, .xls, .xlsx, text/csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                        class="block w-full text-xs text-slate-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl cursor-pointer bg-slate-50 p-1"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="showImportModal = false"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 text-xs font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>🚀</span> <span>Mulai Import</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
