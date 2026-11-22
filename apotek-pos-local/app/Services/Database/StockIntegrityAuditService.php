<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockIntegrityAuditService
{
    /**
     * Audit inventory integrity: compare SUM(stock_movements.qty_change_base) against product_batches.stock_base_unit.
     */
    public function auditStockDiscrepancies(): array
    {
        $batches = ProductBatch::with('product')->get();
        $discrepancies = [];

        foreach ($batches as $batch) {
            $movementSum = (int) StockMovement::where('batch_id', $batch->id)->sum('qty_change_base');
            $currentStock = (int) $batch->stock_base_unit;

            if ($movementSum !== $currentStock) {
                $discrepancies[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'product_name' => $batch->product?->name ?? 'Produk Tidak Diketahui',
                    'batch_stock' => $currentStock,
                    'audit_movement_sum' => $movementSum,
                    'difference' => $currentStock - $movementSum,
                ];
            }
        }

        return [
            'total_batches_checked' => $batches->count(),
            'discrepancies_count' => count($discrepancies),
            'discrepancies' => $discrepancies,
            'is_healthy' => count($discrepancies) === 0,
            'audited_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Defragment and optimize physical database tables.
     */
    public function optimizeTables(): array
    {
        $driver = DB::connection()->getDriverName();
        $optimized = [];

        if ($driver === 'mysql') {
            $tables = [
                'products',
                'product_units',
                'product_batches',
                'stock_movements',
                'transactions',
                'transaction_items',
                'shifts',
                'db_backup_logs',
            ];

            foreach ($tables as $table) {
                try {
                    DB::statement("OPTIMIZE TABLE `{$table}`");
                    $optimized[] = $table;
                } catch (\Throwable) {
                    // Skip if table doesn't exist yet
                }
            }
        } else {
            // SQLite VACUUM
            DB::statement('VACUUM');
            $optimized[] = 'sqlite_vacuum_completed';
        }

        return [
            'status' => 'success',
            'optimized_tables' => $optimized,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
