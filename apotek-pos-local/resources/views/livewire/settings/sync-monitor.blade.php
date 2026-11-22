<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <span>☁️</span> Monitoring Sinkronisasi Hybrid Cloud Hub
                </h1>
                <span class="bg-purple-100 text-purple-800 text-xs font-bold px-2.5 py-0.5 rounded-full uppercase">
                    PRO / ENTERPRISE
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Sinkronisasi data transaksi, shift, dan stok dari terminal kasir lokal ke server Cloud Hub secara background & asinkron.
            </p>
        </div>

        <button wire:click="triggerSync" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg shadow transition">
            <span wire:loading.remove wire:target="triggerSync">🚀 Sinkronkan Sekarang (Push)</span>
            <span wire:loading wire:target="triggerSync">Sedang Mengirim Data...</span>
        </button>
    </div>

    <!-- Alert Messages -->
    @if ($feedbackMessage)
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
            <p class="text-sm text-emerald-800 font-medium">{{ $feedbackMessage }}</p>
        </div>
    @endif

    @if ($errorMessage)
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
            <p class="text-sm text-red-800 font-medium">{{ $errorMessage }}</p>
        </div>
    @endif

    <!-- Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Antrean Tertunda (Pending)</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingCount }}</p>
            <span class="text-xs text-slate-400">Siap dikirim saat internet ada</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Berhasil Tersinkron</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $syncedCount }}</p>
            <span class="text-xs text-slate-400">Tersimpan di Cloud Hub</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Gagal (Retry)</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">{{ $failedCount }}</p>
            <span class="text-xs text-slate-400">Maksimal 5x percobaan</span>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <span class="text-xs font-semibold text-slate-500 uppercase">Mode Cloud Sync</span>
            <p class="text-base font-bold text-slate-800 mt-1">
                {{ $cloudSyncConfigured ? '🟢 AKTIF (Push Otomatis)' : '⚪ NON-AKTIF (Offline Saja)' }}
            </p>
            <span class="text-xs text-slate-400">Pengaturan .env CLOUD_SYNC_ENABLED</span>
        </div>
    </div>

    <!-- Outbox Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">Riwayat Antrean Sinkronisasi (Sync Outbox)</h2>
            <p class="text-xs text-slate-500">Daftar rekaman transaksi kasir lokal yang dikelola oleh engine sinkronisasi ULID.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-700 font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Tipe Entitas</th>
                        <th class="px-6 py-3 text-left">ID Entitas</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-center">Percobaan</th>
                        <th class="px-6 py-3 text-left">Waktu Dibuat</th>
                        <th class="px-6 py-3 text-left">Terakhir Sukses</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentItems as $item)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-bold text-slate-800 uppercase text-xs">{{ $item->entity_type }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-600">{{ $item->entity_id }}</td>
                            <td class="px-6 py-4">
                                @if ($item->status === 'synced')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        ✓ Tersinkron
                                    </span>
                                @elseif ($item->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                        Tertunda
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                                        Gagal
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center text-xs font-mono">{{ $item->attempts }}</td>
                            <td class="px-6 py-4 text-slate-500 text-xs">{{ $item->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-6 py-4 text-slate-500 text-xs">{{ $item->synced_at ? $item->synced_at->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-6 text-center text-slate-400">
                                Belum ada antrean rekaman sinkronisasi di outbox lokal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
