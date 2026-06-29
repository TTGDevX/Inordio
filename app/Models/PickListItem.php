<?php

namespace App\Models;

use Database\Factories\PickListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['pick_list_id', 'inventory_item_id', 'description', 'quantity', 'picked', 'from_location_id', 'position'])]
class PickListItem extends Model
{
    /** @use HasFactory<PickListItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
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
     * Mark this line picked from a source location (timestamp set directly).
     */
    public function markPicked(int $fromLocationId): void
    {
        $this->picked = true;
        $this->from_location_id = $fromLocationId;
        $this->picked_at = now();
        $this->save();
    }
}
