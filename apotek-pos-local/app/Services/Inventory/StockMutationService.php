<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockMutationService
{
    /**
     * Atomically mutate batch stock and record the audit movement in stock_movements.
     *
     * @throws InsufficientStockException
     */
    public function recordMovement(
        ProductBatch $batch,
        int $qtyChangeBase,
        StockMovementType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $userId = null,
        ?string $notes = null
    ): StockMovement {
        // Enforce lockForUpdate inside transaction
        return DB::transaction(function () use (
            $batch,
            $qtyChangeBase,
            $type,
            $referenceType,
            $referenceId,
            $userId,
            $notes
        ) {
            // Lock batch row
            $lockedBatch = ProductBatch::where('id', $batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newStock = $lockedBatch->stock_base_unit + $qtyChangeBase;

            if ($newStock < 0) {
                throw new InsufficientStockException(
                    sprintf(
                        'Stok batch %s tidak mencukupi. Stok saat ini: %d, perubahan: %d.',
                        $lockedBatch->batch_number,
                        $lockedBatch->stock_base_unit,
                        $qtyChangeBase
                    )
                );
            }

            $lockedBatch->stock_base_unit = $newStock;
            $lockedBatch->save();

            return StockMovement::create([
                'branch_id' => $lockedBatch->branch_id,
                'product_id' => $lockedBatch->product_id,
                'batch_id' => $lockedBatch->id,
                'type' => $type,
                'qty_change_base' => $qtyChangeBase,
                'stock_after_base' => $newStock,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId,
                'notes' => $notes,
                'sync_status' => 'pending',
                'created_at' => now(),
            ]);
        });
    }
}
