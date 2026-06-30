<?php

namespace App\Models;

use App\Enums\Province;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'name',
    'contact_name',
    'email',
    'phone',
    'address_line1',
    'address_line2',
    'city',
    'province',
    'postal_code',
    'country',
    'tax_exempt',
    'tax_number',
    'notes',
    'is_active',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'province' => Province::class,
            'tax_exempt' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
