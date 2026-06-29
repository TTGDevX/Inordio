<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\PickList;
use App\Models\PickListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickListItem>
 */
class PickListItemFactory extends Factory
{
    protected $model = PickListItem::class;

    public function definition(): array
    {
        return [
            'pick_list_id' => PickList::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'picked' => false,
            'position' => 0,
        ];
    }
}
