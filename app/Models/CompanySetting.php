<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'legal_name', 'address_line1', 'address_line2', 'city', 'province', 'postal_code',
    'phone', 'email', 'website', 'tax_number', 'payment_terms', 'invoice_footer', 'accent_color', 'logo_path',
])]
class CompanySetting extends Model
{
    use BelongsToTenant;

    /**
     * The current tenant's settings row (one per tenant), created on first use.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
