<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Ordered => 'bg-blue-50 text-blue-700',
            self::Received => 'bg-green-50 text-green-700',
            self::Cancelled => 'bg-red-50 text-red-700',
        };
    }
}
