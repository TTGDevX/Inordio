<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => InvoiceStatus::Draft,
            'province' => 'ON',
            'tax_exempt' => false,
            'tax_total' => 0,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(15)->toDateString(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Sent, 'sent_at' => now()]);
    }
}
