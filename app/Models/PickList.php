<?php

namespace App\Models;

use App\Enums\PickListStatus;
use Database\Factories\PickListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['job_id', 'destination_location_id', 'status'])]
class PickList extends Model
{
    /** @use HasFactory<PickListFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PickListStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickListItem::class)->orderBy('position');
    }

    /**
     * Generate a pick list from a job's catalogue line items (parts that come
     * from stock). Custom/labour lines without an inventory item are skipped.
     */
    public static function generateFrom(Job $job): self
    {
        $pickList = static::create([
            'job_id' => $job->id,
            'status' => PickListStatus::Open,
        ]);

        $position = 0;
        foreach ($job->lines as $line) {
            if ($line->inventory_item_id === null) {
                continue;
            }

            $pickList->items()->create([
                'inventory_item_id' => $line->inventory_item_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'position' => $position++,
            ]);
        }

        return $pickList;
    }

    public function isFullyPicked(): bool
    {
        return $this->items->isNotEmpty() && $this->items->every(fn (PickListItem $i) => $i->picked);
    }

    public function markCompleted(): void
    {
        $this->status = PickListStatus::Completed;
        $this->completed_at = now();
        $this->save();
    }
}
