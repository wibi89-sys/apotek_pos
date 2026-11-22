<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case IN_PURCHASE = 'in_purchase';
    case OUT_SALE = 'out_sale';
    case IN_RETURN = 'in_return';
    case OUT_RETURN = 'out_return';
    case ADJUSTMENT_OPNAME = 'adjustment_opname';
    case EXPIRED_WRITEOFF = 'expired_writeoff';

    public function label(): string
    {
        return match ($this) {
            self::IN_PURCHASE => 'Penerimaan Faktur Pembelian (PBF)',
            self::OUT_SALE => 'Penjualan Kasir (POS)',
            self::IN_RETURN => 'Retur Masuk Penjualan',
            self::OUT_RETURN => 'Retur Keluar ke PBF',
            self::ADJUSTMENT_OPNAME => 'Penyesuaian Stok Opname',
            self::EXPIRED_WRITEOFF => 'Pemusnahan Obat Kedaluwarsa',
        };
    }

    public function isAddition(): bool
    {
        return in_array($this, [self::IN_PURCHASE, self::IN_RETURN], true);
    }
}
