<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DepositBillingTest extends TestCase
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

    /** A job worth $amount pre-tax for an Ontario (13% HST) customer. */
    private function jobWorth(float $amount): Job
    {
        $customer = Customer::factory()->create(['province' => 'ON', 'tax_exempt' => false]);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'quantity' => 1, 'unit_price' => $amount, 'position' => 0,
        ]);

        return $job->fresh();
    }

    public function test_a_deposit_bills_a_partial_amount_with_tax(): void
    {
        $job = $this->jobWorth(100);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billLabel', 'Deposit')
            ->set('billAmount', '25')
            ->call('bill')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invoice = Invoice::where('job_id', $job->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(25, $invoice->subtotal(), 0.001);
        $this->assertEqualsWithDelta(3.25, (float) $invoice->tax_total, 0.001); // 13% of 25
        $this->assertEqualsWithDelta(28.25, $invoice->total(), 0.001);
        $this->assertEqualsWithDelta(75, $job->fresh()->amountRemaining(), 0.001);
    }

    public function test_billing_more_than_the_remaining_balance_is_rejected(): void
    {
        $job = $this->jobWorth(100);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billAmount', '150')
            ->call('bill')
            ->assertHasErrors('billAmount');

        $this->assertSame(0, Invoice::where('job_id', $job->id)->count());
    }

    public function test_deposit_then_bill_remaining_covers_the_whole_job(): void
    {
        $job = $this->jobWorth(100);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billAmount', '25')->set('billLabel', 'Deposit')->call('bill');

        Volt::test('jobs.show', ['jobId' => $job->id])->call('billRemaining');

        $this->assertSame(2, Invoice::where('job_id', $job->id)->count());
        $this->assertEqualsWithDelta(0, $job->fresh()->amountRemaining(), 0.001);
    }

    public function test_full_invoice_is_not_created_once_staged_billing_started(): void
    {
        $job = $this->jobWorth(100);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billAmount', '25')->call('bill');

        // The simple "create full invoice" path must no-op once anything is billed.
        Volt::test('jobs.show', ['jobId' => $job->id])->call('createInvoice');

        $this->assertSame(1, Invoice::where('job_id', $job->id)->count());
    }

    public function test_voiding_an_invoice_frees_the_billed_amount(): void
    {
        $job = $this->jobWorth(100);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billAmount', '40')->call('bill');

        $this->assertEqualsWithDelta(60, $job->fresh()->amountRemaining(), 0.001);

        Invoice::where('job_id', $job->id)->first()->voidInvoice();

        // Void invoices don't count against the job — the full amount is billable again.
        $this->assertEqualsWithDelta(100, $job->fresh()->amountRemaining(), 0.001);
    }

    public function test_viewer_cannot_bill(): void
    {
        $job = $this->jobWorth(100);
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('billAmount', '25')
            ->call('bill')
            ->assertForbidden();

        $this->assertSame(0, Invoice::where('job_id', $job->id)->count());
    }
}
