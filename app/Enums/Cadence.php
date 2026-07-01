<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum Cadence: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semiannual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnual => 'Every 6 months',
            self::Annual => 'Annually',
        };
    }

    /**
     * Advance a date by one cadence period (no month-overflow, so the 31st
     * doesn't skip a short month).
     */
    public function advance(Carbon $date): Carbon
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3),
            self::SemiAnnual => $date->copy()->addMonthsNoOverflow(6),
            self::Annual => $date->copy()->addYearNoOverflow(),
        };
    }
}
