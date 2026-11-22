<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case APOTEKER = 'apoteker';
    case KASIR = 'kasir';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Pemilik Sarana Apotek (PSA / Owner)',
            self::APOTEKER => 'Apoteker Penanggung Jawab (APA / Supervisor)',
            self::KASIR => 'Petugas Kasir / Tenaga Teknis Kefarmasian',
        };
    }

    public function canAuthorizeSupervisorActions(): bool
    {
        return in_array($this, [self::OWNER, self::APOTEKER], true);
    }
}
