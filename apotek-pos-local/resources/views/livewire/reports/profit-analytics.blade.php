<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <span>📊</span> Analitik Laba Kotor & HPP Apotek
                </h1>
                <span class="bg-purple-100 text-purple-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase">
                    PRO FEATURE
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Laporan akurat margin laba kotor apotek berdasarkan snapshot HPP batch riil saat transaksi kasir FEFO.
            </p>
        </div>

        <!-- Date Filters -->
        <div class="flex items-center gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500">Mulai</label>
                <input type="date" wire:model.live="startDate" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500">Sampai</label>
                <input type="date" wire:model.live="endDate" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-indigo-500">
            </div>
        </div>
    </div>

    <!-- Cards Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Total Pendapatan (Omzet)</span>
            <p class="text-2xl font-bold text-slate-900 mt-1">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
            <span class="text-xs text-slate-400">{{ $transactionCount }} transaksi kasir</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Total HPP Penjualan</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">Rp {{ number_format($totalHpp, 0, ',', '.') }}</p>
            <span class="text-xs text-slate-400">Modal pokok obat terjual</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Laba Kotor (Gross Profit)</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">Rp {{ number_format($grossProfit, 0, ',', '.') }}</p>
            <span class="text-xs text-emerald-600 font-medium">Pendapatan bersih apotek</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Margin Laba Kotor</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($profitMargin, 1) }}%</p>
            <span class="text-xs text-slate-400">Rasio laba terhadap omzet</span>
        </div>
    </div>

    <!-- Top 10 Profitable Products -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">10 Produk Obat Penyumbang Laba Terbesar</h2>
            <p class="text-xs text-slate-500">Peringkat kontribusi laba kotor tertinggi pada periode ini.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700 font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Nama Produk Obat</th>
                        <th class="px-6 py-3 text-right">Pendapatan (Rp)</th>
                        <th class="px-6 py-3 text-right">HPP Modal (Rp)</th>
                        <th class="px-6 py-3 text-right">Laba Kotor (Rp)</th>
                        <th class="px-6 py-3 text-right">Margin (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($topProducts as $p)
                        @php
                            $pRev = (float) $p->revenue;
                            $pCost = (float) $p->cost;
                            $pProfit = $pRev - $pCost;
                            $pMargin = $pRev > 0 ? ($pProfit / $pRev) * 100 : 0.0;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ $p->product?->name ?? 'Obat' }}</td>
                            <td class="px-6 py-4 text-right">Rp {{ number_format($pRev, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right text-slate-500">Rp {{ number_format($pCost, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-600">Rp {{ number_format($pProfit, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right font-bold text-indigo-600">{{ number_format($pMargin, 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-6 text-center text-slate-400">
                                Belum ada data transaksi penjualan pada rentang tanggal yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
