<?php

namespace Database\Factories;

use App\Enums\AssetEventType;
use App\Models\AssetEvent;
use App\Models\SerializedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetEvent>
 */
class AssetEventFactory extends Factory
{
    protected $model = AssetEvent::class;

    public function definition(): array
    {
        return [
            'serialized_asset_id' => SerializedAsset::factory(),
            'type' => AssetEventType::Created,
        ];
    }
}
