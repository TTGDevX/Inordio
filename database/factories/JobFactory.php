<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'quote_id' => null,
            'title' => fake()->sentence(3),
            'status' => JobStatus::Scheduled,
            'scheduled_at' => now()->addDays(2),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => JobStatus::InProgress, 'started_at' => now()]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => JobStatus::Done, 'completed_at' => now()]);
    }
}
