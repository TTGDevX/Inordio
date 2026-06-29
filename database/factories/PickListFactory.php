<?php

namespace Database\Factories;

use App\Enums\PickListStatus;
use App\Models\Job;
use App\Models\PickList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickList>
 */
class PickListFactory extends Factory
{
    protected $model = PickList::class;

    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'status' => PickListStatus::Open,
        ];
    }
}
