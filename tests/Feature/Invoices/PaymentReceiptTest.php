<?php

namespace Tests\Feature\Invoices;

use App\Mail\PaymentReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
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

    private function invoice(?string $email): Invoice
    {
        $customer = Customer::factory()->taxExempt()->create(['email' => $email]);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        $invoice = Invoice::fromJob($job);
        $invoice->markSent();

        return $invoice;
    }

    public function test_recording_a_payment_emails_a_receipt_when_opted_in(): void
    {
        Mail::fake();
        $invoice = $this->invoice('client@acme.test');

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->set('payAmount', '40')
            ->set('emailReceipt', true)
            ->call('recordPayment')
            ->assertHasNoErrors();

        Mail::assertSent(PaymentReceiptMail::class, fn ($m) => $m->hasTo('client@acme.test'));
    }

    public function test_no_receipt_when_opted_out(): void
    {
        Mail::fake();
        $invoice = $this->invoice('client@acme.test');

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->set('payAmount', '40')
            ->set('emailReceipt', false)
            ->call('recordPayment')
            ->assertHasNoErrors();

        Mail::assertNotSent(PaymentReceiptMail::class);
    }

    public function test_no_receipt_when_customer_has_no_email(): void
    {
        Mail::fake();
        $invoice = $this->invoice(null);

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->set('payAmount', '40')
            ->set('emailReceipt', true)
            ->call('recordPayment')
            ->assertHasNoErrors();

        Mail::assertNotSent(PaymentReceiptMail::class);
    }

    public function test_receipt_subject_uses_the_template(): void
    {
        $invoice = $this->invoice('client@acme.test');
        $payment = $invoice->recordPayment(40, \App\Enums\PaymentMethod::Cash);

        $mailable = new PaymentReceiptMail($invoice, $payment, \App\Models\CompanySetting::current());
        $this->assertSame('Receipt for invoice '.$invoice->number, $mailable->envelope()->subject);
    }
}
