<?php

namespace App\Enums;

enum ChecklistItemStatus: string
{
    case Pending = 'pending';
    case Pass = 'pass';
    case Fail = 'fail';
    case Na = 'na';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Pass => 'Pass',
            self::Fail => 'Fail',
            self::Na => 'N/A',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-600',
            self::Pass => 'bg-green-100 text-green-800',
            self::Fail => 'bg-red-100 text-red-800',
            self::Na => 'bg-gray-100 text-gray-500',
        };
    }
}
