<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;

class StockFefoService
{
    public function __construct(
        protected StockMutationService $mutationService
    ) {}

    /**
     * Deduct medicine stock following strict First-Expired, First-Out (FEFO) rules.
     * Automatically splits deduction across multiple batches if necessary.
     *
     * @return array<int, array{batch_id: string, batch_number: string, expired_date: string, qty_deducted_base: int, buy_price_base: string}>
     *
     * @throws InsufficientStockException
     */
    public function deductStock(
        Product $product,
        int $neededBaseQty,
        string $referenceType,
        string $referenceId,
        ?string $userId = null,
        ?string $notes = null
    ): array {
        if ($neededBaseQty <= 0) {
            return [];
        }

        return DB::transaction(function () use (
            $product,
            $neededBaseQty,
            $referenceType,
            $referenceId,
            $userId,
            $notes
        ) {
            $today = now()->toDateString();

            // Query active, unexpired batches ordered by nearest expiration date
            $batches = ProductBatch::where('product_id', $product->id)
                ->where('expired_date', '>', $today)
                ->where('stock_base_unit', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalAvailable = (int) $batches->sum('stock_base_unit');

            if ($totalAvailable < $neededBaseQty) {
                throw new InsufficientStockException(
                    sprintf(
                        "Stok obat '%s' tidak mencukupi untuk memenuhi %d %s (Tersedia belum expired: %d).",
                        $product->name,
                        $neededBaseQty,
                        $product->base_unit_name,
                        $totalAvailable
                    )
                );
            }

            $remainingNeeded = $neededBaseQty;
            $allocations = [];

            foreach ($batches as $batch) {
                if ($remainingNeeded <= 0) {
                    break;
                }

                $availableInBatch = $batch->stock_base_unit;
                $deductFromBatch = min($availableInBatch, $remainingNeeded);

                // Perform mutation
                $this->mutationService->recordMovement(
                    batch: $batch,
                    qtyChangeBase: -$deductFromBatch,
                    type: StockMovementType::OUT_SALE,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    userId: $userId,
                    notes: $notes ?? sprintf('Pengurangan FEFO untuk %s #%s', $referenceType, $referenceId)
                );

                $allocations[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expired_date' => $batch->expired_date->format('Y-m-d'),
                    'qty_deducted_base' => $deductFromBatch,
                    'buy_price_base' => number_format((float) $batch->buy_price_base, 2, '.', ''),
                ];

                $remainingNeeded -= $deductFromBatch;
            }

            return $allocations;
        });
    }
}
