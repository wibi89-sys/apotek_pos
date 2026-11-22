<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case COMPLETED = 'completed';
    case VOIDED = 'voided';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Selesai',
            self::VOIDED => 'Dibatalkan (Void)',
            self::RETURNED => 'Diretur',
        };
    }
}
