<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'inventory_item_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_price' => fake()->randomFloat(2, 5, 500),
            'position' => 0,
        ];
    }
}
