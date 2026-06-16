<?php

namespace Database\Factories;

use App\Enums\Province;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->randomElement(Province::cases())->value,
            'postal_code' => fake()->bothify('?#? #?#'),
            'country' => 'CA',
            'tax_exempt' => false,
            'is_active' => true,
        ];
    }

    public function taxExempt(): static
    {
        return $this->state(fn () => [
            'tax_exempt' => true,
            'tax_number' => fake()->bothify('EXEMPT-#####'),
        ]);
    }
}
