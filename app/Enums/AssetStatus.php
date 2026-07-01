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

    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock => 'bg-green-100 text-green-800',
            self::Deployed => 'bg-blue-100 text-blue-800',
            self::Retired => 'bg-gray-100 text-gray-600',
        };
    }
}
