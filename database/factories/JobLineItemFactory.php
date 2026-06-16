<?php

namespace Database\Factories;

use App\Models\Job;
use App\Models\JobLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLineItem>
 */
class JobLineItemFactory extends Factory
{
    protected $model = JobLineItem::class;

    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'inventory_item_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_price' => fake()->randomFloat(2, 5, 500),
            'position' => 0,
        ];
    }
}
