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

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Guard against cycles: is $other this asset or somewhere in its subtree?
     */
    public function containsInSubtree(self $other): bool
    {
        if ($other->id === $this->id) {
            return true;
        }

        return $this->descendants()->contains(fn (self $d) => $d->id === $other->id);
    }

    /**
     * Write an immutable history entry (who/what/when) for this asset.
     */
    public function recordEvent(\App\Enums\AssetEventType $type, ?self $parent = null, ?int $locationId = null, ?string $note = null): void
    {
        $this->events()->create([
            'parent_asset_id' => $parent?->id,
            'type' => $type,
            'location_id' => $locationId,
            'user_id' => auth()->id(),
            'note' => $note,
        ]);
    }

    /**
     * Assemble this unit into $parent (it becomes nested and inherits $parent's
     * location). Refuses to create a cycle. Records an Assembled event.
     */
    public function attachTo(self $parent): void
    {
        if ($this->containsInSubtree($parent)) {
            throw new \InvalidArgumentException('Cannot assemble an asset into itself or one of its own parts.');
        }

        $this->update(['parent_id' => $parent->id, 'location_id' => null, 'status' => AssetStatus::Deployed]);
        $this->recordEvent(\App\Enums\AssetEventType::Assembled, $parent, null, 'Assembled into '.$parent->serial_number);
    }

    /**
     * Disassemble: detach from the parent and keep the inherited location as a
     * home so the freed unit still has a place. Records a Disassembled event.
     */
    public function detach(): void
    {
        if ($this->isRoot()) {
            return;
        }

        $oldParent = $this->parent;
        $home = $this->effectiveLocationId();
        $this->update(['parent_id' => null, 'location_id' => $home, 'status' => AssetStatus::InStock]);
        $this->recordEvent(\App\Enums\AssetEventType::Disassembled, $oldParent, $home, 'Detached from '.$oldParent?->serial_number);
    }

    /**
     * Move the asset to a location. Only roots carry a location, so this moves
     * the whole tree by relocating the root. Records a Moved event.
     */
    public function moveTo(int $locationId): void
    {
        $root = $this->root();
        $root->update(['location_id' => $locationId]);
        $root->recordEvent(\App\Enums\AssetEventType::Moved, null, $locationId, 'Moved');
    }

    public function retire(): void
    {
        $this->update(['status' => AssetStatus::Retired]);
        $this->recordEvent(\App\Enums\AssetEventType::Retired);
    }
}
