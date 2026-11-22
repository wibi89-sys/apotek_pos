<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Exceptions\ClockTamperedException;
use App\Exceptions\LicenseInvalidException;
use App\Models\SystemMeta;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class LicenseService
{
    private ?array $cachedPayload = null;

    public function __construct(
        protected HwidService $hwidService
    ) {}

    public function getLicenseFilePath(): string
    {
        return config('license.license_file_path', storage_path('app/private/license.lic'));
    }

    public function isActivated(): bool
    {
        try {
            $this->verifyOffline();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Perform strict offline Ed25519 signature, HWID, clock-tampering, and expiration checks.
     *
     * @throws LicenseInvalidException
     * @throws ClockTamperedException
     */
    public function verifyOffline(bool $updateClock = true): array
    {
        $path = $this->getLicenseFilePath();
        if (! File::exists($path)) {
            throw new LicenseInvalidException('File sertifikat lisensi tidak ditemukan (license.lic). Silakan lakukan aktivasi.');
        }

        $rawContent = File::get($path);
        $cert = json_decode($rawContent, true);

        if (! is_array($cert) || ! isset($cert['payload_b64'], $cert['signature_b64'])) {
            throw new LicenseInvalidException('Format sertifikat lisensi rusak atau tidak dikenali.');
        }

        if (($cert['algorithm'] ?? '') !== 'Ed25519') {
            throw new LicenseInvalidException('Algoritma kriptografi lisensi tidak didukung (harus Ed25519).');
        }

        $publicKeyB64 = config('license.public_key');
        if (empty($publicKeyB64)) {
            throw new LicenseInvalidException('LICENSE_PUBLIC_KEY belum dikonfigurasi di sistem POS.');
        }

        $publicKey = base64_decode($publicKeyB64, true);
        $signature = base64_decode($cert['signature_b64'], true);

        if ($publicKey === false || $signature === false) {
            throw new LicenseInvalidException('Kunci publik atau tanda tangan digital rusak.');
        }

        // 1. Verify detached Ed25519 signature
        $isValidSig = sodium_crypto_sign_verify_detached($signature, $cert['payload_b64'], $publicKey);
        if (! $isValidSig) {
            throw new LicenseInvalidException('Tanda tangan digital lisensi tidak valid (sertifikat telah dimanipulasi).');
        }

        // 2. Decode payload
        $payloadJson = base64_decode($cert['payload_b64'], true);
        if ($payloadJson === false) {
            throw new LicenseInvalidException('Payload lisensi tidak dapat didekode.');
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload) || ! isset($payload['license_key'], $payload['hwid'])) {
            throw new LicenseInvalidException('Struktur payload lisensi tidak valid.');
        }

        // 3. Verify Hardware ID binding
        $currentHwid = $this->hwidService->getHwid();
        if ($payload['hwid'] !== $currentHwid) {
            throw new LicenseInvalidException(
                sprintf('Lisensi terikat pada HWID berbeda (%s). Mesin ini: %s', $payload['hwid'], $currentHwid)
            );
        }

        // 4. Anti-Clock Tampering Check
        $encryptedLastSeen = SystemMeta::get('license_last_seen_at');
        if ($encryptedLastSeen) {
            try {
                $lastSeenIso = Crypt::decryptString($encryptedLastSeen);
                $lastSeen = Carbon::parse($lastSeenIso);

                // If system clock is moved back more than 5 minutes (300 seconds)
                if (now()->addSeconds(300)->isBefore($lastSeen)) {
                    throw new ClockTamperedException('Terdeteksi manipulasi jam sistem: waktu komputer dimundurkan lebih dari 5 menit.');
                }
            } catch (DecryptException) {
                throw new ClockTamperedException('Timestamp integritas anti-tampering sistem rusak.');
            }
        }

        if ($updateClock) {
            SystemMeta::set('license_last_seen_at', Crypt::encryptString(now()->toIso8601String()));
        }

        // 5. Expiration & Grace Period Check
        if (! empty($payload['expires_at'])) {
            $expiresAt = Carbon::createFromTimestamp($payload['expires_at']);
            $graceDays = (int) ($payload['grace_days'] ?? 14);
            $hardExpiry = $expiresAt->copy()->addDays($graceDays);

            if (now()->isAfter($hardExpiry)) {
                throw new LicenseInvalidException('Masa berlaku lisensi beserta masa tenggang (grace period) telah habis.');
            }
        }

        $this->cachedPayload = $payload;
        return $payload;
    }

    public function getLicenseData(): ?array
    {
        if ($this->cachedPayload !== null) {
            return $this->cachedPayload;
        }

        try {
            return $this->verifyOffline(updateClock: false);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getLicensePayload(): ?array
    {
        return $this->getLicenseData();
    }

    /**
     * Activate license via Cloud Hub API.
     *
     * @throws LicenseInvalidException
     */
    public function activateOnline(string $licenseKey, ?string $cloudHubUrl = null): array
    {
        $hubUrl = rtrim($cloudHubUrl ?? config('license.cloud_hub_url', 'http://127.0.0.1:8000'), '/');
        $hwid = $this->hwidService->getHwid();

        try {
            $response = Http::timeout(10)->post("{$hubUrl}/api/v1/license/activate", [
                'license_key' => trim($licenseKey),
                'hwid' => $hwid,
                'hostname' => gethostname(),
                'app_version' => '1.0.0',
            ]);
        } catch (\Throwable $e) {
            throw new LicenseInvalidException('Gagal menghubungi server Cloud Hub: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            $errorMsg = $response->json('message') ?? 'Aktivasi ditolak oleh Cloud Hub.';
            throw new LicenseInvalidException($errorMsg);
        }

        $cert = $response->json('certificate');
        if (! is_array($cert)) {
            throw new LicenseInvalidException('Respon server tidak memuat sertifikat lisensi yang valid.');
        }

        // Ensure private storage directory exists
        $filePath = $this->getLicenseFilePath();
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, json_encode($cert, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Initialize last_seen_at
        SystemMeta::set('license_last_seen_at', Crypt::encryptString(now()->toIso8601String()));

        // Return verified payload
        return $this->verifyOffline();
    }

    /**
     * Activate license offline using a signed certificate file (.lic content).
     *
     * @throws LicenseInvalidException
     */
    public function activateWithFileContent(string $rawContent): array
    {
        $cert = json_decode(trim($rawContent), true);
        if (! is_array($cert) || ! isset($cert['payload_b64'], $cert['signature_b64'])) {
            throw new LicenseInvalidException('Format file lisensi (.lic) rusak atau tidak valid.');
        }

        if (($cert['algorithm'] ?? '') !== 'Ed25519') {
            throw new LicenseInvalidException('Algoritma sertifikat lisensi tidak didukung (harus Ed25519).');
        }

        $publicKeyB64 = config('license.public_key');
        if (empty($publicKeyB64)) {
            throw new LicenseInvalidException('LICENSE_PUBLIC_KEY belum dikonfigurasi di sistem POS.');
        }

        $publicKey = base64_decode($publicKeyB64, true);
        $signature = base64_decode($cert['signature_b64'], true);

        if ($publicKey === false || $signature === false) {
            throw new LicenseInvalidException('Kunci publik atau tanda tangan digital dalam file lisensi rusak.');
        }

        // 1. Verify detached signature
        $isValidSig = sodium_crypto_sign_verify_detached($signature, $cert['payload_b64'], $publicKey);
        if (! $isValidSig) {
            throw new LicenseInvalidException('Tanda tangan digital tidak valid! File lisensi telah dimanipulasi atau tidak diterbitkan oleh Laksana Hub.');
        }

        // 2. Decode payload
        $payloadJson = base64_decode($cert['payload_b64'], true);
        $payload = json_decode($payloadJson ?: '', true);

        if (! is_array($payload) || ! isset($payload['license_key'], $payload['hwid'])) {
            throw new LicenseInvalidException('Struktur payload sertifikat lisensi tidak valid.');
        }

        // 3. Verify Hardware ID binding
        $currentHwid = $this->hwidService->getHwid();
        if ($payload['hwid'] !== $currentHwid) {
            throw new LicenseInvalidException(
                sprintf('File lisensi ini terikat pada HWID mesin lain (%s). Mesin ini: %s', $payload['hwid'], $currentHwid)
            );
        }

        // Save to target path
        $filePath = $this->getLicenseFilePath();
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, json_encode($cert, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Initialize last_seen_at
        SystemMeta::set('license_last_seen_at', Crypt::encryptString(now()->toIso8601String()));

        // Clear cached payload and verify
        $this->cachedPayload = null;
        return $this->verifyOffline();
    }
}
