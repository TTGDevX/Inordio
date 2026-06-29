<?php

namespace App\Enums;

enum PickListStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-50 text-amber-700',
            self::Completed => 'bg-green-50 text-green-700',
        };
    }
}
