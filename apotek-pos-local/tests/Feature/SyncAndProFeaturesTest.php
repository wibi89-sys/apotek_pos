<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicensePlan;
use App\Jobs\SyncPushJob;
use App\Models\SyncOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncAndProFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected string $licensePath;
    protected string $keyPair;
    protected string $publicKey;
    protected string $secretKey;
    protected string $hwid = 'test-hwid-sync';

    protected function setUp(): void
    {
        parent::setUp();

        $this->licensePath = storage_path('app/private/license_test_sync.lic');
        File::ensureDirectoryExists(dirname($this->licensePath));

        $this->keyPair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($this->keyPair);
        $this->secretKey = sodium_crypto_sign_secretkey($this->keyPair);

        config([
            'license.public_key' => base64_encode($this->publicKey),
            'license.mock_hwid' => $this->hwid,
            'license.license_file_path' => $this->licensePath,
            'license.cloud_hub_url' => 'http://127.0.0.1:8000',
        ]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->licensePath)) {
            File::delete($this->licensePath);
        }
        parent::tearDown();
    }

    protected function createSignedLicense(LicensePlan $plan, array $featureOverrides = []): void
    {
        $features = [
            'fingerprint_auth' => false,
            'label_printing' => false,
            'offsite_backup' => false,
            'cloud_sync_push' => false,
            'sipnap_export' => false,
            'profit_analytics' => false,
            'cloud_sync_pull' => false,
            'multi_branch_ops' => false,
            'external_api' => false,
        ];

        if ($plan === LicensePlan::PRO_CLOUD_SYNC) {
            $features['cloud_sync_push'] = true;
            $features['profit_analytics'] = true;
            $features['sipnap_export'] = true;
            $features['fingerprint_auth'] = true;
        }

        foreach ($featureOverrides as $k => $v) {
            $features[$k] = $v;
        }

        $payload = [
            'schema_version' => 1,
            'license_key' => 'APT-SYNC-TEST-0001',
            'customer_name' => 'Apotek Sync Test',
            'pharmacy_name' => 'Apotek Mandiri',
            'branch_code' => 'BR01',
            'hwid' => $this->hwid,
            'plan' => $plan->value,
            'billing_type' => 'subscription',
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addDays(30)->timestamp,
            'grace_days' => 14,
            'heartbeat_interval_days' => 7,
            'limits' => ['max_terminals' => 5],
            'features' => $features,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = base64_encode($payloadJson);
        $signature = sodium_crypto_sign_detached($payloadB64, $this->secretKey);

        $cert = [
            'version' => 1,
            'algorithm' => 'Ed25519',
            'payload_b64' => $payloadB64,
            'signature_b64' => base64_encode($signature),
        ];

        File::put($this->licensePath, json_encode($cert));
    }

    public function test_sync_push_job_skips_when_cloud_sync_disabled(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);
        config(['license.cloud_sync_enabled' => false]);

        $job = new SyncPushJob();
        $result = app()->call([$job, 'handle']);

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('CLOUD_SYNC_ENABLED is false', $result['reason']);
    }

    public function test_sync_push_job_sends_pending_outbox_when_enabled(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);
        config(['license.cloud_sync_enabled' => true]);

        // Insert pending outbox record
        $outbox = SyncOutbox::create([
            'entity_type' => 'transaction',
            'entity_id' => '01m80txsampleulid999',
            'action' => 'create',
            'payload' => [
                'id' => '01m80txsampleulid999',
                'invoice_no' => 'INV-BR01-20261122-0099',
                'grand_total' => '25000.00',
            ],
            'status' => 'pending',
        ]);

        Http::fake([
            'http://127.0.0.1:8000/api/v1/sync/push' => Http::response([
                'status' => 'success',
                'message' => 'Synced',
            ], 200),
        ]);

        $job = new SyncPushJob();
        $result = app()->call([$job, 'handle']);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['processed_count']);

        $outbox->refresh();
        $this->assertEquals('synced', $outbox->status);
        $this->assertNotNull($outbox->synced_at);
    }

    public function test_profit_analytics_forbidden_in_standard_local_tier(): void
    {
        $this->createSignedLicense(LicensePlan::STANDARD_LOCAL);

        $response = $this->get('/reports/profit');
        $response->assertStatus(403);
    }

    public function test_profit_analytics_accessible_in_pro_cloud_sync_tier(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);

        $response = $this->get('/reports/profit');
        $response->assertStatus(200);
    }

    public function test_sipnap_export_forbidden_in_standard_local_tier(): void
    {
        $this->createSignedLicense(LicensePlan::STANDARD_LOCAL);

        $response = $this->get('/reports/sipnap');
        $response->assertStatus(403);
    }

    public function test_sipnap_export_accessible_in_pro_cloud_sync_tier(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);

        $response = $this->get('/reports/sipnap');
        $response->assertStatus(200);
    }
}
