<?php

namespace App\Enums;

enum AssetStatus: string
{
    case InStock = 'in_stock';
    case Deployed = 'deployed';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::Deployed => 'Deployed',
            self::Retired => 'Retired',
        };
    }
}
