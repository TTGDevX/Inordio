<?php

namespace App\Models;

use Database\Factories\JobLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['job_id', 'inventory_item_id', 'description', 'quantity', 'unit_price', 'position'])]
class JobLineItem extends Model
{
    /** @use HasFactory<JobLineItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function lineTotal(): float
    {
        return \App\Support\Money::round((float) $this->quantity * (float) $this->unit_price);
    }
}
