<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function customer(): Customer
    {
        return Customer::factory()->create();
    }

    public function test_a_scheduled_job_appears_on_the_board(): void
    {
        Job::factory()->create([
            'customer_id' => $this->customer()->id,
            'title' => 'Furnace tune-up',
            'scheduled_at' => now()->addDay(),
        ]);

        Volt::test('jobs.schedule')
            ->assertOk()
            ->assertSee('Furnace tune-up');
    }

    public function test_unscheduled_jobs_are_flagged_for_scheduling(): void
    {
        Job::factory()->create([
            'customer_id' => $this->customer()->id,
            'title' => 'Unbooked callout',
            'scheduled_at' => null,
        ]);

        Volt::test('jobs.schedule')
            ->assertSee('Needs scheduling')
            ->assertSee('Unbooked callout');
    }

    public function test_completed_jobs_are_excluded(): void
    {
        Job::factory()->done()->create([
            'customer_id' => $this->customer()->id,
            'title' => 'Finished work',
        ]);

        Volt::test('jobs.schedule')->assertDontSee('Finished work');
    }

    public function test_technician_filter_limits_the_board(): void
    {
        $techA = User::factory()->technician()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tech A']);
        $techB = User::factory()->technician()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tech B']);

        Job::factory()->create(['customer_id' => $this->customer()->id, 'title' => 'Job for A', 'assigned_user_id' => $techA->id, 'scheduled_at' => now()->addDay()]);
        Job::factory()->create(['customer_id' => $this->customer()->id, 'title' => 'Job for B', 'assigned_user_id' => $techB->id, 'scheduled_at' => now()->addDay()]);

        Volt::test('jobs.schedule')
            ->set('tech', (string) $techA->id)
            ->assertSee('Job for A')
            ->assertDontSee('Job for B');
    }
}
