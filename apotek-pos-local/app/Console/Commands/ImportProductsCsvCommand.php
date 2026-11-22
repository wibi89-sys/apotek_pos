<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\ProductImportExportService;
use Illuminate\Console\Command;

class ImportProductsCsvCommand extends Command
{
    protected $signature = 'inventory:import-csv {file?} {--no-default-stock : Do not seed default 100 stock}';
    protected $description = 'Import pharmacy medicine dataset from Excel/CSV file';

    public function handle(ProductImportExportService $service): int
    {
        $file = $this->argument('file') ?: base_path('database/data/obat_master.csv');

        if (!file_exists($file)) {
            $this->error("File tidak ditemukan pada path: {$file}");
            return 1;
        }

        $this->info("Memulai proses impor database obat dari: {$file}");
        $start = microtime(true);

        try {
            $res = $service->importFromCsv($file, !$this->option('no-default-stock'));
            $duration = round(microtime(true) - $start, 2);

            $this->newLine();
            $this->info("==================================================");
            $this->info("  IMPORT DATABASE OBAT BERHASIL SELESAI!");
            $this->info("==================================================");
            $this->line("• Total Baris Diproses : " . number_format($res['total_rows']));
            $this->line("• Produk Baru Ditambah : " . number_format($res['imported']));
            $this->line("• Produk Diperbarui   : " . number_format($res['updated']));
            $this->line("• Durasi Eksekusi      : {$duration} detik");
            $this->info("==================================================");

            return 0;
        } catch (\Throwable $e) {
            $this->error("Gagal melakukan import: " . $e->getMessage());
            return 1;
        }
    }
}
