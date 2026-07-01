<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'legal_name', 'address_line1', 'address_line2', 'city', 'province', 'postal_code',
    'phone', 'email', 'website', 'tax_number', 'payment_terms', 'invoice_footer', 'accent_color', 'logo_path',
    'invoice_prefix', 'invoice_next_number', 'quote_prefix', 'quote_next_number',
    'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name',
    'default_labour_rate',
])]
class CompanySetting extends Model
{
    use BelongsToTenant;

    /**
     * In-memory defaults so a freshly firstOrCreate()'d row carries the real
     * prefixes/counters (DB-level defaults aren't reflected on the new instance).
     */
    protected $attributes = [
        'invoice_prefix' => 'INV-',
        'invoice_next_number' => 1,
        'quote_prefix' => 'Q-',
        'quote_next_number' => 1,
    ];

    protected function casts(): array
    {
        return [
            'invoice_next_number' => 'integer',
            'quote_next_number' => 'integer',
            'mail_port' => 'integer',
            'mail_password' => 'encrypted', // never stored in plaintext
        ];
    }

    /**
     * True when this tenant has configured its own outgoing SMTP server.
     */
    public function hasCustomMailer(): bool
    {
        return filled($this->mail_host);
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
