<?php

declare(strict_types=1);

namespace App\Livewire\License;

use App\Services\Licensing\HwidService;
use App\Services\Licensing\LicenseService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ActivateLicense extends Component
{
    use WithFileUploads;

    public string $activationMode = 'online'; // 'online' or 'file'
    public string $licenseKey = '';
    public string $cloudHubUrl = '';
    public string $currentHwid = '';
    public $licenseFile = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $isProcessing = false;

    public function mount(HwidService $hwidService): void
    {
        $this->currentHwid = $hwidService->getHwid();
        $this->cloudHubUrl = (string) config('license.cloud_hub_url', 'http://127.0.0.1:8000');
    }

    public function setMode(string $mode): void
    {
        $this->activationMode = in_array($mode, ['online', 'file'], true) ? $mode : 'online';
        $this->errorMessage = null;
        $this->successMessage = null;
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

    public function activateOffline(LicenseService $licenseService): void
    {
        $this->validate([
            'licenseFile' => ['required', 'file', 'max:1024'],
        ], [
            'licenseFile.required' => 'File sertifikat lisensi (.lic) wajib dipilih.',
            'licenseFile.file' => 'File yang diunggah tidak valid.',
            'licenseFile.max' => 'Ukuran file lisensi maksimal 1MB.',
        ]);

        $this->isProcessing = true;
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            $content = file_get_contents($this->licenseFile->getRealPath());
            if ($content === false || empty(trim($content))) {
                throw new \RuntimeException('File lisensi yang diunggah kosong.');
            }

            $licenseService->activateWithFileContent($content);
            $this->successMessage = 'Aktivasi file lisensi offline berhasil! Sistem POS sekarang aktif.';
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
