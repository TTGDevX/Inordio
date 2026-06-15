<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'location_id' => Location::factory(),
            'quantity' => fake()->randomFloat(2, 0, 100),
            'min_quantity' => null,
        ];
    }
}
