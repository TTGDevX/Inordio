<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\JobTimeEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobTimeEntryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function job(): Job
    {
        return Job::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    }

    public function test_technician_can_log_time_and_totals_compute(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('timeHours', '2.5')
            ->set('timeRate', '100')
            ->set('timeDescription', 'Diagnostics')
            ->call('addTimeEntry')
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertEqualsWithDelta(2.5, $job->loggedHours(), 0.001);
        $this->assertEqualsWithDelta(250.0, $job->labourTotal(), 0.001);
    }

    public function test_logged_labour_flows_onto_the_invoice(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $customer = Customer::factory()->taxExempt()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        $job->timeEntries()->create(['hours' => 3, 'rate' => 90, 'description' => 'Install', 'performed_on' => now()]);

        $invoice = Invoice::fromJob($job);

        // Parts 100 + labour 270 = 370 subtotal.
        $this->assertEqualsWithDelta(370.0, $invoice->subtotal(), 0.001);
        $this->assertTrue($invoice->lines->contains(fn ($l) => $l->description === 'Install'));
    }

    public function test_invoice_without_labour_is_unchanged(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $customer = Customer::factory()->taxExempt()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        $invoice = Invoice::fromJob($job);

        $this->assertCount(1, $invoice->lines);
        $this->assertEqualsWithDelta(100.0, $invoice->subtotal(), 0.001);
    }

    public function test_viewer_cannot_log_time(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('timeHours', '1')
            ->set('timeRate', '50')
            ->call('addTimeEntry')
            ->assertForbidden();

        $this->assertSame(0, JobTimeEntry::where('job_id', $job->id)->count());
    }

    public function test_time_entries_are_tenant_isolated(): void
    {
        $job = $this->job();
        $job->timeEntries()->create(['hours' => 1, 'rate' => 50, 'performed_on' => now()]);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, JobTimeEntry::count());
    }
}
