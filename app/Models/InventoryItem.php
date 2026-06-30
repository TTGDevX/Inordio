<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'category_id',
    'supplier_id',
    'name',
    'description',
    'internal_sku',
    'vendor_sku',
    'barcode',
    'unit_of_measure',
    'cost',
    'price',
    'is_serialized',
    'photo_path',
])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'is_serialized' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function serializedAssets(): HasMany
    {
        return $this->hasMany(SerializedAsset::class);
    }

    /**
     * Wholesaler offerings (each with its own cost + vendor SKU).
     */
    public function supplierOfferings(): HasMany
    {
        return $this->hasMany(ItemSupplier::class);
    }

    /**
     * Set the item's cost from its preferred supplier offering (falling back to
     * the first). Keeps valuation/margin/pricing in sync with the chosen source.
     */
    public function applyPreferredCost(): void
    {
        $preferred = $this->supplierOfferings()->where('is_preferred', true)->first()
            ?? $this->supplierOfferings()->orderBy('id')->first();

        if ($preferred) {
            $this->update(['cost' => $preferred->cost]);
        }
    }

    /**
     * Total on-hand quantity across every location for this tenant.
     */
    public function totalQuantity(): float
    {
        return (float) $this->stockLevels()->sum('quantity');
    }
}
