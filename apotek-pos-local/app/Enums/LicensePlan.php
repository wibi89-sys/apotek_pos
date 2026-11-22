<?php

declare(strict_types=1);

namespace App\Enums;

enum LicensePlan: string
{
    case STANDARD_LOCAL = 'STANDARD_LOCAL';
    case PRO_CLOUD_SYNC = 'PRO_CLOUD_SYNC';
    case ENTERPRISE = 'ENTERPRISE';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD_LOCAL => 'Standard Local (Offline Lifetime)',
            self::PRO_CLOUD_SYNC => 'Pro Cloud Sync (Subscription)',
            self::ENTERPRISE => 'Enterprise (Multi-Cabang & API)',
        };
    }
}
