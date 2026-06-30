<?php

namespace App\Models;

use Database\Factories\QuoteLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['quote_id', 'inventory_item_id', 'description', 'quantity', 'unit_price', 'position'])]
class QuoteLineItem extends Model
{
    /** @use HasFactory<QuoteLineItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
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
