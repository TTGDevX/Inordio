<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'inventory_item_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 20),
            'unit_cost' => fake()->randomFloat(2, 1, 100),
            'received_quantity' => 0,
            'position' => 0,
        ];
    }
}
