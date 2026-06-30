<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['supplier_id', 'number', 'status', 'notes'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (PurchaseOrder $po) {
            if (! $po->number) {
                $po->forceFill(['number' => 'PO-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('position');
    }

    public function total(): float
    {
        return \App\Support\Money::sum($this->lines->map(fn (PurchaseOrderItem $l) => $l->lineTotal()));
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function markOrdered(): void
    {
        $this->status = PurchaseOrderStatus::Ordered;
        $this->ordered_at = now();
        $this->save();
    }

    public function markReceived(): void
    {
        $this->status = PurchaseOrderStatus::Received;
        $this->received_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = PurchaseOrderStatus::Cancelled;
        $this->save();
    }
}
