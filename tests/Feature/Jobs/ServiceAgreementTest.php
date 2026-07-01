<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\ServiceAgreement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ServiceAgreementTest extends TestCase
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

    private function agreement(array $overrides = []): ServiceAgreement
    {
        return ServiceAgreement::create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'title' => 'Quarterly HVAC service',
            'cadence' => 'quarterly',
            'next_run_at' => now()->toDateString(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_generating_a_job_copies_lines_and_advances_the_schedule(): void
    {
        $agreement = $this->agreement();
        $agreement->items()->create(['description' => 'Replace filter', 'quantity' => 1, 'unit_price' => 40, 'position' => 0]);
        $agreement->items()->create(['description' => 'System check', 'quantity' => 1, 'unit_price' => 90, 'position' => 1]);

        $job = $agreement->generateDueJob();

        $this->assertSame($agreement->id, $job->service_agreement_id);
        $this->assertSame(JobStatus::Scheduled, $job->status);
        $this->assertSame(now()->toDateString(), $job->scheduled_at->toDateString());
        $this->assertCount(2, $job->lines);

        $agreement->refresh();
        $this->assertSame(now()->toDateString(), $agreement->last_run_at->toDateString());
        $this->assertSame(now()->addMonthsNoOverflow(3)->toDateString(), $agreement->next_run_at->toDateString());
    }

    public function test_the_command_generates_only_due_active_agreements(): void
    {
        $due = $this->agreement(['title' => 'Due now']);
        $future = $this->agreement(['title' => 'Not yet', 'next_run_at' => now()->addMonth()->toDateString()]);
        $paused = $this->agreement(['title' => 'Paused', 'is_active' => false]);

        tenancy()->end();
        $this->artisan('agreements:run')->assertExitCode(0);
        tenancy()->initialize($this->tenant);

        $this->assertSame(1, Job::where('service_agreement_id', $due->id)->count());
        $this->assertSame(0, Job::where('service_agreement_id', $future->id)->count());
        $this->assertSame(0, Job::where('service_agreement_id', $paused->id)->count());
    }

    public function test_office_can_create_an_agreement_with_lines(): void
    {
        $customer = Customer::factory()->create();

        Volt::test('agreements.form')
            ->set('customer_id', $customer->id)
            ->set('title', 'Annual boiler service')
            ->set('cadence', 'annual')
            ->set('next_run_at', now()->toDateString())
            ->set('items', [['inventory_item_id' => null, 'description' => 'Boiler inspection', 'quantity' => '1', 'unit_price' => '150']])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('agreements.index'));

        $agreement = ServiceAgreement::where('title', 'Annual boiler service')->first();
        $this->assertNotNull($agreement);
        $this->assertSame(1, $agreement->items()->count());
    }

    public function test_generate_now_creates_a_job_from_the_index(): void
    {
        $agreement = $this->agreement();
        $agreement->items()->create(['description' => 'Visit', 'quantity' => 1, 'unit_price' => 0, 'position' => 0]);

        Volt::test('agreements.index')
            ->call('generateNow', $agreement->id)
            ->assertRedirect();

        $this->assertSame(1, Job::where('service_agreement_id', $agreement->id)->count());
    }

    public function test_viewer_cannot_manage_agreements(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('agreements.index')->assertForbidden();
        Volt::test('agreements.form')->assertForbidden();
    }

    public function test_agreements_are_tenant_isolated(): void
    {
        $this->agreement();
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, ServiceAgreement::count());
    }
}
