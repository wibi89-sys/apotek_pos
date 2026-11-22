<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Hardware\FingerprintService;
use App\Services\Licensing\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FingerprintAuthTest extends TestCase
{
    use RefreshDatabase;

    protected string $licensePath;
    protected string $keyPair;
    protected string $publicKey;
    protected string $secretKey;
    protected string $hwid = 'test-hwid-fingerprint';

    protected function setUp(): void
    {
        parent::setUp();

        $this->licensePath = storage_path('app/private/license_test.lic');
        File::ensureDirectoryExists(dirname($this->licensePath));

        $this->keyPair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($this->keyPair);
        $this->secretKey = sodium_crypto_sign_secretkey($this->keyPair);

        config([
            'license.public_key' => base64_encode($this->publicKey),
            'license.mock_hwid' => $this->hwid,
            'license.license_file_path' => $this->licensePath,
            'license.supervisor_pin' => '123456',
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
            $features['fingerprint_auth'] = true;
            $features['cloud_sync_push'] = true;
        }

        foreach ($featureOverrides as $k => $v) {
            $features[$k] = $v;
        }

        $payload = [
            'schema_version' => 1,
            'license_key' => 'APT-FP-TEST-0001',
            'customer_name' => 'Apotek Bio Test',
            'pharmacy_name' => 'Apotek Bio Sehat',
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

    public function test_fingerprint_route_forbidden_in_standard_local_tier(): void
    {
        $this->createSignedLicense(LicensePlan::STANDARD_LOCAL);

        // Standard Local tier has fingerprint_auth = false
        $response = $this->get('/settings/fingerprint');

        $response->assertStatus(403);
    }

    public function test_fingerprint_route_accessible_in_pro_cloud_sync_tier(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);

        $response = $this->get('/settings/fingerprint');

        $response->assertStatus(200);
    }

    public function test_fingerprint_enrollment_stores_template_for_user(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);

        $user = User::factory()->create([
            'name' => 'Apt. Siti Nurhaliza',
            'role' => UserRole::APOTEKER,
        ]);

        // Mock bridge enroll response
        Http::fake([
            'http://127.0.0.1:8765/api/v1/fingerprint/enroll' => Http::response([
                'status' => 'success',
                'message' => 'Pindai sidik jari berhasil.',
                'template' => 'FP_ISO19794_2_MOCK_TEST_TEMPLATE_123',
                'quality_score' => 95,
            ], 200),
        ]);

        $service = app(FingerprintService::class);
        $result = $service->enrollUser($user);

        $this->assertEquals('success', $result['status']);
        $user->refresh();
        $this->assertEquals('FP_ISO19794_2_MOCK_TEST_TEMPLATE_123', $user->fingerprint_template);
    }

    public function test_supervisor_authorization_matches_fingerprint(): void
    {
        $this->createSignedLicense(LicensePlan::PRO_CLOUD_SYNC);

        $supervisor = User::factory()->create([
            'name' => 'Apt. Budi Santoso',
            'role' => UserRole::APOTEKER,
            'fingerprint_template' => 'FP_STORED_TEMPLATE_SUPERVISOR',
            'is_active' => true,
        ]);

        Http::fake([
            'http://127.0.0.1:8765/api/v1/fingerprint/verify' => Http::response([
                'matched' => true,
                'confidence' => 98.5,
                'message' => 'Cocok',
            ], 200),
        ]);

        $service = app(FingerprintService::class);
        $auth = $service->verifySupervisor(candidateTemplate: 'FP_LIVE_CANDIDATE');

        $this->assertTrue($auth['authorized']);
        $this->assertEquals('fingerprint', $auth['method']);
        $this->assertEquals($supervisor->id, $auth['supervisor_id']);
    }

    public function test_supervisor_authorization_fallback_to_pin(): void
    {
        $this->createSignedLicense(LicensePlan::STANDARD_LOCAL); // Even on standard local

        $service = app(FingerprintService::class);

        // Verification via Supervisor PIN (123456)
        $auth = $service->verifySupervisor(candidateTemplate: null, pin: '123456');

        $this->assertTrue($auth['authorized']);
        $this->assertEquals('supervisor_pin', $auth['method']);

        // Wrong PIN fails
        $failedAuth = $service->verifySupervisor(candidateTemplate: null, pin: '000000');
        $this->assertFalse($failedAuth['authorized']);
    }
}
