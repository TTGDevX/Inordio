<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Draft,
            'valid_until' => now()->addDays(30)->toDateString(),
            'notes' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Sent, 'sent_at' => now()]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Approved, 'approved_at' => now()]);
    }
}
