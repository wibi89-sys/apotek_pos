<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\Inventory\StockFefoService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompletePosTransactionAction
{
    public function __construct(
        protected StockFefoService $fefoService
    ) {}

    /**
     * Execute final POS checkout: deduct stock via FEFO, snapshot HPP per batch, and record invoice.
     */
    public function execute(
        Shift $shift,
        User $cashier,
        TransactionType $type,
        array $cartItems,
        PaymentMethod $paymentMethod,
        string $paidAmount,
        string $tuslahEmbalaseTotal = '0.00',
        string $discountTotal = '0.00',
        string $taxTotal = '0.00',
        ?string $patientName = null,
        ?string $doctorName = null,
        ?string $authorizedByUserId = null
    ): Transaction {
        if (empty($cartItems)) {
            throw new InvalidArgumentException('Keranjang belanja kasir kosong.');
        }

        return DB::transaction(function () use (
            $shift,
            $cashier,
            $type,
            $cartItems,
            $paymentMethod,
            $paidAmount,
            $tuslahEmbalaseTotal,
            $discountTotal,
            $taxTotal,
            $patientName,
            $doctorName,
            $authorizedByUserId
        ) {
            $branchId = (string) config('license.branch_id', 'BR01');
            $terminalId = (string) config('license.terminal_id', 'POS01');
            $invoiceNo = Transaction::generateInvoiceNo($branchId);

            // 1. Calculate Subtotal using bcmath
            $subtotal = '0.00';
            foreach ($cartItems as $item) {
                $lineTotal = bcmul((string) $item['unit_price'], (string) $item['qty_input'], 2);
                $discountItem = (string) ($item['discount_item'] ?? '0.00');
                $lineNet = bcsub($lineTotal, $discountItem, 2);
                $subtotal = bcadd($subtotal, $lineNet, 2);
            }

            // Grand Total: subtotal + tuslah + tax - discount
            $withTuslah = bcadd($subtotal, $tuslahEmbalaseTotal, 2);
            $withTax = bcadd($withTuslah, $taxTotal, 2);
            $grandTotal = bcsub($withTax, $discountTotal, 2);

            // Validate cash payment
            if ($paymentMethod === PaymentMethod::CASH) {
                if (bccomp($paidAmount, $grandTotal, 2) === -1) {
                    throw new InvalidArgumentException(
                        sprintf('Jumlah pembayaran tunai (Rp %s) kurang dari total tagihan (Rp %s).', $paidAmount, $grandTotal)
                    );
                }
                $changeAmount = bcsub($paidAmount, $grandTotal, 2);
            } else {
                // Non-cash (QRIS, Debit, Transfer) requires exact amount
                $paidAmount = $grandTotal;
                $changeAmount = '0.00';
            }

            // 2. Create Transaction Header
            $transaction = Transaction::create([
                'invoice_no' => $invoiceNo,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'type' => $type,
                'patient_name' => $patientName,
                'doctor_name' => $doctorName,
                'subtotal' => $subtotal,
                'tuslah_embalase_total' => $tuslahEmbalaseTotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'status' => TransactionStatus::COMPLETED,
                'authorized_by_user_id' => $authorizedByUserId,
                'sync_status' => 'pending',
            ]);

            // 3. Deduct Stock via FEFO & Record Transaction Items
            foreach ($cartItems as $item) {
                $product = Product::findOrFail($item['product_id']);
                $conversion = (int) ($item['conversion_factor'] ?? 1);
                $qtyInput = (float) $item['qty_input'];
                $neededBase = (int) round($qtyInput * $conversion);

                // Run FEFO deduction across unexpired batches
                $allocations = $this->fefoService->deductStock(
                    product: $product,
                    neededBaseQty: $neededBase,
                    referenceType: 'transaction',
                    referenceId: $transaction->invoice_no,
                    userId: $cashier->id,
                    notes: sprintf('Penjualan Kasir POS #%s (%s)', $transaction->invoice_no, $product->name)
                );

                $unitPrice = (string) $item['unit_price'];
                $discountItem = (string) ($item['discount_item'] ?? '0.00');

                // If split across multiple batches, create an item row per batch allocation
                foreach ($allocations as $alloc) {
                    $allocQtyBase = (int) $alloc['qty_deducted_base'];
                    $allocQtyInput = (string) round($allocQtyBase / $conversion, 2);
                    $allocSubtotal = bcmul($unitPrice, (string) $allocQtyInput, 2);

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'batch_id' => $alloc['batch_id'],
                        'product_unit_id' => $item['product_unit_id'] ?? null,
                        'is_racikan' => (bool) ($item['is_racikan'] ?? false),
                        'racikan_group_name' => $item['racikan_group_name'] ?? null,
                        'qty_input' => $allocQtyInput,
                        'conversion_factor' => $conversion,
                        'qty_base_deducted' => $allocQtyBase,
                        'unit_price' => $unitPrice,
                        'hpp_base_snapshot' => $alloc['buy_price_base'],
                        'discount_item' => $discountItem,
                        'subtotal' => $allocSubtotal,
                    ]);
                }
            }

            // Record outbox for Hybrid Cloud Sync
            try {
                \App\Models\SyncOutbox::create([
                    'entity_type' => 'transaction',
                    'entity_id' => $transaction->id,
                    'action' => 'create',
                    'payload' => [
                        'id' => $transaction->id,
                        'invoice_no' => $transaction->invoice_no,
                        'cashier_name' => $cashier->name,
                        'type' => $transaction->type->value,
                        'subtotal' => $transaction->subtotal,
                        'tuslah_embalase_total' => $transaction->tuslah_embalase_total,
                        'discount_total' => $transaction->discount_total,
                        'grand_total' => $transaction->grand_total,
                        'payment_method' => $transaction->payment_method->value,
                        'status' => $transaction->status->value,
                        'created_at' => $transaction->created_at->toIso8601String(),
                        'items' => $transaction->items()->with(['product', 'batch', 'productUnit'])->get()->map(fn($item) => [
                            'id' => $item->id,
                            'product_name' => $item->product?->name ?? 'Obat',
                            'batch_number' => $item->batch?->batch_number ?? 'BATCH',
                            'qty_input' => $item->qty_input,
                            'qty_base_deducted' => $item->qty_base_deducted,
                            'unit_name' => $item->productUnit?->unit_name ?? $item->product?->base_unit_name ?? 'Tablet',
                            'unit_price' => $item->unit_price,
                            'hpp_base_snapshot' => $item->hpp_base_snapshot,
                            'subtotal' => $item->subtotal,
                        ])->toArray(),
                    ],
                    'status' => 'pending',
                ]);
            } catch (\Throwable) {
                // Outbox failure should not roll back cashier sale
            }

            return $transaction->load(['items.product', 'items.batch', 'cashier']);
        });
    }
}
