<?php

declare(strict_types=1);

namespace App\Enums;

enum DrugClass: string
{
    case BEBAS = 'bebas';
    case BEBAS_TERBATAS = 'bebas_terbatas';
    case KERAS = 'keras';
    case PSIKOTROPIKA = 'psikotropika';
    case NARKOTIKA = 'narkotika';
    case PREKURSOR = 'prekursor';
    case OOT = 'oot'; // Obat-Obat Tertentu
    case ALKES = 'alkes';

    public function label(): string
    {
        return match ($this) {
            self::BEBAS => 'Obat Bebas (Hijau)',
            self::BEBAS_TERBATAS => 'Obat Bebas Terbatas (Biru)',
            self::KERAS => 'Obat Keras (Merah / K)',
            self::PSIKOTROPIKA => 'Psikotropika',
            self::NARKOTIKA => 'Narkotika',
            self::PREKURSOR => 'Prekursor Farmasi',
            self::OOT => 'Obat-Obat Tertentu (OOT)',
            self::ALKES => 'Alat Kesehatan & Medis',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::BEBAS => 'bg-green-100 text-green-800 border-green-300',
            self::BEBAS_TERBATAS => 'bg-blue-100 text-blue-800 border-blue-300',
            self::KERAS => 'bg-red-100 text-red-800 border-red-300',
            self::PSIKOTROPIKA => 'bg-purple-100 text-purple-800 border-purple-300',
            self::NARKOTIKA => 'bg-rose-900 text-rose-100 border-rose-950',
            self::PREKURSOR => 'bg-amber-100 text-amber-800 border-amber-300',
            self::OOT => 'bg-orange-100 text-orange-800 border-orange-300',
            self::ALKES => 'bg-teal-100 text-teal-800 border-teal-300',
        };
    }
}
