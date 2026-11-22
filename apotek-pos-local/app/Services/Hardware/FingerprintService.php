<?php

declare(strict_types=1);

namespace App\Services\Hardware;

use App\Enums\LicenseFeature;
use App\Enums\UserRole;
use App\Exceptions\LicenseEntitlementException;
use App\Models\User;
use App\Services\Licensing\LicenseEntitlementService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class FingerprintService
{
    protected string $bridgeUrl;

    public function __construct(
        protected LicenseEntitlementService $entitlementService
    ) {
        $this->bridgeUrl = (string) config('hardware.bridge_url', env('HARDWARE_BRIDGE_URL', 'http://127.0.0.1:8765'));
    }

    /**
     * Check if fingerprint hardware authentication feature is enabled in license tier.
     */
    public function isFeatureEnabled(): bool
    {
        return $this->entitlementService->hasFeature(LicenseFeature::FINGERPRINT_AUTH);
    }

    /**
     * Get fingerprint hardware status from hardware bridge.
     */
    public function getHardwareStatus(): array
    {
        if (! $this->isFeatureEnabled()) {
            return [
                'status' => 'locked',
                'message' => 'Fitur Otentikasi Sidik Jari memerlukan lisensi Paket PRO atau ENTERPRISE.',
            ];
        }

        try {
            $response = Http::timeout(2)->get("{$this->bridgeUrl}/api/v1/fingerprint/status");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // Bridge unreachable
        }

        return [
            'status' => 'offline',
            'message' => 'Hardware bridge sidik jari tidak terhubung (Port 8765).',
        ];
    }

    /**
     * Enroll fingerprint sample for a user and save template to database.
     */
    public function enrollUser(User $user): array
    {
        if (! $this->isFeatureEnabled()) {
            throw new LicenseEntitlementException('Fitur Otentikasi Sidik Jari tidak aktif pada lisensi saat ini.');
        }

        try {
            $response = Http::timeout(10)->post("{$this->bridgeUrl}/api/v1/fingerprint/enroll");
            if (! $response->successful()) {
                throw new InvalidArgumentException('Gagal memindai sidik jari dari sensor hardware.');
            }

            $data = $response->json();
            $template = $data['template'];

            $user->fingerprint_template = $template;
            $user->save();

            return [
                'status' => 'success',
                'message' => "Sidik jari untuk pengguna {$user->name} berhasil didaftarkan.",
                'template_preview' => substr($template, 0, 20) . '...',
                'quality_score' => $data['quality_score'] ?? 95,
            ];
        } catch (\Throwable $e) {
            if ($e instanceof LicenseEntitlementException) {
                throw $e;
            }
            throw new InvalidArgumentException('Koneksi sensor sidik jari gagal: ' . $e->getMessage());
        }
    }

    /**
     * Verify supervisor authorization: supports live fingerprint verification or Supervisor PIN fallback.
     */
    public function verifySupervisor(?string $candidateTemplate = null, ?string $pin = null): array
    {
        // 1. PIN fallback (Always available for emergencies / offline)
        $masterPin = config('license.supervisor_pin', '123456');
        if (! empty($pin) && $pin === $masterPin) {
            return [
                'authorized' => true,
                'method' => 'supervisor_pin',
                'message' => 'Otorisasi Supervisor via PIN Darurat berhasil.',
            ];
        }

        // 2. Fingerprint verification (Requires PRO license and candidate template)
        if (! empty($candidateTemplate) && $this->isFeatureEnabled()) {
            // Find all supervisor users (Owner or Apoteker) who have enrolled fingerprints
            $supervisors = User::whereIn('role', [UserRole::OWNER, UserRole::APOTEKER])
                ->whereNotNull('fingerprint_template')
                ->where('is_active', true)
                ->get();

            foreach ($supervisors as $supervisor) {
                try {
                    $response = Http::timeout(2)->post("{$this->bridgeUrl}/api/v1/fingerprint/verify", [
                        'candidate_template' => $candidateTemplate,
                        'enrolled_template' => $supervisor->fingerprint_template,
                    ]);

                    if ($response->successful() && $response->json('matched') === true) {
                        return [
                            'authorized' => true,
                            'method' => 'fingerprint',
                            'supervisor_id' => $supervisor->id,
                            'supervisor_name' => $supervisor->name,
                            'confidence' => $response->json('confidence'),
                            'message' => "Otorisasi Supervisor via Sidik Jari berhasil ({$supervisor->name}).",
                        ];
                    }
                } catch (\Throwable) {
                    // Bridge timeout or error, continue to next or fail
                }
            }
        }

        return [
            'authorized' => false,
            'method' => 'none',
            'message' => 'Otorisasi gagal: Sidik jari tidak dikenali atau PIN Supervisor tidak valid.',
        ];
    }
}
