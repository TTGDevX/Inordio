<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Sent => 'bg-blue-50 text-blue-700',
            self::Paid => 'bg-green-50 text-green-700',
            self::Void => 'bg-red-50 text-red-700',
        };
    }
}
