<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Office = 'office';
    case Technician = 'technician';
    case Viewer = 'viewer';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 5,
            self::Admin => 4,
            self::Office => 3,
            self::Technician => 2,
            self::Viewer => 1,
        };
    }

    public function isAtLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
