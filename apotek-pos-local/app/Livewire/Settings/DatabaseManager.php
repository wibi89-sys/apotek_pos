<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\DbBackupLog;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\StockIntegrityAuditService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DatabaseManager extends Component
{
    public ?string $feedbackMessage = null;
    public ?string $errorMessage = null;

    // Audit State
    public ?array $auditResult = null;
    public ?array $optimizationResult = null;

    // Restore modal state
    public bool $showRestoreModal = false;
    public ?string $selectedBackupId = null;
    public ?string $selectedBackupFilename = null;

    public function createBackup(): void
    {
        $this->resetMessages();

        try {
            $service = app(DatabaseBackupService::class);
            $log = $service->createBackup('manual');

            $this->feedbackMessage = "Backup database berhasil dibuat: {$log->filename} ({$log->formatted_size})";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal membuat backup: ' . $e->getMessage();
        }
    }

    public function runAudit(): void
    {
        $this->resetMessages();

        try {
            $service = app(StockIntegrityAuditService::class);
            $this->auditResult = $service->auditStockDiscrepancies();

            if ($this->auditResult['is_healthy']) {
                $this->feedbackMessage = 'Audit selesai: Seluruh stok fisik batch sinkron 100% dengan riwayat mutasi stok (Tidak ada selisih).';
            } else {
                $this->errorMessage = "Audit mendeteksi {$this->auditResult['discrepancies_count']} batch obat dengan selisih stok!";
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal melakukan audit: ' . $e->getMessage();
        }
    }

    public function optimizeDatabase(): void
    {
        $this->resetMessages();

        try {
            $service = app(StockIntegrityAuditService::class);
            $this->optimizationResult = $service->optimizeTables();

            $this->feedbackMessage = 'Tabel database berhasil dioptimasi & didefragmentasi.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal mengoptimasi database: ' . $e->getMessage();
        }
    }

    public function openRestoreModal(string $id, string $filename): void
    {
        $this->selectedBackupId = $id;
        $this->selectedBackupFilename = $filename;
        $this->showRestoreModal = true;
    }

    public function executeRestore(): void
    {
        $this->resetMessages();

        if (! $this->selectedBackupId) {
            $this->errorMessage = 'Pilih file backup yang ingin di-restore.';
            return;
        }

        $log = DbBackupLog::find($this->selectedBackupId);
        if (! $log || ! File::exists($log->storage_path)) {
            $this->errorMessage = 'File backup tidak ditemukan di disk lokal.';
            $this->showRestoreModal = false;
            return;
        }

        try {
            $restoreService = app(DatabaseRestoreService::class);
            $result = $restoreService->restoreFromZip($log->storage_path);

            $this->feedbackMessage = "Database berhasil dipulihkan dari arsip {$result['restored_file']}. Snapshot darurat otomatis disimpan ke {$result['pre_restore_backup']}.";
            $this->showRestoreModal = false;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Restore gagal: ' . $e->getMessage();
            $this->showRestoreModal = false;
        }
    }

    protected function resetMessages(): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $backups = DbBackupLog::orderBy('created_at', 'desc')->take(20)->get();

        return view('livewire.settings.database-manager', [
            'backups' => $backups,
        ]);
    }
}
