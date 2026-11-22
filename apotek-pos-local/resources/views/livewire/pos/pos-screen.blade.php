<div
    x-data="posApp()"
    @keydown.window="handleGlobalKey($event)"
    @receipt-ready.window="handleReceiptReady($event.detail.payload)"
    @pos-toast.window="showToast($event.detail.type, $event.detail.message)"
    class="h-screen max-h-screen bg-slate-950 text-slate-100 flex flex-col select-none font-sans overflow-hidden"
>
    <!-- TOP BAR (HEADER KASIR COMPACT & RESIK) -->
    <header class="h-14 shrink-0 px-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between gap-4 z-20">
        <!-- Brand & Terminal Info -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xl">💊</span>
                <span class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-xs px-2.5 py-1 rounded-lg tracking-wider shadow-xs">
                    APOTEK POS
                </span>
            </div>
            <div class="h-5 w-px bg-slate-800 hidden sm:block"></div>
            <div class="hidden sm:flex items-center gap-2 text-xs">
                <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-mono font-bold border border-slate-700">
                    {{ config('license.terminal_id', 'POS01') }}
                </span>
                <span class="text-slate-400">Kasir: <strong class="text-slate-200">{{ $cashier?->name ?? 'Kasir' }}</strong></span>
                <span class="text-slate-500">•</span>
                <span class="text-slate-400">Shift: <strong class="text-emerald-400 font-mono">{{ $shift?->opened_at?->format('H:i') ?? '-' }}</strong></span>
            </div>
        </div>

        <!-- Hardware Bridge Status & Navigation -->
        <div class="flex items-center gap-2.5 text-xs">
            <!-- Hardware Bridge & U.are.U Status Pill -->
            <div class="flex items-center gap-2 bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800 shadow-2xs">
                <template x-if="bridgeStatus === 'connected'">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-emerald-400 font-semibold text-[11px] hidden md:inline" x-text="bridgeDeviceName ? bridgeDeviceName + ' (Terhubung)' : 'U.are.U Terhubung (Fisik)'"></span>
                    </span>
                </template>
                <template x-if="bridgeStatus === 'no_device'">
                    <span class="flex items-center gap-1.5" title="Driver SDK aktif, tancapkan USB DigitalPersona U.are.U untuk memindai">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span class="text-amber-300 font-medium text-[11px] hidden md:inline">U.are.U Belum Terpasang (USB)</span>
                    </span>
                </template>
                <template x-if="bridgeStatus === 'offline' || bridgeStatus === 'checking'">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <span class="text-rose-400 font-medium text-[11px] hidden md:inline">Bridge Offline</span>
                    </span>
                </template>
            </div>

            <!-- Master Obat Link -->
            <a href="{{ route('products.index') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-700 transition flex items-center gap-1.5">
                <span>📦</span> <span class="hidden sm:inline">Master Obat</span>
            </a>

            <!-- Exit / Tutup Kasir Button (Prominent Red) -->
            <button
                type="button"
                @click="openModal('exit')"
                class="bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-sm border border-rose-500 transition flex items-center gap-1.5 cursor-pointer"
            >
                <span>🚪</span> <span>Tutup Kasir</span>
            </button>
        </div>
    </header>

    <!-- MAIN POS SCREEN (FITS PERFECTLY IN VIEWPORT 100vh) -->
    <div class="flex-1 min-h-0 grid grid-cols-12 gap-3 p-3 overflow-hidden">
        <!-- LEFT AREA: SCAN BARCODE & CART TABLE (8 COLS) -->
        <div class="col-span-12 lg:col-span-8 flex flex-col gap-2.5 min-h-0 h-full overflow-hidden">
            <!-- BARCODE SCANNER INPUT BAR -->
            <div class="shrink-0 bg-slate-900 p-2.5 rounded-xl border border-slate-800 shadow-md">
                <div class="flex items-center gap-2.5">
                    <span class="text-slate-400 text-base pl-1">📷</span>
                    <input
                        type="text"
                        id="barcode-input"
                        wire:model="barcodeInput"
                        wire:keydown.enter="scanBarcode"
                        placeholder="Scan barcode kemasan obat atau ketik barcode lalu tekan [Enter]..."
                        class="flex-1 bg-slate-950 border-2 border-indigo-600/70 rounded-lg px-3.5 py-2 text-sm font-mono text-white placeholder-slate-500 focus:outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/30 select-all"
                        autofocus
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        @click="openModal('search')"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-lg flex items-center gap-1.5 shadow transition cursor-pointer whitespace-nowrap"
                    >
                        <span>🔍</span> [F1] Cari Obat
                    </button>
                </div>
            </div>

            <!-- CART ITEMS TABLE (INTERNAL SCROLL, NEVER CUT OFF) -->
            <div class="flex-1 min-h-0 bg-slate-900 rounded-xl border border-slate-800 shadow-md flex flex-col overflow-hidden">
                <!-- Table Header Sticky -->
                <div class="shrink-0 bg-slate-850 border-b border-slate-800">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase font-extrabold text-[11px]">
                                <th class="py-2.5 px-3 w-10 text-center">#</th>
                                <th class="py-2.5 px-3">Nama Produk Obat & Zat Aktif</th>
                                <th class="py-2.5 px-3 w-32">Satuan [F3]</th>
                                <th class="py-2.5 px-3 text-center w-24">Qty [F2]</th>
                                <th class="py-2.5 px-3 text-right w-28">Harga (Rp)</th>
                                <th class="py-2.5 px-3 text-right w-32">Subtotal (Rp)</th>
                                <th class="py-2.5 px-3 text-center w-12">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <!-- Table Body Scrollable -->
                <div class="flex-1 min-h-0 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <tbody class="divide-y divide-slate-800/60">
                            @forelse($cart as $idx => $item)
                                <tr
                                    @click="$wire.set('selectedItemIndex', {{ $idx }})"
                                    class="cursor-pointer transition {{ $selectedItemIndex === $idx ? 'bg-indigo-950/60 border-l-4 border-indigo-500' : 'hover:bg-slate-850/50' }}"
                                >
                                    <td class="py-2.5 px-3 w-10 text-center font-mono text-slate-400">{{ $idx + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-extrabold text-white text-sm leading-snug">
                                            @if($item['is_racikan'])
                                                <span class="text-amber-400 font-mono text-xs">[{{ $item['racikan_group_name'] ?? 'Racikan' }}]</span>
                                            @endif
                                            {{ $item['product_name'] }}
                                        </div>
                                        @if($item['generic_name'])
                                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $item['generic_name'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 w-32">
                                        <button
                                            type="button"
                                            @click.stop="$wire.set('selectedItemIndex', {{ $idx }}); openModal('unit')"
                                            class="bg-slate-800 hover:bg-slate-700 text-indigo-300 font-semibold px-2.5 py-1 rounded-lg text-xs border border-slate-700 transition"
                                            title="Tekan F3 untuk ubah satuan"
                                        >
                                            {{ $item['unit_name'] }} <span class="text-[10px] text-slate-400">(x{{ $item['conversion_factor'] }})</span>
                                        </button>
                                    </td>
                                    <td class="py-2.5 px-3 text-center w-24">
                                        <button
                                            type="button"
                                            @click.stop="$wire.set('selectedItemIndex', {{ $idx }}); openModal('qty')"
                                            class="bg-slate-800 hover:bg-slate-700 text-white font-mono font-black px-3 py-1 rounded-lg text-xs border border-slate-700 transition min-w-[40px]"
                                            title="Tekan F2 untuk ubah qty"
                                        >
                                            {{ (float) $item['qty_input'] }}
                                        </button>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-300 w-28">
                                        {{ number_format((float) $item['unit_price'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-black text-emerald-400 text-sm w-32 whitespace-nowrap">
                                        {{ number_format((float) bcmul((string) $item['unit_price'], (string) $item['qty_input'], 2), 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center w-12">
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $idx }})"
                                            class="text-rose-400 hover:text-rose-300 hover:bg-rose-950/60 p-1.5 rounded-lg transition font-bold"
                                            title="Hapus baris obat ini"
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-20 text-center text-slate-500">
                                        <div class="text-4xl mb-2">🛒</div>
                                        <p class="text-sm font-bold text-slate-400">Keranjang Transaksi Kasir Kosong</p>
                                        <p class="text-xs text-slate-600 mt-1">Arahkan scanner ke kemasan obat, atau tekan <strong class="text-indigo-400">[F1]</strong> untuk cari obat di database.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- KEYBOARD SHORTCUTS BAR -->
            <div class="shrink-0 bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 flex flex-wrap gap-1.5 items-center justify-between text-[11px] font-mono text-slate-300">
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-indigo-400">F1</b> Cari</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-indigo-400">F2</b> Qty</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-indigo-400">F3</b> Satuan</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-indigo-400">F4</b> Racikan</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-amber-400">F8</b> Hold</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b class="text-amber-400">F9</b> Recall ({{ count($heldCarts) }})</span>
                <span class="bg-emerald-900/60 text-emerald-300 px-2 py-0.5 rounded border border-emerald-700 font-bold"><b>F10</b> Bayar</span>
                <span class="bg-rose-900/60 text-rose-300 px-2 py-0.5 rounded border border-rose-700"><b>Ctrl+V</b> Void</span>
                <span class="bg-slate-800 px-2 py-0.5 rounded border border-slate-700"><b>ESC</b> Batal</span>
            </div>
        </div>

        <!-- RIGHT AREA: TOTAL TAGIHAN & SUMMARY (4 COLS) -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-2.5 min-h-0 h-full overflow-hidden">
            <!-- BIG LED GRAND TOTAL DISPLAY -->
            <div class="shrink-0 bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-4 rounded-xl border border-slate-800 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <span>Total Tagihan Kasir</span>
                    <span class="text-[10px] text-emerald-500 font-mono">IDR (PASIEN)</span>
                </div>
                <div class="mt-2 text-right">
                    <span class="text-xs text-emerald-500 font-mono font-bold mr-1">Rp</span>
                    <span class="text-3xl xl:text-4xl font-black font-mono tracking-tight text-emerald-400">
                        {{ number_format((float) $this->grandTotal, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <!-- SUMMARY DETAILS & ADJUSTMENTS -->
            <div class="flex-1 min-h-0 bg-slate-900 p-4 rounded-xl border border-slate-800 shadow-md space-y-2.5 text-xs overflow-y-auto">
                <div class="flex justify-between py-1.5 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Subtotal Barang</span>
                    <span class="font-mono font-bold text-slate-200">Rp {{ number_format((float) $this->subtotal, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Tuslah & Embalase (F4)</span>
                    <span class="font-mono text-amber-300">Rp {{ number_format((float) $this->tuslahEmbalaseTotal, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Potongan Diskon</span>
                    <span class="font-mono text-rose-400 font-bold">- Rp {{ number_format((float) $this->discountTotal, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-slate-800">
                    <span class="text-slate-400 font-medium">Jumlah Item Terpilih</span>
                    <span class="font-mono text-indigo-400 font-bold">{{ count($cart) }} Baris</span>
                </div>

                @if($patientName)
                    <div class="p-2.5 bg-indigo-950/40 border border-indigo-800/50 rounded-lg text-[11px] mt-2">
                        <span class="font-bold text-indigo-300">Resep Dokter:</span>
                        <div class="text-slate-300 mt-0.5">Pasien: {{ $patientName }} | Dr: {{ $doctorName ?? '-' }}</div>
                    </div>
                @endif
            </div>

            <!-- QUICK ACTIONS BAR -->
            <div class="shrink-0 grid grid-cols-3 gap-1.5 text-xs font-bold">
                <button
                    type="button"
                    wire:click="holdTransaction"
                    :disabled="{{ empty($cart) ? 'true' : 'false' }}"
                    class="py-2 px-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-amber-400 border border-slate-800 rounded-lg transition text-center cursor-pointer"
                >
                    [F8] Tahan
                </button>
                <button
                    type="button"
                    @click="openModal('recall')"
                    class="py-2 px-2 bg-slate-900 hover:bg-slate-800 text-blue-400 border border-slate-800 rounded-lg transition text-center cursor-pointer"
                >
                    [F9] Buka ({{ count($heldCarts) }})
                </button>
                <button
                    type="button"
                    @click="openModal('void')"
                    :disabled="{{ empty($cart) ? 'true' : 'false' }}"
                    class="py-2 px-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-rose-400 border border-slate-800 rounded-lg transition text-center cursor-pointer"
                >
                    Void Transaksi
                </button>
            </div>

            <!-- BIG CHECKOUT BUTTON -->
            <button
                type="button"
                @click="openModal('payment')"
                :disabled="{{ empty($cart) ? 'true' : 'false' }}"
                class="shrink-0 w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-40 disabled:hover:from-emerald-600 disabled:hover:to-teal-600 text-white font-black text-base lg:text-lg py-3.5 rounded-xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer"
            >
                <span>💳 [F10] PROSES PEMBAYARAN</span>
            </button>
        </div>
    </div>

    <!-- MODAL 1: [F1] CARI OBAT DI DATABASE -->
    <div
        x-show="modal === 'search'"
        x-cloak
        class="fixed inset-0 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-3xl rounded-2xl shadow-2xl p-6 flex flex-col max-h-[85vh]">
            <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🔍</span>
                    <h3 class="text-base font-bold text-white">[F1] Cari Obat / Zat Aktif / SKU</h3>
                </div>
                <span class="text-xs text-slate-400">Tekan <kbd class="px-1.5 py-0.5 bg-slate-800 rounded font-mono text-[10px] border border-slate-700">ESC</kbd> untuk tutup</span>
            </div>

            <div class="my-4">
                <input
                    type="text"
                    id="search-modal-input"
                    wire:model.live.debounce.200ms="searchQuery"
                    placeholder="Ketik nama obat (misal: Paracetamol, Amoxicillin, Sanmol, Kalbe)..."
                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                    autocomplete="off"
                >
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-800 border border-slate-800 rounded-xl">
                @forelse($searchResults as $res)
                    <div
                        wire:click="addProductToCart('{{ $res->id }}')"
                        @click="closeModal()"
                        class="p-3 hover:bg-indigo-950/60 cursor-pointer flex justify-between items-center transition"
                    >
                        <div>
                            <div class="font-bold text-sm text-white">{{ $res->name }}</div>
                            <div class="text-xs text-slate-400">{{ $res->generic_name ?? '-' }} | SKU: {{ $res->sku }}</div>
                            <div class="text-[10px] text-indigo-400 font-mono mt-1">
                                @foreach($res->units as $u)
                                    <span class="mr-3 bg-slate-850 px-1.5 py-0.5 rounded border border-slate-800">{{ $u->unit_name }}: Rp {{ number_format((float) $u->sell_price, 0, ',', '.') }}</span>
                                @endforeach
                            </div>
                        </div>
                        <button class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-xs cursor-pointer">
                            Pilih [Enter]
                        </button>
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-500 text-xs">
                        {{ strlen(trim($searchQuery)) < 2 ? 'Ketik minimal 2 karakter untuk mencari obat...' : 'Obat tidak ditemukan di database.' }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- MODAL 2: [F2] UBAH QUANTITY ITEM -->
    <div
        x-show="modal === 'qty'"
        x-cloak
        class="fixed inset-0 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-xs rounded-2xl p-6 shadow-2xl space-y-4 text-center">
            <h3 class="text-base font-bold text-white">[F2] Ubah Jumlah (Qty)</h3>
            <input
                type="number"
                id="qty-modal-input"
                x-model="tempQty"
                step="1"
                min="1"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-2xl font-mono font-black text-center text-white focus:outline-none focus:border-indigo-500"
                @keydown.enter="$wire.updateItemQty({{ $selectedItemIndex ?? 0 }}, tempQty); closeModal()"
            >
            <div class="flex gap-2">
                <button @click="closeModal()" class="flex-1 bg-slate-800 text-slate-300 py-2.5 rounded-xl text-xs font-bold">Batal (ESC)</button>
                <button @click="$wire.updateItemQty({{ $selectedItemIndex ?? 0 }}, tempQty); closeModal()" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 rounded-xl text-xs font-bold">Simpan [Enter]</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: [F3] UBAH SATUAN KEMASAN -->
    <div
        x-show="modal === 'unit'"
        x-cloak
        class="fixed inset-0 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-sm rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white">[F3] Pilih Satuan Kemasan</h3>
            <div class="space-y-2">
                @if($selectedItemIndex !== null && isset($cart[$selectedItemIndex]))
                    @php
                        $prod = \App\Models\Product::with('units')->find($cart[$selectedItemIndex]['product_id']);
                    @endphp
                    @if($prod)
                        @foreach($prod->units as $u)
                            <button
                                type="button"
                                wire:click="changeItemUnit({{ $selectedItemIndex }}, '{{ $u->id }}')"
                                @click="closeModal()"
                                class="w-full p-3 bg-slate-950 hover:bg-indigo-950/60 border border-slate-800 rounded-xl flex justify-between items-center text-xs transition cursor-pointer"
                            >
                                <span class="font-bold text-white">{{ $u->unit_name }} (x{{ $u->conversion_factor }})</span>
                                <span class="font-mono text-emerald-400 font-bold">Rp {{ number_format((float) $u->sell_price, 0, ',', '.') }}</span>
                            </button>
                        @endforeach
                    @endif
                @endif
            </div>
            <button @click="closeModal()" class="w-full bg-slate-800 text-slate-300 py-2.5 rounded-xl text-xs font-bold">Batal (ESC)</button>
        </div>
    </div>

    <!-- MODAL 4: [F4] RESEP RACIKAN / DOKTER -->
    <div
        x-show="modal === 'resep'"
        x-cloak
        class="fixed inset-0 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white">[F4] Input Resep Dokter & Tuslah</h3>
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 mb-1 font-semibold">Nama Pasien:</label>
                    <input type="text" wire:model="patientName" placeholder="Contoh: Budi Santoso" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1 font-semibold">Nama Dokter:</label>
                    <input type="text" wire:model="doctorName" placeholder="Contoh: dr. Hartono, Sp.A" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-slate-400 mb-1 font-semibold">Tuslah (Jasa Resep):</label>
                        <input type="number" wire:model="tuslahInput" placeholder="2000" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1 font-semibold">Embalase (Wadah):</label>
                        <input type="number" wire:model="embalaseInput" placeholder="1000" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono">
                    </div>
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <button @click="closeModal()" class="flex-1 bg-slate-800 text-slate-300 py-2.5 rounded-xl text-xs font-bold">Batal (ESC)</button>
                <button wire:click="applyPrescriptionDetails" @click="closeModal()" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 rounded-xl text-xs font-bold">Terapkan Resep</button>
            </div>
        </div>
    </div>

    <!-- MODAL 5: [F10] CHECKOUT PEMBAYARAN -->
    <div
        x-show="modal === 'payment'"
        x-cloak
        class="fixed inset-0 bg-black/85 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-lg rounded-2xl p-6 shadow-2xl space-y-4 my-auto">
            <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>💳</span> [F10] Pembayaran Transaksi Kasir
                </h3>
                <span class="text-xs text-emerald-400 font-mono font-bold bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800">
                    Total: Rp {{ number_format((float) $this->grandTotal, 0, ',', '.') }}
                </span>
            </div>

            <!-- Metode Pembayaran -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Pilih Metode Pembayaran:</label>
                <div class="grid grid-cols-4 gap-2">
                    @foreach($paymentMethods as $pm)
                        <button
                            type="button"
                            wire:click="$set('paymentMethod', '{{ $pm->value }}')"
                            class="p-2.5 rounded-xl text-xs font-bold border transition cursor-pointer {{ $paymentMethod === $pm->value ? 'bg-indigo-600 border-indigo-400 text-white shadow-xs' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white' }}"
                        >
                            {{ $pm->label() }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Nominal Uang Diterima -->
            @if($paymentMethod === 'cash')
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Nominal Uang Tunai Diterima (Rp):</label>
                    <input
                        type="number"
                        id="payment-modal-input"
                        wire:model.live="paidAmount"
                        x-model="cashInput"
                        class="w-full bg-slate-950 border-2 border-emerald-500 rounded-xl px-4 py-3 text-2xl font-mono font-black text-emerald-400 text-right focus:outline-none"
                        autofocus
                    >

                    <!-- Quick Cash Buttons -->
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" @click="$wire.set('paidAmount', '{{ $this->grandTotal }}'); cashInput = '{{ $this->grandTotal }}'" class="bg-slate-800 hover:bg-slate-700 text-slate-200 py-2 rounded-lg text-xs font-mono font-bold transition">Uang Pas</button>
                        <button type="button" @click="$wire.set('paidAmount', '50000'); cashInput = '50000'" class="bg-slate-800 hover:bg-slate-700 text-slate-200 py-2 rounded-lg text-xs font-mono font-bold transition">50.000</button>
                        <button type="button" @click="$wire.set('paidAmount', '100000'); cashInput = '100000'" class="bg-slate-800 hover:bg-slate-700 text-slate-200 py-2 rounded-lg text-xs font-mono font-bold transition">100.000</button>
                        <button type="button" @click="$wire.set('paidAmount', '200000'); cashInput = '200000'" class="bg-slate-800 hover:bg-slate-700 text-slate-200 py-2 rounded-lg text-xs font-mono font-bold transition">200.000</button>
                    </div>

                    <!-- Realtime Kembalian -->
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">KEMBALIAN:</span>
                        <span class="font-mono font-black text-2xl" :class="realtimeChange >= 0 ? 'text-amber-400' : 'text-rose-500'" x-text="'Rp ' + formatRupiah(realtimeChange)"></span>
                    </div>
                </div>
            @endif

            <div class="flex gap-2 pt-2">
                <button @click="closeModal()" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-300 py-3 rounded-xl text-xs font-bold transition">Batal (ESC)</button>
                <button
                    wire:click="processCheckout"
                    @click="closeModal()"
                    class="flex-1 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white py-3 rounded-xl text-sm font-black shadow-lg transition"
                >
                    Konfirmasi & Cetak Struk [Enter]
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 6: [Ctrl+V] VOID DENGAN U.are.U FINGERPRINT & PIN SUPERVISOR -->
    <div
        x-show="modal === 'void'"
        x-cloak
        class="fixed inset-0 bg-black/85 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center text-2xl mx-auto border border-rose-500/20">
                🔒
            </div>
            <h3 class="text-base font-bold text-white">[Ctrl+V] Otorisasi Pembatalan (Void)</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                Tempelkan sidik jari Supervisor pada sensor <strong>DigitalPersona U.are.U 4500</strong>, atau masukkan PIN Darurat di bawah ini:
            </p>

            <!-- U.are.U Live Scan Button -->
            <button
                type="button"
                @click="scanFingerprintForVoid()"
                class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md transition cursor-pointer"
            >
                <span>👆</span> Pindai Sidik Jari U.are.U 4500
            </button>

            <div class="flex items-center gap-2 text-slate-500 text-xs">
                <div class="flex-1 border-t border-slate-800"></div>
                <span>ATAU DENGAN PIN</span>
                <div class="flex-1 border-t border-slate-800"></div>
            </div>

            <!-- PIN Input Fallback -->
            <input
                type="password"
                id="void-pin-input"
                x-model="supervisorPin"
                placeholder="PIN Supervisor (Default: 123456)"
                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-center font-mono text-lg text-white tracking-widest focus:outline-none focus:border-rose-500"
                maxlength="6"
            >

            <div class="flex gap-2">
                <button @click="closeModal()" class="flex-1 bg-slate-800 text-slate-300 py-2.5 rounded-xl text-xs font-bold">Batal (ESC)</button>
                <button @click="$wire.voidTransactionWithSupervisorPin(supervisorPin); closeModal()" class="flex-1 bg-rose-600 hover:bg-rose-500 text-white py-2.5 rounded-xl text-xs font-bold">Verifikasi PIN</button>
            </div>
        </div>
    </div>

    <!-- MODAL 7: [F9] RECALL TRANSAKSI -->
    <div
        x-show="modal === 'recall'"
        x-cloak
        class="fixed inset-0 bg-black/85 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-slate-900 border border-slate-700 w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <span>⏱️</span> [F9] Buka Transaksi Ditahan (Recall)
            </h3>
            <div class="space-y-2 max-h-60 overflow-y-auto">
                @forelse($heldCarts as $hIdx => $held)
                    <div
                        wire:click="recallTransaction({{ $hIdx }})"
                        @click="closeModal()"
                        class="p-3 bg-slate-950 hover:bg-indigo-950/60 border border-slate-800 rounded-xl flex justify-between items-center cursor-pointer text-xs transition"
                    >
                        <div>
                            <div class="font-bold text-white">Waktu Hold: {{ $held['time'] }} ({{ count($held['cart']) }} item)</div>
                            <div class="text-[11px] text-slate-400">{{ $held['patient_name'] ? 'Pasien: ' . $held['patient_name'] : 'Umum (OTC)' }}</div>
                        </div>
                        <span class="font-mono font-bold text-emerald-400">Rp {{ number_format((float) $held['grand_total'], 0, ',', '.') }}</span>
                    </div>
                @empty
                    <div class="text-center text-slate-500 py-8 text-xs">Tidak ada transaksi yang sedang ditahan.</div>
                @endforelse
            </div>
            <button @click="closeModal()" class="w-full bg-slate-800 text-slate-300 py-2.5 rounded-xl text-xs font-bold">Tutup (ESC)</button>
        </div>
    </div>

    <!-- MODAL 8: FALLBACK CETAK STRUK BROWSER -->
    <div
        x-show="showBrowserReceiptModal"
        x-cloak
        class="fixed inset-0 bg-black/85 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div class="bg-white text-slate-900 w-full max-w-sm rounded-2xl p-6 shadow-2xl space-y-4 font-mono text-xs">
            <div class="flex justify-between items-center pb-2 border-b border-slate-300">
                <h3 class="font-bold text-sm">Preview Struk Kasir</h3>
                <button @click="showBrowserReceiptModal = false" class="text-slate-500 font-bold">✕</button>
            </div>

            <!-- Struk Printable Area -->
            <div id="printable-receipt" class="bg-slate-50 p-4 border border-dashed border-slate-400 rounded-xl leading-relaxed whitespace-pre-wrap select-text">
                <div class="text-center font-bold text-sm">{{ $lastReceiptPayload['header']['pharmacy_name'] ?? 'APOTEK POS' }}</div>
                <div class="text-center text-[10px]">{{ $lastReceiptPayload['header']['address'] ?? '' }}</div>
                <div class="text-center text-[10px]">{{ $lastReceiptPayload['header']['phone'] ?? '' }}</div>
                <hr class="my-2 border-slate-400">
                <div>No   : {{ $lastReceiptPayload['transaction']['invoice_no'] ?? '-' }}</div>
                <div>Tgl  : {{ $lastReceiptPayload['transaction']['date'] ?? '-' }}</div>
                <div>Kasir: {{ $lastReceiptPayload['transaction']['cashier_name'] ?? '-' }}</div>
                <hr class="my-2 border-slate-400">
                @if(isset($lastReceiptPayload['items']))
                    @foreach($lastReceiptPayload['items'] as $it)
                        <div class="flex justify-between">
                            <span>{{ $it['name'] }}</span>
                            <span>{{ $it['subtotal'] }}</span>
                        </div>
                        <div class="text-[10px] text-slate-500 pl-2">{{ $it['qty'] }} {{ $it['unit'] }} x {{ $it['price'] }}</div>
                    @endforeach
                @endif
                <hr class="my-2 border-slate-400">
                <div class="flex justify-between font-bold">
                    <span>TOTAL:</span>
                    <span>Rp {{ $lastReceiptPayload['totals']['grand_total'] ?? '0' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Bayar:</span>
                    <span>Rp {{ $lastReceiptPayload['totals']['paid_amount'] ?? '0' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kembali:</span>
                    <span>Rp {{ $lastReceiptPayload['totals']['change_amount'] ?? '0' }}</span>
                </div>
                <hr class="my-2 border-slate-400">
                <div class="text-center text-[10px]">{{ $lastReceiptPayload['footer'] ?? 'Semoga Lekas Sembuh' }}</div>
            </div>

            <div class="flex gap-2">
                <button @click="printViaBrowser()" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 rounded-xl text-xs font-bold font-sans">
                    🖨️ Cetak via Browser
                </button>
                <button @click="showBrowserReceiptModal = false" class="bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-sans font-bold">
                    Selesai
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 9: EXIT / TUTUP KASIR MODAL -->
    <div
        x-show="modal === 'exit'"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/85 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-5 text-slate-100">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 class="text-base font-bold flex items-center gap-2 text-rose-400">
                    <span>🚪</span> Tutup / Keluar Kasir POS
                </h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
            </div>

            <!-- Option 1: Tutup Shift Kasir (Blind Closing) -->
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
                <div>
                    <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider">1. Tutup Shift Kasir (Blind Closing)</h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">Masukkan total uang fisik di laci kasir untuk menghitung selisih:</p>
                </div>
                <div class="flex gap-2">
                    <input
                        type="number"
                        x-model="shiftClosingCashInput"
                        placeholder="Contoh: 150000"
                        class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-rose-500"
                    >
                    <button
                        type="button"
                        @click="if (shiftClosingCashInput) { $wire.closeShiftFromPos(shiftClosingCashInput) } else { alert('Masukkan nominal uang fisik di laci!') }"
                        class="bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs px-4 py-2 rounded-lg shadow cursor-pointer"
                    >
                        Tutup Shift
                    </button>
                </div>
            </div>

            <!-- Option 2: Buka Modul Web Lainnya -->
            <div class="space-y-2">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">2. Menu Modul Lainnya</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <a href="{{ route('products.index') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-200 p-2.5 rounded-lg text-center font-medium border border-slate-700">
                        📦 Master Obat
                    </a>
                    <a href="{{ route('purchases.create') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-200 p-2.5 rounded-lg text-center font-medium border border-slate-700">
                        📥 Faktur Masuk PBF
                    </a>
                    <a href="{{ route('settings.database') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-200 p-2.5 rounded-lg text-center font-medium border border-slate-700">
                        💾 Database & Backup
                    </a>
                    <a href="{{ route('settings.fingerprint') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-200 p-2.5 rounded-lg text-center font-medium border border-slate-700">
                        👆 Biometrik U.are.U
                    </a>
                </div>
            </div>

            <!-- Option 3: Tutup Jendela Browser -->
            <div class="pt-3 border-t border-slate-800 flex justify-between items-center">
                <span class="text-xs text-slate-500">Shortcut Tutup: <strong class="text-slate-300">Alt + F4</strong></span>
                <div class="flex gap-2">
                    <button type="button" @click="closeModal()" class="px-3 py-1.5 text-xs text-slate-400 hover:text-white cursor-pointer">
                        Batal (ESC)
                    </button>
                    <button type="button" @click="window.close()" class="bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg cursor-pointer">
                        Tutup Jendela
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
        <template x-for="(toast, tIdx) in toasts" :key="tIdx">
            <div
                class="px-4 py-3 rounded-xl shadow-2xl text-xs font-semibold flex items-center justify-between gap-3 text-white transition transform duration-300"
                :class="{
                    'bg-emerald-600 border border-emerald-500': toast.type === 'success',
                    'bg-rose-600 border border-rose-500': toast.type === 'error',
                    'bg-amber-600 border border-amber-500': toast.type === 'warning',
                    'bg-indigo-600 border border-indigo-500': toast.type === 'info'
                }"
            >
                <span x-text="toast.message"></span>
                <button @click="toasts.splice(tIdx, 1)" class="font-bold opacity-75 hover:opacity-100 cursor-pointer">✕</button>
            </div>
        </template>
    </div>
</div>

<script>
function posApp() {
    return {
        modal: null,
        bridgeOnline: false,
        bridgeStatus: 'checking',
        bridgeDeviceName: '',
        toasts: [],
        tempQty: 1,
        cashInput: 0,
        supervisorPin: '',
        shiftClosingCashInput: '',
        showBrowserReceiptModal: false,

        init() {
            this.checkBridgeHealth();
            setInterval(() => this.checkBridgeHealth(), 10000);
            this.focusBarcode();
        },

        get grandTotalNum() {
            return parseFloat(@js((float) $this->grandTotal)) || 0;
        },

        get realtimeChange() {
            const paid = parseFloat(this.cashInput) || 0;
            return paid - this.grandTotalNum;
        },

        formatRupiah(num) {
            return new Intl.NumberFormat('id-ID').format(Math.max(0, num));
        },

        focusBarcode() {
            this.$nextTick(() => {
                const el = document.getElementById('barcode-input');
                if (el) el.focus();
            });
        },

        openModal(name) {
            this.modal = name;
            this.$nextTick(() => {
                if (name === 'search') document.getElementById('search-modal-input')?.focus();
                if (name === 'qty') document.getElementById('qty-modal-input')?.select();
                if (name === 'payment') document.getElementById('payment-modal-input')?.select();
                if (name === 'void') document.getElementById('void-pin-input')?.focus();
            });
        },

        closeModal() {
            this.modal = null;
            this.focusBarcode();
        },

        async scanFingerprintForVoid() {
            this.showToast('info', 'Membaca sensor DigitalPersona U.are.U 4500...');
            try {
                const res = await fetch('http://127.0.0.1:8765/api/v1/fingerprint/enroll', {
                    method: 'POST',
                    signal: AbortSignal.timeout(6000),
                });
                const data = await res.json();
                if (res.ok && data.template) {
                    // Send candidate template to server for verification
                    const verifyRes = await @this.verifySupervisorFingerprint(data.template);
                    if (verifyRes && verifyRes.authorized) {
                        this.showToast('success', verifyRes.message || 'Otorisasi Sidik Jari U.are.U Berhasil!');
                        this.closeModal();
                    } else {
                        this.showToast('error', verifyRes?.message || 'Sidik jari tidak dikenali.');
                    }
                } else {
                    throw new Error(data.message || 'Sensor gagal memindai.');
                }
            } catch (err) {
                this.showToast('warning', 'Sensor U.are.U offline/timeout. Silakan gunakan PIN Supervisor.');
            }
        },

        handleGlobalKey(e) {
            // Global Shortcuts
            if (e.key === 'Escape') {
                e.preventDefault();
                this.closeModal();
                this.showBrowserReceiptModal = false;
                return;
            }

            if (e.ctrlKey && (e.key === 'v' || e.key === 'V')) {
                e.preventDefault();
                this.openModal('void');
                return;
            }

            switch(e.key) {
                case 'F1':
                    e.preventDefault();
                    this.openModal('search');
                    break;
                case 'F2':
                    e.preventDefault();
                    this.openModal('qty');
                    break;
                case 'F3':
                    e.preventDefault();
                    this.openModal('unit');
                    break;
                case 'F4':
                    e.preventDefault();
                    this.openModal('resep');
                    break;
                case 'F8':
                    e.preventDefault();
                    @this.holdTransaction();
                    break;
                case 'F9':
                    e.preventDefault();
                    this.openModal('recall');
                    break;
                case 'F10':
                    e.preventDefault();
                    this.openModal('payment');
                    break;
            }
        },

        async checkBridgeHealth() {
            try {
                const res = await fetch('http://127.0.0.1:8765/api/v1/fingerprint/status', { method: 'GET', signal: AbortSignal.timeout(1500) });
                if (res.ok) {
                    this.bridgeOnline = true;
                    const data = await res.json();
                    if (data.physical_device_connected) {
                        this.bridgeStatus = 'connected';
                        this.bridgeDeviceName = data.device || 'U.are.U 4500';
                    } else {
                        this.bridgeStatus = 'no_device';
                    }
                } else {
                    this.bridgeOnline = false;
                    this.bridgeStatus = 'offline';
                }
            } catch (err) {
                this.bridgeOnline = false;
                this.bridgeStatus = 'offline';
            }
        },

        async handleReceiptReady(payload) {
            // Send payload to Hardware Bridge ESC/POS Thermal Printer
            try {
                const res = await fetch('http://127.0.0.1:8765/api/v1/print/receipt', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    signal: AbortSignal.timeout(2000), // Max 2s timeout
                });

                if (res.ok) {
                    this.showToast('success', 'Struk berhasil dikirim ke printer thermal!');
                } else {
                    throw new Error('Printer Bridge merespon status error.');
                }
            } catch (err) {
                this.showToast('warning', 'Bridge offline/timeout (2s). Mengaktifkan fallback struk browser.');
                this.showBrowserReceiptModal = true;
            }
        },

        printViaBrowser() {
            window.print();
        },

        showToast(type, message) {
            this.toasts.push({ type, message });
            setTimeout(() => {
                if (this.toasts.length > 0) this.toasts.shift();
            }, 4000);
        }
    };
}
</script>
