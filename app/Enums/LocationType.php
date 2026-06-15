<?php

namespace App\Enums;

enum LocationType: string
{
    case Warehouse = 'warehouse';
    case Truck = 'truck';
    case JobSite = 'jobsite';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse',
            self::Truck => 'Truck',
            self::JobSite => 'Job Site',
        };
    }

    /**
     * Trucks are "mini-warehouses" assigned to a single technician.
     */
    public function holdsStock(): bool
    {
        return $this === self::Warehouse || $this === self::Truck;
    }
}
