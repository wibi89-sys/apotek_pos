<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DrugClass;
use App\Enums\StockMovementType;
use App\Models\DbBackupLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Database\DatabaseBackupService;
use App\Services\Database\DatabaseRestoreService;
use App\Services\Database\StockIntegrityAuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class DatabaseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up test backup files from storage
        $backupDir = storage_path('app/backups');
        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                File::delete($file->getRealPath());
            }
        }

        parent::tearDown();
    }

    public function test_backup_service_creates_valid_zip_and_logs(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $log = $backupService->createBackup('manual');

        $this->assertInstanceOf(DbBackupLog::class, $log);
        $this->assertEquals('completed', $log->status);
        $this->assertEquals('manual', $log->trigger_type);
        $this->assertGreaterThan(0, $log->file_size_bytes);
        $this->assertTrue(File::exists($log->storage_path));

        // Verify ZIP contents
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($log->storage_path) === true);
        $this->assertGreaterThan(0, $zip->numFiles);
        $zip->close();
    }

    public function test_retention_cleanup_removes_old_backups(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $backupDir = $backupService->getBackupDirectory();

        // Create an old physical zip file and log dated 40 days ago
        $oldFilename = 'backup_BR01_manual_2026-01-01_old.zip';
        $oldPath = "{$backupDir}/{$oldFilename}";
        File::put($oldPath, 'dummy-zip-content');
        touch($oldPath, Carbon::now()->subDays(40)->timestamp);

        $oldLog = DbBackupLog::create([
            'filename' => $oldFilename,
            'file_size_bytes' => 100,
            'storage_path' => $oldPath,
            'trigger_type' => 'manual',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(40),
        ]);

        $this->assertTrue(File::exists($oldPath));
        $this->assertDatabaseHas('db_backup_logs', ['id' => $oldLog->id]);

        // Run cleanup with 30 days retention
        $deleted = $backupService->cleanupOldBackups(30);

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertFalse(File::exists($oldPath));
        $this->assertDatabaseMissing('db_backup_logs', ['id' => $oldLog->id]);
    }

    public function test_restore_service_enforces_mandatory_pre_restore_emergency_snapshot(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $initialBackup = $backupService->createBackup('manual');

        $restoreService = app(DatabaseRestoreService::class);
        $result = $restoreService->restoreFromZip($initialBackup->storage_path);

        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['pre_restore_backup']);

        // Verify that a pre_restore backup log was automatically created
        $this->assertDatabaseHas('db_backup_logs', [
            'trigger_type' => 'pre_restore',
            'status' => 'completed',
        ]);
    }

    public function test_stock_integrity_audit_detects_discrepancy(): void
    {
        $product = Product::create([
            'sku' => 'AUDIT-TEST-01',
            'barcode' => '8999999999991',
            'name' => 'Vitamin C 500mg',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'VITC-01',
            'expired_date' => now()->addDays(200),
            'stock_base_unit' => 50, // Physical stock is 50
            'buy_price_base' => '500.00',
        ]);

        // But movement history only has +40 (Discrepancy of 10)
        StockMovement::create([
            'branch_id' => 'BR01',
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'type' => StockMovementType::IN_PURCHASE,
            'qty_change_base' => 40,
            'stock_after_base' => 40,
        ]);

        $auditService = app(StockIntegrityAuditService::class);
        $report = $auditService->auditStockDiscrepancies();

        $this->assertFalse($report['is_healthy']);
        $this->assertEquals(1, $report['discrepancies_count']);
        $this->assertEquals(10, $report['discrepancies'][0]['difference']);
    }

    public function test_stock_integrity_audit_healthy_when_movements_match_stock(): void
    {
        $product = Product::create([
            'sku' => 'AUDIT-TEST-02',
            'barcode' => '8999999999992',
            'name' => 'Vitamin B Complex',
            'drug_class' => DrugClass::BEBAS,
            'base_unit_name' => 'Tablet',
            'is_active' => true,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => 'VITB-01',
            'expired_date' => now()->addDays(200),
            'stock_base_unit' => 100,
            'buy_price_base' => '400.00',
        ]);

        StockMovement::create([
            'branch_id' => 'BR01',
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'type' => StockMovementType::IN_PURCHASE,
            'qty_change_base' => 100,
            'stock_after_base' => 100,
        ]);

        $auditService = app(StockIntegrityAuditService::class);
        $report = $auditService->auditStockDiscrepancies();

        $this->assertTrue($report['is_healthy']);
        $this->assertEquals(0, $report['discrepancies_count']);
    }

    public function test_emergency_download_endpoint_provides_backup_zip(): void
    {
        $response = $this->get(route('db.emergency-download'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'zip'));
    }
}
