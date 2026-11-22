<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                <span>💾</span> Database Management & Pemeliharaan (DBM)
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Cadangan otomatis harian, restore darurat, defragmentasi tabel, dan audit integritas mutasi stok apotek.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="createBackup" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-lg shadow transition">
                <span wire:loading.remove wire:target="createBackup">📦 Buat Backup (.zip)</span>
                <span wire:loading wire:target="createBackup">Sedang Memproses...</span>
            </button>
            <button wire:click="runAudit" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow transition">
                <span wire:loading.remove wire:target="runAudit">🔍 Audit Integritas Stok</span>
                <span wire:loading wire:target="runAudit">Memeriksa...</span>
            </button>
            <button wire:click="optimizeDatabase" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white font-medium text-sm rounded-lg shadow transition">
                <span wire:loading.remove wire:target="optimizeDatabase">⚡ Optimasi Tabel</span>
                <span wire:loading wire:target="optimizeDatabase">Mengoptimasi...</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    @if ($feedbackMessage)
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <span class="text-emerald-500 font-bold mr-2">✓</span>
                <p class="text-sm text-emerald-800">{{ $feedbackMessage }}</p>
            </div>
        </div>
    @endif

    @if ($errorMessage)
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
            <div class="flex items-center">
                <span class="text-red-500 font-bold mr-2">⚠</span>
                <p class="text-sm text-red-800">{{ $errorMessage }}</p>
            </div>
        </div>
    @endif

    <!-- Audit Result Box -->
    @if ($auditResult)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-900 mb-2">Hasil Audit Integritas Stok Batches</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                    <span class="text-xs text-slate-500 uppercase font-semibold">Total Batch Diperiksa</span>
                    <p class="text-2xl font-bold text-slate-800">{{ $auditResult['total_batches_checked'] }}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                    <span class="text-xs text-slate-500 uppercase font-semibold">Status Kesehatan</span>
                    <p class="text-lg font-bold {{ $auditResult['is_healthy'] ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ $auditResult['is_healthy'] ? 'SEHAT (100% SINKRON)' : 'DITEMUKAN ANOMALI' }}
                    </p>
                </div>
                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                    <span class="text-xs text-slate-500 uppercase font-semibold">Jumlah Selisih</span>
                    <p class="text-2xl font-bold {{ $auditResult['discrepancies_count'] > 0 ? 'text-red-600' : 'text-slate-800' }}">
                        {{ $auditResult['discrepancies_count'] }}
                    </p>
                </div>
            </div>

            @if (!empty($auditResult['discrepancies']))
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-red-50 text-red-900 font-semibold">
                            <tr>
                                <th class="px-3 py-2 text-left">Produk</th>
                                <th class="px-3 py-2 text-left">No Batch</th>
                                <th class="px-3 py-2 text-right">Stok Batch</th>
                                <th class="px-3 py-2 text-right">Sum Mutasi</th>
                                <th class="px-3 py-2 text-right">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($auditResult['discrepancies'] as $disc)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $disc['product_name'] }}</td>
                                    <td class="px-3 py-2">{{ $disc['batch_number'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $disc['batch_stock'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ $disc['audit_movement_sum'] }}</td>
                                    <td class="px-3 py-2 text-right text-red-600 font-bold">{{ $disc['difference'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- Backup List -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Riwayat Arsip Cadangan Database (ZIP)</h2>
                <p class="text-xs text-slate-500">Masa retensi: 30 hari terakhir. File lama dibersihkan secara otomatis.</p>
            </div>
            <a href="{{ route('db.emergency-download') }}" target="_blank" class="text-xs text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-300 font-semibold px-3 py-1.5 rounded-lg">
                🚨 Unduh Cadangan Darurat (Owner)
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700 font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Nama File Arsip</th>
                        <th class="px-6 py-3 text-left">Pemicu</th>
                        <th class="px-6 py-3 text-left">Ukuran</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Waktu Dibuat</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($backups as $backup)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono font-medium text-slate-800">{{ $backup->filename }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $backup->trigger_type === 'pre_restore' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800' }}">
                                    {{ $backup->trigger_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $backup->formatted_size }}</td>
                            <td class="px-6 py-4">
                                @if ($backup->status === 'completed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        ✓ Sukses
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                        Gagal
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $backup->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('settings.database.download', $backup->id) }}" class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded">
                                    ⬇ Unduh ZIP
                                </a>
                                <button wire:click="openRestoreModal('{{ $backup->id }}', '{{ $backup->filename }}')" class="inline-flex items-center text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded">
                                    ↺ Restore
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                Belum ada riwayat backup database. Klik tombol "Buat Backup" untuk membuat cadangan pertama.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    @if ($showRestoreModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 space-y-4 border border-rose-200">
                <div class="flex items-center gap-3 text-rose-600">
                    <span class="text-3xl">⚠️</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Konfirmasi Restore Database</h3>
                        <p class="text-xs text-rose-600 font-semibold">Tindakan ini akan menimpa seluruh database saat ini!</p>
                    </div>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 space-y-1">
                    <p class="font-bold">Protokol Keamanan Data Apotek:</p>
                    <p>Sistem akan <strong>otomatis membuat Snapshot Darurat (Pre-Restore Backup)</strong> terlebih dahulu sebelum restore dijalankan. Jika restore gagal, database dapat dipulihkan kembali dari snapshot darurat tersebut.</p>
                </div>

                <p class="text-sm text-slate-600">
                    File yang akan dipulihkan: <span class="font-mono font-bold text-slate-900">{{ $selectedBackupFilename }}</span>
                </p>

                <div class="flex justify-end gap-3 pt-2">
                    <button wire:click="$set('showRestoreModal', false)" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Batal
                    </button>
                    <button wire:click="executeRestore" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold shadow">
                        <span wire:loading.remove wire:target="executeRestore">Ya, Buat Snapshot & Restore</span>
                        <span wire:loading wire:target="executeRestore">Memproses Restore...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
