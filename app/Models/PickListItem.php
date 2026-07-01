<?php

namespace App\Models;

use Database\Factories\PickListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['pick_list_id', 'inventory_item_id', 'description', 'quantity', 'picked', 'picked_quantity', 'short_quantity', 'from_location_id', 'position'])]
class PickListItem extends Model
{
    /** @use HasFactory<PickListItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'picked_quantity' => 'decimal:2',
            'short_quantity' => 'decimal:2',
            'picked' => 'boolean',
            'picked_at' => 'datetime',
        ];
    }

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Mark this line picked from a source location. $pickedQty defaults to the
     * full need; any shortfall is recorded as back-order (short_quantity).
     */
    public function markPicked(int $fromLocationId, ?float $pickedQty = null): void
    {
        $picked = $pickedQty ?? (float) $this->quantity;

        $this->picked = true;
        $this->from_location_id = $fromLocationId;
        $this->picked_quantity = $picked;
        $this->short_quantity = max(0, (float) $this->quantity - $picked);
        $this->picked_at = now();
        $this->save();
    }

    /**
     * Resolve the line with nothing available — the whole quantity is
     * back-ordered and no stock moves.
     */
    public function markShort(): void
    {
        $this->picked = true;
        $this->from_location_id = null;
        $this->picked_quantity = 0;
        $this->short_quantity = (float) $this->quantity;
        $this->picked_at = now();
        $this->save();
    }

    public function isShort(): bool
    {
        return (float) $this->short_quantity > 0;
    }
}
