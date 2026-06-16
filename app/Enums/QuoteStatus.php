<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Tailwind badge classes for this status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700',
            self::Sent => 'bg-blue-50 text-blue-700',
            self::Approved => 'bg-green-50 text-green-700',
            self::Declined => 'bg-red-50 text-red-700',
        };
    }
}
