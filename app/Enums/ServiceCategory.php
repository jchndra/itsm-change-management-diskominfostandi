<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case APPLICATION = 'application';
    case INFRASTRUCTURE = 'infrastructure';
    case NETWORK = 'network';
    case SECURITY = 'security';
    case DATABASE = 'database';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::APPLICATION => 'Aplikasi',
            self::INFRASTRUCTURE => 'Infrastruktur',
            self::NETWORK => 'Jaringan',
            self::SECURITY => 'Keamanan',
            self::DATABASE => 'Basis Data',
            self::OTHER => 'Lainnya',
        };
    }
}
