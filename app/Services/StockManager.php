<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single entry point for changing on-hand inventory. Every method records
 * an immutable StockMovement (the ledger) and adjusts the affected StockLevel
 * rows inside one transaction, so the running totals can never drift from the
 * movement history. Tenant scoping is handled by BelongsToTenant on the models;
 * callers must have tenancy initialized.
 */
class StockManager
{
    /**
     * Inventory arriving from a supplier into a location (e.g. PO receipt).
     */
    public function receive(InventoryItem $item, Location $to, float $quantity, ?User $user = null, ?string $note = null): StockMovement
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($item, $to, $quantity, $user, $note) {
            $this->addToLocation($item, $to, $quantity);

            return $this->record($item, StockMovementType::Receipt, $quantity, null, $to, $user, $note);
        });
    }

    /**
     * Move stock from one location to another (the warehouse -> truck pick is a
     * transfer). Fails if the source doesn't hold enough.
     */
    public function transfer(InventoryItem $item, Location $from, Location $to, float $quantity, ?User $user = null, ?string $note = null): StockMovement
    {
        $this->assertPositive($quantity);

        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Cannot transfer stock to the same location.');
        }

        return DB::transaction(function () use ($item, $from, $to, $quantity, $user, $note) {
            $this->removeFromLocation($item, $from, $quantity);
            $this->addToLocation($item, $to, $quantity);

            return $this->record($item, StockMovementType::Transfer, $quantity, $from, $to, $user, $note);
        });
    }

    /**
     * Consume stock at a location (used on a job, deducted from the truck).
     */
    public function consume(InventoryItem $item, Location $from, float $quantity, ?User $user = null, ?string $note = null): StockMovement
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($item, $from, $quantity, $user, $note) {
            $this->removeFromLocation($item, $from, $quantity);

            return $this->record($item, StockMovementType::Usage, $quantity, $from, null, $user, $note);
        });
    }

    /**
     * Correct on-hand at a location by a signed delta (counts, shrinkage).
     * The movement quantity is stored as the absolute value; the sign lives in
     * the level change.
     */
    public function adjust(InventoryItem $item, Location $location, float $delta, ?User $user = null, ?string $note = null): StockMovement
    {
        if ($delta === 0.0) {
            throw new InvalidArgumentException('Adjustment delta cannot be zero.');
        }

        return DB::transaction(function () use ($item, $location, $delta, $user, $note) {
            if ($delta > 0) {
                $this->addToLocation($item, $location, $delta);
                [$from, $to] = [null, $location];
            } else {
                $this->removeFromLocation($item, $location, abs($delta));
                [$from, $to] = [$location, null];
            }

            return $this->record($item, StockMovementType::Adjustment, abs($delta), $from, $to, $user, $note);
        });
    }

    private function addToLocation(InventoryItem $item, Location $location, float $quantity): void
    {
        $level = $this->levelFor($item, $location);
        $level->quantity = (float) $level->quantity + $quantity;
        $level->save();
    }

    private function removeFromLocation(InventoryItem $item, Location $location, float $quantity): void
    {
        $level = $this->levelFor($item, $location);

        if ((float) $level->quantity < $quantity) {
            throw new InsufficientStockException(
                "Location {$location->id} holds {$level->quantity} of item {$item->id}; cannot remove {$quantity}."
            );
        }

        $level->quantity = (float) $level->quantity - $quantity;
        $level->save();
    }

    private function levelFor(InventoryItem $item, Location $location): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['inventory_item_id' => $item->id, 'location_id' => $location->id],
            ['quantity' => 0],
        );
    }

    private function record(InventoryItem $item, StockMovementType $type, float $quantity, ?Location $from, ?Location $to, ?User $user, ?string $note): StockMovement
    {
        return StockMovement::create([
            'inventory_item_id' => $item->id,
            'from_location_id' => $from?->id,
            'to_location_id' => $to?->id,
            'type' => $type,
            'quantity' => $quantity,
            'user_id' => $user?->id,
            'note' => $note,
        ]);
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }
}
