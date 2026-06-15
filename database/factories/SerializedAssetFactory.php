<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\InventoryItem;
use App\Models\SerializedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerializedAsset>
 */
class SerializedAssetFactory extends Factory
{
    protected $model = SerializedAsset::class;

    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory()->serialized(),
            'parent_id' => null,
            'serial_number' => strtoupper(fake()->unique()->bothify('SN-####-???')),
            'status' => AssetStatus::InStock,
        ];
    }
}
