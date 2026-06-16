<?php

namespace App\Enums;

enum JobStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-blue-50 text-blue-700',
            self::InProgress => 'bg-amber-50 text-amber-700',
            self::Done => 'bg-green-50 text-green-700',
            self::Cancelled => 'bg-gray-100 text-gray-600',
        };
    }
}
