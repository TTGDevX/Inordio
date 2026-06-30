<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Province;
use App\Services\TaxCalculator;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'customer_id', 'job_id', 'number', 'status', 'province', 'tax_exempt',
    'tax_total', 'tax_breakdown', 'issued_at', 'due_at', 'notes',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;
    use \App\Models\Concerns\Auditable;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'province' => Province::class,
            'tax_exempt' => 'boolean',
            'tax_total' => 'decimal:2',
            'tax_breakdown' => 'array',
            'issued_at' => 'date',
            'due_at' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice) {
            if (! $invoice->number) {
                $invoice->forceFill([
                    'number' => 'INV-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subtotal(): float
    {
        return \App\Support\Money::sum($this->lines->map(fn (InvoiceLineItem $line) => $line->lineTotal()));
    }

    public function total(): float
    {
        return \App\Support\Money::sum([$this->subtotal(), (float) $this->tax_total]);
    }

    public function amountPaid(): float
    {
        return \App\Support\Money::round($this->payments()->sum('amount'));
    }

    public function balance(): float
    {
        return \App\Support\Money::round($this->total() - $this->amountPaid());
    }

    /**
     * Build an invoice from a completed job: copy lines, snapshot the customer's
     * province and compute tax at today's rates (frozen onto the invoice).
     */
    public static function fromJob(Job $job): self
    {
        $customer = $job->customer;

        $invoice = static::create([
            'customer_id' => $job->customer_id,
            'job_id' => $job->id,
            'status' => InvoiceStatus::Draft,
            'province' => $customer->province?->value,
            'tax_exempt' => $customer->tax_exempt,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(15)->toDateString(),
        ]);

        foreach ($job->lines as $line) {
            $invoice->lines()->create([
                'inventory_item_id' => $line->inventory_item_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'position' => $line->position,
            ]);
        }

        $invoice->load('lines');
        $tax = app(TaxCalculator::class)->calculate($customer->province, $invoice->subtotal(), $customer->tax_exempt);

        $invoice->forceFill([
            'tax_total' => $tax['total'],
            'tax_breakdown' => $tax['lines'],
        ])->save();

        return $invoice;
    }

    public function markSent(): void
    {
        $this->status = InvoiceStatus::Sent;
        $this->sent_at = now();
        $this->save();
    }

    public function voidInvoice(): void
    {
        $this->status = InvoiceStatus::Void;
        $this->save();
    }

    public function recordPayment(float $amount, PaymentMethod $method, ?string $reference = null, ?string $note = null): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'paid_at' => now()->toDateString(),
            'note' => $note,
        ]);

        if ($this->balance() <= 0.0 && $this->status !== InvoiceStatus::Void) {
            $this->status = InvoiceStatus::Paid;
            $this->paid_at = now();
            $this->save();
        }

        return $payment;
    }
}
