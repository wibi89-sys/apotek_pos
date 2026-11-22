<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Models\ActiveTerminal;
use App\Models\SystemMeta;
use App\Services\Licensing\HwidService;
use App\Services\Licensing\LicenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected string $testKeypairPrivate;
    protected string $testKeypairPublic;
    protected string $licensePath;
    protected string $mockHwid;

    protected function setUp(): void
    {
        parent::setUp();

        $keypair = sodium_crypto_sign_keypair();
        $this->testKeypairPrivate = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $this->testKeypairPublic = base64_encode(sodium_crypto_sign_publickey($keypair));

        $this->mockHwid = hash('sha256', 'TEST_MACHINE_123');
        $this->licensePath = storage_path('app/private/test_license.lic');

        config([
            'license.public_key' => $this->testKeypairPublic,
            'license.mock_hwid' => $this->mockHwid,
            'license.cloud_hub_url' => 'http://127.0.0.1:8000',
            'license.license_file_path' => $this->licensePath,
            'license.terminal_id' => 'POS01',
        ]);

        if (File::exists($this->licensePath)) {
            File::delete($this->licensePath);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->licensePath)) {
            File::delete($this->licensePath);
        }
        parent::tearDown();
    }

    /**
     * Helper to generate a signed certificate for tests.
     */
    protected function createSignedCertificate(
        array $overridePayload = [],
        ?string $privateKeyB64 = null
    ): array {
        $payload = array_merge([
            'schema_version' => 1,
            'license_key' => 'APT-TEST-0001-0002',
            'customer_name' => 'Apoteker Uji',
            'pharmacy_name' => 'Apotek Sehat Uji',
            'branch_code' => 'BR01',
            'hwid' => $this->mockHwid,
            'plan' => LicensePlan::STANDARD_LOCAL->value,
            'billing_type' => 'lifetime',
            'issued_at' => now()->timestamp,
            'expires_at' => null,
            'grace_days' => 14,
            'heartbeat_interval_days' => 0,
            'limits' => [
                'max_terminals' => 2,
                'max_branches' => 1,
                'max_users' => 5,
            ],
            'features' => [
                'fingerprint_auth' => false,
                'label_printing' => false,
                'offsite_backup' => false,
                'cloud_sync_push' => false,
                'sipnap_export' => false,
                'profit_analytics' => false,
                'cloud_sync_pull' => false,
                'multi_branch_ops' => false,
                'external_api' => false,
            ],
        ], $overridePayload);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = base64_encode($payloadJson);

        $privateKey = base64_decode($privateKeyB64 ?? $this->testKeypairPrivate);
        $sig = sodium_crypto_sign_detached($payloadB64, $privateKey);

        return [
            'version' => 1,
            'algorithm' => 'Ed25519',
            'payload_b64' => $payloadB64,
            'signature_b64' => base64_encode($sig),
        ];
    }

    public function test_pos_is_locked_before_activation(): void
    {
        $response = $this->get('/pos');
        $response->assertRedirect(route('license.activate'));
    }

    public function test_online_activation_stores_license_and_runs_offline(): void
    {
        $cert = $this->createSignedCertificate();

        Http::fake([
            'http://127.0.0.1:8000/api/v1/license/activate' => Http::response([
                'status' => 'success',
                'message' => 'Lisensi berhasil diaktivasi.',
                'certificate' => $cert,
            ], 200),
        ]);

        $service = app(LicenseService::class);
        $payload = $service->activateOnline('APT-TEST-0001-0002');

        $this->assertEquals('APT-TEST-0001-0002', $payload['license_key']);
        $this->assertFileExists($this->licensePath);

        // Subsequent access runs 100% offline (Http::fake will fail if any request is made)
        Http::fake(function () {
            throw new \Exception('No HTTP requests should be made for offline POS operations!');
        });

        $posResponse = $this->get('/pos/shift');
        $posResponse->assertStatus(200);
    }

    public function test_rejects_mismatched_hwid(): void
    {
        // Certificate bound to a different HWID
        $cert = $this->createSignedCertificate([
            'hwid' => hash('sha256', 'DIFFERENT_MACHINE_XYZ'),
        ]);

        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($cert));

        $response = $this->get('/pos');
        $response->assertRedirect(route('license.activate'));
        $this->assertStringContainsString('HWID berbeda', session('error') ?? '');
    }

    public function test_rejects_tampered_license_file(): void
    {
        $cert = $this->createSignedCertificate();

        // Tamper 1 character in signature
        $sig = $cert['signature_b64'];
        $tamperedSig = ($sig[0] === 'A' ? 'B' : 'A') . substr($sig, 1);
        $cert['signature_b64'] = $tamperedSig;

        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($cert));

        $response = $this->get('/pos');
        $response->assertRedirect(route('license.activate'));
        $this->assertStringContainsString('dimanipulasi', session('error') ?? '');
    }

    public function test_rejects_clock_tampered_backward_more_than_5_minutes(): void
    {
        $cert = $this->createSignedCertificate();
        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($cert));

        // Simulate last_seen_at was recorded 10 minutes in the future relative to current time
        $futureTime = now()->addMinutes(10)->toIso8601String();
        SystemMeta::set('license_last_seen_at', Crypt::encryptString($futureTime));

        $response = $this->get('/pos');
        $response->assertRedirect(route('license.activate'));
        $this->assertStringContainsString('Terdeteksi manipulasi jam', session('error') ?? '');
    }

    public function test_feature_entitlement_standard_local_vs_pro_cloud_sync(): void
    {
        // 1. STANDARD_LOCAL: features false
        $standardCert = $this->createSignedCertificate([
            'plan' => LicensePlan::STANDARD_LOCAL->value,
            'features' => [
                'fingerprint_auth' => false,
                'cloud_sync_push' => false,
            ],
        ]);
        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($standardCert));

        // Test standard local access to fingerprint and sync
        $this->get('/test/feature-fingerprint')->assertStatus(403);
        $this->get('/test/feature-cloud-sync')->assertStatus(403);

        // 2. PRO_CLOUD_SYNC: features true
        $proCert = $this->createSignedCertificate([
            'plan' => LicensePlan::PRO_CLOUD_SYNC->value,
            'features' => [
                'fingerprint_auth' => true,
                'cloud_sync_push' => true,
            ],
        ]);
        File::put($this->licensePath, json_encode($proCert));

        $this->get('/test/feature-fingerprint')->assertStatus(200);
        $this->get('/test/feature-cloud-sync')->assertStatus(200);
    }

    public function test_terminal_quota_enforcement(): void
    {
        $cert = $this->createSignedCertificate([
            'limits' => ['max_terminals' => 2],
        ]);
        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($cert));

        // Register 2 active terminals within last 15 minutes
        ActiveTerminal::create([
            'terminal_id' => 'POS01',
            'hostname' => 'KASIR-01',
            'ip_address' => '127.0.0.1',
            'last_ping_at' => now(),
        ]);
        ActiveTerminal::create([
            'terminal_id' => 'POS02',
            'hostname' => 'KASIR-02',
            'ip_address' => '127.0.0.2',
            'last_ping_at' => now(),
        ]);

        // Terminal POS01 is within quota -> 200 OK
        config(['license.terminal_id' => 'POS01']);
        $this->get('/pos/shift')->assertStatus(200);

        // Terminal POS03 exceeds quota (2 active terminals exist) -> 403 Forbidden
        config(['license.terminal_id' => 'POS03']);
        $this->get('/pos/shift')->assertStatus(403);
    }

    public function test_offline_operation_when_cloud_hub_is_down(): void
    {
        $cert = $this->createSignedCertificate();
        File::ensureDirectoryExists(dirname($this->licensePath));
        File::put($this->licensePath, json_encode($cert));

        // Simulate Cloud Hub is completely down/unreachable
        Http::fake([
            'http://127.0.0.1:8000/*' => Http::response(null, 500),
        ]);

        // POS operations and info MUST continue to work without error
        $response = $this->get('/pos/shift');
        $response->assertStatus(200);

        $infoResponse = $this->get(route('license.info'));
        $infoResponse->assertStatus(200);
    }

    public function test_can_activate_offline_using_file_content_with_valid_signature_and_hwid(): void
    {
        $cert = $this->createSignedCertificate([
            'license_key' => 'APT-OFFLINE-RESTORE-2026',
            'customer_name' => 'Dokter Andi',
            'pharmacy_name' => 'Apotek Sehat Offline',
        ]);

        $jsonString = json_encode($cert);

        $licenseService = app(LicenseService::class);
        $verifiedPayload = $licenseService->activateWithFileContent($jsonString);

        $this->assertEquals('APT-OFFLINE-RESTORE-2026', $verifiedPayload['license_key']);
        $this->assertEquals('Apotek Sehat Offline', $verifiedPayload['pharmacy_name']);
        $this->assertTrue($licenseService->isActivated());
        $this->assertFileExists($this->licensePath);
    }

    public function test_rejects_offline_activation_if_file_has_different_hwid(): void
    {
        $cert = $this->createSignedCertificate([
            'hwid' => 'DIFFERENT_MACHINE_HWID_999',
        ]);

        $this->expectException(\App\Exceptions\LicenseInvalidException::class);
        $this->expectExceptionMessage('File lisensi ini terikat pada HWID mesin lain');

        $licenseService = app(LicenseService::class);
        $licenseService->activateWithFileContent(json_encode($cert));
    }

    public function test_rejects_offline_activation_if_file_tampered(): void
    {
        $cert = $this->createSignedCertificate();
        // Modify payload without resigning
        $payload = json_decode(base64_decode($cert['payload_b64']), true);
        $payload['plan'] = 'PRO_ENTERPRISE_HACKED';
        $cert['payload_b64'] = base64_encode(json_encode($payload));

        $this->expectException(\App\Exceptions\LicenseInvalidException::class);
        $this->expectExceptionMessage('Tanda tangan digital tidak valid');

        $licenseService = app(LicenseService::class);
        $licenseService->activateWithFileContent(json_encode($cert));
    }
}
