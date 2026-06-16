<?php

namespace Tests\Feature\Jobs;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Job $jobA;

    private Job $jobB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->jobA = $this->asTenant($this->tenantA, function () {
            $customer = Customer::factory()->create();

            return Job::factory()->create(['customer_id' => $customer->id]);
        });

        $this->jobB = $this->asTenant($this->tenantB, function () {
            $customer = Customer::factory()->create();

            return Job::factory()->create(['customer_id' => $customer->id]);
        });

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function asTenant(Tenant $tenant, callable $callback): mixed
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    private function userWith(UserRole $role): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_index_lists_only_the_current_tenants_jobs(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee($this->jobA->number)
            ->assertDontSee($this->jobB->number);
    }

    public function test_user_cannot_view_a_job_from_another_tenant(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('jobs.show', $this->jobB->id))
            ->assertNotFound();
    }

    public function test_viewer_cannot_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('jobs.create'))
            ->assertForbidden();
    }

    public function test_technician_cannot_open_the_create_page(): void
    {
        // Technicians work jobs but don't manage them.
        $this->actingAs($this->userWith(UserRole::Technician))
            ->get(route('jobs.create'))
            ->assertForbidden();
    }

    public function test_office_can_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('jobs.create'))
            ->assertOk();
    }

    public function test_new_job_button_is_hidden_from_a_viewer_but_shown_to_office(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertDontSee('New job');

        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('New job');
    }
}
