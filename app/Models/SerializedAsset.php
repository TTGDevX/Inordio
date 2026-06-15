<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Database\Factories\SerializedAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A serialized, individually-tracked unit (the LEGO model — PROJECT-BRIEF.md §5).
 * Assets nest to arbitrary depth via parent_id; the effective physical location
 * is inherited from the topmost ancestor.
 */
#[Fillable([
    'inventory_item_id',
    'parent_id',
    'serial_number',
    'location_id',
    'status',
    'notes',
])]
class SerializedAsset extends Model
{
    /** @use HasFactory<SerializedAssetFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ownLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /**
     * Walk up to the topmost ancestor. Used to resolve inherited location.
     */
    public function root(): self
    {
        $node = $this;

        while ($node->parent_id !== null) {
            $node = $node->parent;
        }

        return $node;
    }

    /**
     * The effective location: a nested asset lives wherever its root lives.
     * Moving the rack moves everything inside it (§5).
     */
    public function effectiveLocationId(): ?int
    {
        return $this->root()->location_id;
    }

    /**
     * Recursively collect every descendant (walk down the tree).
     *
     * @return \Illuminate\Support\Collection<int, SerializedAsset>
     */
    public function descendants(): \Illuminate\Support\Collection
    {
        return $this->children->flatMap(
            fn (self $child) => collect([$child])->merge($child->descendants())
        );
    }
}
