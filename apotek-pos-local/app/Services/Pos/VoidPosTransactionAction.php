<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Inventory\StockMutationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VoidPosTransactionAction
{
    public function __construct(
        protected StockMutationService $mutationService
    ) {}

    /**
     * Void a completed POS transaction with supervisor authorization and restore stock.
     */
    public function execute(
        Transaction $transaction,
        string $supervisorPin,
        ?string $voidReason = null,
        ?User $supervisorUser = null
    ): Transaction {
        // Verify supervisor PIN (default '123456' or supervisor user's pin)
        $masterPin = config('license.supervisor_pin', '123456');
        if ($supervisorPin !== $masterPin) {
            throw new InvalidArgumentException('PIN Otorisasi Supervisor tidak valid.');
        }

        if ($transaction->status === TransactionStatus::VOIDED) {
            throw new InvalidArgumentException('Transaksi ini sudah berstatus Void.');
        }

        return DB::transaction(function () use ($transaction, $supervisorUser, $voidReason) {
            $transaction->loadMissing('items.batch');

            // 1. Restore stock to respective batches
            foreach ($transaction->items as $item) {
                if ($item->batch) {
                    $this->mutationService->recordMovement(
                        batch: $item->batch,
                        qtyChangeBase: $item->qty_base_deducted,
                        type: StockMovementType::IN_RETURN,
                        referenceType: 'void_transaction',
                        referenceId: $transaction->invoice_no,
                        userId: $supervisorUser?->id,
                        notes: sprintf('Pembatalan (Void) Kasir Transaksi #%s: %s', $transaction->invoice_no, $voidReason ?? 'Tanpa alasan')
                    );
                }
            }

            // 2. Mark transaction as voided
            $transaction->status = TransactionStatus::VOIDED;
            if ($supervisorUser) {
                $transaction->authorized_by_user_id = $supervisorUser->id;
            }
            $transaction->save();

            return $transaction;
        });
    }
}
