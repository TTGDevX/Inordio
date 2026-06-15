<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->streetName().' Warehouse',
            'type' => LocationType::Warehouse,
            'is_active' => true,
        ];
    }

    public function warehouse(): static
    {
        return $this->state(fn () => ['type' => LocationType::Warehouse]);
    }

    public function truck(): static
    {
        return $this->state(fn () => [
            'type' => LocationType::Truck,
            'name' => 'Truck '.fake()->unique()->randomLetter(),
        ]);
    }

    public function jobSite(): static
    {
        return $this->state(fn () => [
            'type' => LocationType::JobSite,
            'name' => fake()->company().' Site',
        ]);
    }
}
