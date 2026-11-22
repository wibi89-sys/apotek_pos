<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case QRIS = 'qris';
    case TRANSFER = 'transfer';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai (Cash)',
            self::QRIS => 'QRIS (Gopay/OVO/Dana/BCA)',
            self::TRANSFER => 'Transfer Bank',
            self::DEBIT => 'Kartu Debit / EDC',
        };
    }
}
