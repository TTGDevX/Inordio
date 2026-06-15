<?php

namespace App\Enums;

/**
 * Every change to on-hand quantity is recorded as a stock movement (the
 * ledger). from_location_id / to_location_id are interpreted per type:
 *
 *  - Receipt:    null      -> location   (inventory arriving from a supplier)
 *  - Transfer:   location  -> location   (warehouse -> truck pick, etc.)
 *  - Usage:      location  -> null       (consumed on a job)
 *  - Adjustment: either, signed quantity (counts, shrinkage, corrections)
 */
enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Transfer = 'transfer';
    case Usage = 'usage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
