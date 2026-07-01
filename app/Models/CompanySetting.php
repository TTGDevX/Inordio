<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'legal_name', 'address_line1', 'address_line2', 'city', 'province', 'postal_code',
    'phone', 'email', 'website', 'tax_number', 'payment_terms', 'invoice_footer', 'accent_color', 'logo_path',
    'invoice_prefix', 'invoice_next_number', 'quote_prefix', 'quote_next_number',
])]
class CompanySetting extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'invoice_next_number' => 'integer',
            'quote_next_number' => 'integer',
        ];
    }

    /**
     * The current tenant's settings row (one per tenant), created on first use.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    /**
     * Format the *next* number for a document type without consuming it.
     * $type is 'invoice' or 'quote'.
     */
    public function formatNumber(string $type): string
    {
        $prefix = $this->{$type.'_prefix'} ?: strtoupper($type[0]).'-';
        $n = (int) ($this->{$type.'_next_number'} ?: 1);

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Allocate (format + consume) the next number for a document type. The
     * counter is per-tenant, so each tenant gets its own gap-free sequence.
     */
    public static function allocateNumber(string $type): string
    {
        $settings = static::current();
        $number = $settings->formatNumber($type);
        $settings->increment($type.'_next_number');

        return $number;
    }
}
