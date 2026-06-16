<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['customer_id', 'number', 'status', 'valid_until', 'notes'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Assign a human-friendly number once the row has an id.
        static::created(function (Quote $quote) {
            if (! $quote->number) {
                $quote->forceFill([
                    'number' => 'Q-'.str_pad((string) $quote->id, 5, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('position');
    }

    /**
     * The job this quote was converted into, if any (one per quote).
     */
    public function job(): HasOne
    {
        return $this->hasOne(Job::class);
    }

    /**
     * Pre-tax total. Tax is applied at invoicing (brief §5/§7, Phase 5).
     */
    public function subtotal(): float
    {
        return (float) $this->lines->sum(fn (QuoteLineItem $line) => $line->lineTotal());
    }

    public function isDraft(): bool
    {
        return $this->status === QuoteStatus::Draft;
    }

    // Lifecycle timestamps are set directly (not mass-assigned) since they're
    // system-controlled and intentionally absent from $fillable.
    public function markSent(): void
    {
        $this->status = QuoteStatus::Sent;
        $this->sent_at = now();
        $this->save();
    }

    public function approve(): void
    {
        $this->status = QuoteStatus::Approved;
        $this->approved_at = now();
        $this->save();
    }

    public function decline(): void
    {
        $this->status = QuoteStatus::Declined;
        $this->declined_at = now();
        $this->save();
    }
}
