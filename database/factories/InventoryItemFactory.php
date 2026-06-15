<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 500);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'internal_sku' => 'TTG-'.strtoupper(fake()->unique()->bothify('???-####')),
            'vendor_sku' => fake()->optional()->bothify('V#######'),
            'barcode' => fake()->optional()->ean13(),
            'unit_of_measure' => fake()->randomElement(['each', 'box', 'ft', 'm']),
            'cost' => $cost,
            'price' => round($cost * fake()->randomFloat(2, 1.2, 2.5), 2),
            'is_serialized' => false,
        ];
    }

    public function serialized(): static
    {
        return $this->state(fn () => ['is_serialized' => true]);
    }
}
