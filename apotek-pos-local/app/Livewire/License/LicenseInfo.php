<?php

declare(strict_types=1);

namespace App\Livewire\License;

use App\Enums\LicenseFeature;
use App\Services\Licensing\LicenseEntitlementService;
use App\Services\Licensing\LicenseService;
use Livewire\Component;

class LicenseInfo extends Component
{
    public function refreshLicense(LicenseService $licenseService): void
    {
        try {
            $payload = $licenseService->verifyOffline();
            $licenseKey = $payload['license_key'] ?? '';
            $licenseService->activateOnline($licenseKey);
            session()->flash('success', 'Sertifikat lisensi berhasil direfresh secara online!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal refresh lisensi: ' . $e->getMessage());
        }
    }

    public function render(LicenseEntitlementService $entitlement)
    {
        $payload = $entitlement->getPayload();
        $plan = $entitlement->getPlan();
        $features = LicenseFeature::cases();

        return view('livewire.license.license-info', [
            'payload' => $payload,
            'plan' => $plan,
            'features' => $features,
            'entitlement' => $entitlement,
        ])->layout('components.layouts.app', ['title' => 'Informasi Lisensi']);
    }
}
