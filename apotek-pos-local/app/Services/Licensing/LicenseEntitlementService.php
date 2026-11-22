<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use Carbon\Carbon;

class LicenseEntitlementService
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    public function getPayload(): ?array
    {
        return $this->licenseService->getLicenseData();
    }

    public function getPlan(): ?LicensePlan
    {
        $payload = $this->getPayload();
        if (! $payload || empty($payload['plan'])) {
            return null;
        }

        return LicensePlan::tryFrom($payload['plan']);
    }

    public function getLicenseKey(): ?string
    {
        return $this->getPayload()['license_key'] ?? null;
    }

    public function getPharmacyName(): string
    {
        return $this->getPayload()['pharmacy_name'] ?? 'Apotek';
    }

    public function getBranchCode(): string
    {
        return $this->getPayload()['branch_code'] ?? 'BR01';
    }

    public function hasFeature(LicenseFeature|string $feature): bool
    {
        $featureKey = $feature instanceof LicenseFeature ? $feature->value : $feature;
        $payload = $this->getPayload();

        if (! $payload || ! isset($payload['features'])) {
            return false;
        }

        return (bool) ($payload['features'][$featureKey] ?? false);
    }

    public function getMaxTerminals(): ?int
    {
        $limits = $this->getPayload()['limits'] ?? [];
        return isset($limits['max_terminals']) ? (int) $limits['max_terminals'] : null;
    }

    public function getMaxUsers(): ?int
    {
        $limits = $this->getPayload()['limits'] ?? [];
        return isset($limits['max_users']) ? (int) $limits['max_users'] : null;
    }

    public function getMaxBranches(): ?int
    {
        $limits = $this->getPayload()['limits'] ?? [];
        return isset($limits['max_branches']) ? (int) $limits['max_branches'] : null;
    }

    public function isGracePeriod(): bool
    {
        $payload = $this->getPayload();
        if (! $payload || empty($payload['expires_at'])) {
            return false;
        }

        $expiresAt = Carbon::createFromTimestamp($payload['expires_at']);
        $graceDays = (int) ($payload['grace_days'] ?? 14);
        $hardExpiry = $expiresAt->copy()->addDays($graceDays);

        return now()->isAfter($expiresAt) && now()->isBefore($hardExpiry);
    }

    public function isExpired(): bool
    {
        $payload = $this->getPayload();
        if (! $payload || empty($payload['expires_at'])) {
            return false;
        }

        $expiresAt = Carbon::createFromTimestamp($payload['expires_at']);
        $graceDays = (int) ($payload['grace_days'] ?? 14);
        $hardExpiry = $expiresAt->copy()->addDays($graceDays);

        return now()->isAfter($hardExpiry);
    }

    public function daysUntilExpiration(): ?int
    {
        $payload = $this->getPayload();
        if (! $payload || empty($payload['expires_at'])) {
            return null;
        }

        $expiresAt = Carbon::createFromTimestamp($payload['expires_at']);
        return (int) now()->diffInDays($expiresAt, false);
    }
}
