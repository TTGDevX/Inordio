<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
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

    private function jobForOntarioCustomerWorth(float $unitPrice): Job
    {
        $customer = Customer::factory()->create(['province' => 'ON', 'tax_exempt' => false]);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => $unitPrice, 'position' => 0,
        ]);

        return $job;
    }

    public function test_invoice_from_job_copies_lines_and_snapshots_ontario_tax(): void
    {
        $invoice = Invoice::fromJob($this->jobForOntarioCustomerWorth(100));

        $this->assertCount(1, $invoice->lines);
        $this->assertSame('ON', $invoice->province->value);
        $this->assertEqualsWithDelta(13.0, (float) $invoice->tax_total, 0.001);
        $this->assertEqualsWithDelta(113.0, $invoice->total(), 0.001);
        $this->assertSame('INV-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT), $invoice->number);
    }

    public function test_tax_exempt_customer_invoice_has_no_tax(): void
    {
        $customer = Customer::factory()->taxExempt()->create(['province' => 'ON']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        $invoice = Invoice::fromJob($job);

        $this->assertEqualsWithDelta(0.0, (float) $invoice->tax_total, 0.001);
        $this->assertEqualsWithDelta(100.0, $invoice->total(), 0.001);
        $this->assertSame([], $invoice->tax_breakdown ?? []);
    }

    public function test_full_payment_marks_the_invoice_paid(): void
    {
        $invoice = Invoice::fromJob($this->jobForOntarioCustomerWorth(100)); // total 113

        $invoice->recordPayment(113.0, PaymentMethod::ETransfer);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertEqualsWithDelta(0.0, $invoice->fresh()->balance(), 0.001);
    }

    public function test_partial_payment_leaves_a_balance_and_stays_unpaid(): void
    {
        $invoice = Invoice::fromJob($this->jobForOntarioCustomerWorth(100)); // total 113

        $invoice->recordPayment(50.0, PaymentMethod::Cash);

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        $this->assertEqualsWithDelta(63.0, $invoice->fresh()->balance(), 0.001);
    }

    public function test_recording_a_payment_through_the_show_component(): void
    {
        $invoice = Invoice::fromJob($this->jobForOntarioCustomerWorth(100));

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->set('payAmount', '113')
            ->set('payMethod', 'cash')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_creating_an_invoice_from_a_job_is_idempotent(): void
    {
        $job = $this->jobForOntarioCustomerWorth(100);
        $job->update(['status' => \App\Enums\JobStatus::Done]);

        Volt::test('jobs.show', ['jobId' => $job->id])->call('createInvoice');
        Volt::test('jobs.show', ['jobId' => $job->id])->call('createInvoice');

        $this->assertSame(1, Invoice::where('job_id', $job->id)->count());
    }
}
