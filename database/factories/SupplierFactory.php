<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'account_number' => fake()->bothify('ACCT-####'),
        ];
    }
}
