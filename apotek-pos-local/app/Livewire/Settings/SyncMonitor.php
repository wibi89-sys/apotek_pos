<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Jobs\SyncPushJob;
use App\Models\SyncOutbox;
use App\Services\Licensing\LicenseService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SyncMonitor extends Component
{
    public ?string $feedbackMessage = null;
    public ?string $errorMessage = null;

    public function triggerSync(): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;

        try {
            $job = new SyncPushJob();
            $result = app()->call([$job, 'handle']);

            if (($result['status'] ?? '') === 'success') {
                $count = $result['processed_count'] ?? 0;
                $this->feedbackMessage = "Sinkronisasi berhasil! Sebanyak {$count} transaksi/mutasi sukses dikirim ke Cloud Hub.";
            } elseif (($result['status'] ?? '') === 'idle') {
                $this->feedbackMessage = 'Antrean sinkronisasi kosong (Semua data lokal telah tersinkron sempurna).';
            } else {
                $this->errorMessage = 'Sinkronisasi dilewati: ' . ($result['reason'] ?? 'Status tidak diketahui');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal melakukan sinkronisasi: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $pendingCount = SyncOutbox::where('status', 'pending')->count();
        $syncedCount = SyncOutbox::where('status', 'synced')->count();
        $failedCount = SyncOutbox::where('status', 'failed')->count();
        $recentItems = SyncOutbox::orderBy('created_at', 'desc')->take(20)->get();

        $licensePayload = app(LicenseService::class)->getLicensePayload();
        $cloudSyncConfigured = (bool) config('license.cloud_sync_enabled', env('CLOUD_SYNC_ENABLED', false));

        return view('livewire.settings.sync-monitor', [
            'pendingCount' => $pendingCount,
            'syncedCount' => $syncedCount,
            'failedCount' => $failedCount,
            'recentItems' => $recentItems,
            'cloudSyncConfigured' => $cloudSyncConfigured,
            'licensePayload' => $licensePayload,
        ]);
    }
}
