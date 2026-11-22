<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LicenseFeature;
use App\Models\SyncOutbox;
use App\Services\Licensing\LicenseEntitlementService;
use App\Services\Licensing\LicenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function handle(
        LicenseService $licenseService,
        LicenseEntitlementService $entitlementService
    ): array {
        $syncEnabled = (bool) config('license.cloud_sync_enabled', env('CLOUD_SYNC_ENABLED', false));
        if (! $syncEnabled) {
            return ['status' => 'skipped', 'reason' => 'CLOUD_SYNC_ENABLED is false'];
        }

        if (! $entitlementService->hasFeature(LicenseFeature::CLOUD_SYNC_PUSH)) {
            return ['status' => 'skipped', 'reason' => 'cloud_sync_push feature not licensed'];
        }

        $licensePayload = $licenseService->getLicensePayload();
        $licenseKey = $licensePayload['license_key'] ?? null;
        if (! $licenseKey) {
            return ['status' => 'skipped', 'reason' => 'license_key missing'];
        }

        $pendingOutbox = SyncOutbox::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get();

        if ($pendingOutbox->isEmpty()) {
            return ['status' => 'idle', 'processed_count' => 0];
        }

        $cloudHubUrl = (string) config('license.cloud_hub_url', env('CLOUD_HUB_URL', 'http://127.0.0.1:8000'));
        $branchCode = (string) config('license.branch_id', 'BR01');

        $transactions = [];
        $shifts = [];
        $batches = [];

        foreach ($pendingOutbox as $item) {
            match ($item->entity_type) {
                'transaction' => $transactions[] = $item->payload,
                'shift' => $shifts[] = $item->payload,
                'batch' => $batches[] = $item->payload,
                default => null,
            };
        }

        try {
            $response = Http::timeout(10)->post("{$cloudHubUrl}/api/v1/sync/push", [
                'license_key' => $licenseKey,
                'branch_code' => $branchCode,
                'transactions' => $transactions,
                'shifts' => $shifts,
                'batches' => $batches,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Cloud Hub Push HTTP ' . $response->status() . ': ' . $response->body());
            }

            // Mark outbox as synced
            foreach ($pendingOutbox as $item) {
                $item->status = 'synced';
                $item->synced_at = now();
                $item->save();
            }

            return [
                'status' => 'success',
                'processed_count' => $pendingOutbox->count(),
                'server_response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('SyncPushJob network error: ' . $e->getMessage());

            foreach ($pendingOutbox as $item) {
                $item->attempts += 1;
                $item->last_error = $e->getMessage();
                if ($item->attempts >= 5) {
                    $item->status = 'failed';
                }
                $item->save();
            }

            throw $e;
        }
    }
}
