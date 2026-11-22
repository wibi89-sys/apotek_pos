<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <span>📋</span> Ekspor Pelaporan Regulasi SIPNAP Kemenkes RI
                </h1>
                <span class="bg-purple-100 text-purple-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase">
                    PRO FEATURE
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Generator file format resmi SIPNAP (Sistem Informasi Pelaporan Narkotika dan Psikotropika) siap unggah ke portal resmi Kementerian Kesehatan / BPOM.
            </p>
        </div>

        <!-- Download Action -->
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <select wire:model.live="reportingMonth" class="text-xs border border-slate-300 rounded-lg px-2.5 py-2">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">Bulan {{ DateTime::createFromFormat('!m', (string)$m)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <select wire:model.live="reportingYear" class="text-xs border border-slate-300 rounded-lg px-2.5 py-2">
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                </select>
            </div>
            <button wire:click="downloadCsv" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow transition">
                ⬇ Unduh CSV Resmi SIPNAP
            </button>
        </div>
    </div>

    <!-- Info Box SIPNAP -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-xs text-indigo-900 space-y-1">
        <p class="font-bold">Ketentuan Regulasi SIPNAP Farmasi:</p>
        <p>Setiap Sarana Pelayanan Kefarmasian (Apotek) diwajibkan oleh Permenkes RI untuk melaporkan mutasi pemasukan faktur PBF dan pengeluaran resep dokter untuk obat golongan Narkotika, Psikotropika, Prekursor, dan Obat-Obat Tertentu (OOT) secara berkala setiap bulan.</p>
    </div>

    <!-- Preview Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Pratinjau Data Obat Terpantau Regulasi</h2>
            <p class="text-xs text-slate-500">Menampilkan sampel obat keras & psikotropika yang akan dimasukkan ke dalam berkas pelaporan SIPNAP.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700 font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Kode SKU / Barcode</th>
                        <th class="px-6 py-3 text-left">Nama Obat</th>
                        <th class="px-6 py-3 text-left">Golongan</th>
                        <th class="px-6 py-3 text-left">Satuan Dasar</th>
                        <th class="px-6 py-3 text-right">Stok Fisik Tersedia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sampleProducts as $prod)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono text-xs text-slate-600">{{ $prod->sku }}</td>
                            <td class="px-6 py-4 font-medium text-slate-900">{{ $prod->name }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">
                                    {{ $prod->drug_class->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $prod->base_unit_name }}</td>
                            <td class="px-6 py-4 text-right font-bold text-slate-800">
                                {{ $prod->batches->sum('stock_base_unit') }} {{ $prod->base_unit_name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-6 text-center text-slate-400">
                                Tidak ada produk obat keras yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
