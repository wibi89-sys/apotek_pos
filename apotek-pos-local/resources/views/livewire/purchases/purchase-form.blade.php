<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                    ← Master Obat
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-xs text-slate-500 font-medium">Faktur PBF</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-1">Penerimaan Faktur Pembelian (PBF)</h1>
            <p class="text-xs text-slate-500 mt-0.5">Pencatatan nomor batch, expired date, konversi multi-satuan, dan pembaruan HPP otomatis</p>
        </div>
        <a href="{{ route('products.index') }}" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-2xs">
            Batal & Kembali
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- Card 1: Data Header Faktur -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3 flex items-center gap-2">
                <span class="text-lg">📄</span>
                <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Identitas Faktur Distributor / PBF</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nomor Faktur PBF *</label>
                    <input
                        type="text"
                        wire:model="invoiceNo"
                        placeholder="Contoh: FAK-PBF-2026-001"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                        autofocus
                    >
                    @error('invoiceNo') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Distributor / PBF *</label>
                    <select wire:model="supplierId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                        <option value="">-- Pilih Supplier PBF --</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->code }})</option>
                        @endforeach
                    </select>
                    @error('supplierId') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tanggal Faktur</label>
                    <input
                        type="date"
                        wire:model="purchaseDate"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                    @error('purchaseDate') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jatuh Tempo Pembayaran</label>
                    <input
                        type="date"
                        wire:model="dueDate"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                    >
                </div>
            </div>
        </div>

        <!-- Card 2: Detail Obat, Batch & Harga Beli -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📦</span>
                        <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Daftar Obat, Nomor Batch & Tanggal Expired</h2>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Setiap baris otomatis menambah stok batch dan mencatat mutasi masuk terindeks FEFO</p>
                </div>
                <button
                    type="button"
                    wire:click="addItem"
                    class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition self-start sm:self-auto cursor-pointer"
                >
                    <span>+</span> Tambah Baris Obat
                </button>
            </div>

            <!-- Table Lines -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-3 w-10 text-center">#</th>
                            <th class="py-2.5 px-3 min-w-[200px]">Pilih Produk Obat *</th>
                            <th class="py-2.5 px-3 w-36">No. Batch *</th>
                            <th class="py-2.5 px-3 w-36">Expired Date *</th>
                            <th class="py-2.5 px-3 w-28">Satuan</th>
                            <th class="py-2.5 px-3 w-24">Konversi</th>
                            <th class="py-2.5 px-3 w-24">Qty Beli</th>
                            <th class="py-2.5 px-3 w-32">Harga Beli (Rp)</th>
                            <th class="py-2.5 px-3 w-32 text-right">Subtotal (Rp)</th>
                            <th class="py-2.5 px-3 w-12 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $invoiceGrandTotal = '0'; @endphp
                        @foreach($items as $idx => $item)
                            @php
                                $lineQty = (string) ($item['qty_input'] ?? '0');
                                $linePrice = (string) ($item['buy_price_unit'] ?? '0');
                                $lineSubtotal = bcmul($lineQty, $linePrice, 2);
                                $invoiceGrandTotal = bcadd($invoiceGrandTotal, $lineSubtotal, 2);
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 text-center font-mono text-slate-400">{{ $idx + 1 }}</td>

                                <!-- Product Select -->
                                <td class="py-2.5 px-3">
                                    <select wire:model.live="items.{{ $idx }}.product_id" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                        <option value="">-- Pilih Obat --</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('items.' . $idx . '.product_id') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Batch Number -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="text"
                                        wire:model="items.{{ $idx }}.batch_number"
                                        placeholder="BATCH-001"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-mono uppercase focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                    @error('items.' . $idx . '.batch_number') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Expired Date -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="date"
                                        wire:model="items.{{ $idx }}.expired_date"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                    @error('items.' . $idx . '.expired_date') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </td>

                                <!-- Satuan Kemasan -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="text"
                                        wire:model="items.{{ $idx }}.unit_name"
                                        placeholder="Box"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                </td>

                                <!-- Konversi -->
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-400 font-mono text-[10px]">x</span>
                                        <input
                                            type="number"
                                            wire:model="items.{{ $idx }}.conversion_factor"
                                            min="1"
                                            placeholder="100"
                                            class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-mono focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        >
                                    </div>
                                </td>

                                <!-- Qty Beli -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="number"
                                        wire:model.live="items.{{ $idx }}.qty_input"
                                        min="1"
                                        placeholder="1"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-mono font-bold focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none text-center"
                                    >
                                </td>

                                <!-- Harga Beli Satuan -->
                                <td class="py-2.5 px-3">
                                    <input
                                        type="number"
                                        wire:model.live="items.{{ $idx }}.buy_price_unit"
                                        min="0"
                                        step="100"
                                        placeholder="50000"
                                        class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                </td>

                                <!-- Line Subtotal -->
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700 whitespace-nowrap">
                                    Rp {{ number_format((float) $lineSubtotal, 0, ',', '.') }}
                                </td>

                                <!-- Action -->
                                <td class="py-2.5 px-3 text-center">
                                    @if(count($items) > 1)
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $idx }})"
                                            class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-xs transition inline-flex items-center justify-center cursor-pointer"
                                            title="Hapus baris obat ini"
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
                    <tfoot>
                        <tr class="bg-slate-50/80 font-bold border-t-2 border-slate-200">
                            <td colspan="8" class="py-3 px-4 text-right uppercase tracking-wider text-slate-600">
                                Total Nilai Faktur Pembelian:
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-base font-black text-emerald-700 whitespace-nowrap">
                                Rp {{ number_format((float) $invoiceGrandTotal, 0, ',', '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Submit & Cancel Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('products.index') }}" class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition">
                Batal
            </a>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove>📥 Simpan & Terima Faktur PBF</span>
                <span wire:loading>Menyimpan Faktur...</span>
            </button>
        </div>
    </form>
</div>
