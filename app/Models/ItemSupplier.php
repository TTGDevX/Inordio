<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A wholesaler's offering for an inventory item: their cost + vendor SKU.
 * An item can have many of these; one is marked preferred (drives item cost).
 */
#[Fillable(['inventory_item_id', 'supplier_id', 'vendor_sku', 'cost', 'is_preferred'])]
class ItemSupplier extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'is_preferred' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
