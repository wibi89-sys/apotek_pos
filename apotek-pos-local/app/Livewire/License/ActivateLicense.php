<?php

declare(strict_types=1);

namespace App\Livewire\License;

use App\Services\Licensing\HwidService;
use App\Services\Licensing\LicenseService;
use Livewire\Component;

class ActivateLicense extends Component
{
    public string $licenseKey = '';
    public string $cloudHubUrl = '';
    public string $currentHwid = '';
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $isProcessing = false;

    public function mount(HwidService $hwidService): void
    {
        $this->currentHwid = $hwidService->getHwid();
        $this->cloudHubUrl = (string) config('license.cloud_hub_url', 'http://127.0.0.1:8000');
    }

    public function fillDemoLicense(): void
    {
        $this->licenseKey = 'APT-DEMO-PRO-2026';
    }

    public function activate(LicenseService $licenseService): void
    {
        $this->validate([
            'licenseKey' => ['required', 'string', 'min:10'],
            'cloudHubUrl' => ['required', 'url'],
        ], [
            'licenseKey.required' => 'Kunci lisensi wajib diisi.',
            'cloudHubUrl.required' => 'URL Server Cloud Hub wajib diisi.',
            'cloudHubUrl.url' => 'Format URL Cloud Hub tidak valid.',
        ]);

        $this->isProcessing = true;
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            $licenseService->activateOnline($this->licenseKey, $this->cloudHubUrl);
            $this->successMessage = 'Aktivasi lisensi berhasil! Sistem POS sekarang aktif & dapat digunakan offline.';
            $this->redirectRoute('license.info');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function render()
    {
        return view('livewire.license.activate-license')
            ->layout('components.layouts.app', ['title' => 'Aktivasi Lisensi POS Apotek']);
    }
}
