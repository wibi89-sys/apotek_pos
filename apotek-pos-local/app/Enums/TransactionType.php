<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case OTC = 'otc'; // Over The Counter (Obat Bebas Tanpa Resep)
    case RESEP = 'resep'; // Resep Dokter & Obat Racikan

    public function label(): string
    {
        return match ($this) {
            self::OTC => 'Non-Resep (OTC)',
            self::RESEP => 'Resep Dokter',
        };
    }
}
