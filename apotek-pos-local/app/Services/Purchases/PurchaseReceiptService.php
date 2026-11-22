<?php

declare(strict_types=1);

namespace App\Services\Purchases;

use App\Enums\StockMovementType;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\Inventory\StockMutationService;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptService
{
    public function __construct(
        protected StockMutationService $mutationService
    ) {}

    /**
     * Process PBF incoming invoice receipt, update HPP and mutate incoming stock.
     */
    public function recordReceipt(array $invoiceData, array $itemsData, ?string $userId = null): Purchase
    {
        return DB::transaction(function () use ($invoiceData, $itemsData, $userId) {
            $branchId = (string) config('license.branch_id', 'BR01');

            $purchase = Purchase::create([
                'invoice_no' => $invoiceData['invoice_no'],
                'supplier_id' => $invoiceData['supplier_id'],
                'branch_id' => $branchId,
                'user_id' => $userId,
                'purchase_date' => $invoiceData['purchase_date'],
                'due_date' => $invoiceData['due_date'] ?? null,
                'subtotal' => (string) $invoiceData['subtotal'],
                'tax_amount' => (string) ($invoiceData['tax_amount'] ?? '0.00'),
                'grand_total' => (string) $invoiceData['grand_total'],
                'payment_status' => $invoiceData['payment_status'] ?? 'paid',
                'notes' => $invoiceData['notes'] ?? null,
                'sync_status' => 'pending',
            ]);

            foreach ($itemsData as $item) {
                $qtyInput = (float) $item['qty_input'];
                $conversion = (int) ($item['conversion_factor'] ?? 1);
                $qtyBase = (int) round($qtyInput * $conversion);

                $buyPriceUnit = (float) $item['buy_price_unit'];
                $buyPriceBase = bcdiv((string) $buyPriceUnit, (string) $conversion, 2);
                $itemSubtotal = bcmul((string) $buyPriceUnit, (string) $qtyInput, 2);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'batch_number' => $item['batch_number'],
                    'expired_date' => $item['expired_date'],
                    'unit_name' => $item['unit_name'],
                    'conversion_factor' => $conversion,
                    'qty_input' => (string) $qtyInput,
                    'qty_base' => $qtyBase,
                    'buy_price_unit' => (string) $buyPriceUnit,
                    'buy_price_base' => $buyPriceBase,
                    'subtotal' => $itemSubtotal,
                ]);

                // Find or create product batch
                $batch = ProductBatch::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'product_id' => $item['product_id'],
                        'batch_number' => $item['batch_number'],
                    ],
                    [
                        'expired_date' => $item['expired_date'],
                        'stock_base_unit' => 0,
                        'buy_price_base' => $buyPriceBase,
                        'supplier_id' => $purchase->supplier_id,
                        'received_at' => now(),
                    ]
                );

                // Update HPP snapshot and expired date if updated
                $batch->buy_price_base = $buyPriceBase;
                $batch->expired_date = $item['expired_date'];
                $batch->supplier_id = $purchase->supplier_id;
                $batch->save();

                // Mutate stock in
                $this->mutationService->recordMovement(
                    batch: $batch,
                    qtyChangeBase: $qtyBase,
                    type: StockMovementType::IN_PURCHASE,
                    referenceType: 'purchase',
                    referenceId: $purchase->invoice_no,
                    userId: $userId,
                    notes: sprintf('Penerimaan faktur PBF #%s', $purchase->invoice_no)
                );
            }

            return $purchase;
        });
    }
}
