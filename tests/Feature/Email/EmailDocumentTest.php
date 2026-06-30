<?php

namespace Tests\Feature\Email;

use App\Mail\InvoiceMail;
use App\Mail\QuoteMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailDocumentTest extends TestCase
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

    private function invoiceFor(Customer $customer): Invoice
    {
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        return Invoice::fromJob($job);
    }

    public function test_invoice_is_emailed_to_the_customer(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'client@acme.test']);
        $invoice = $this->invoiceFor($customer);

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->call('emailToCustomer')
            ->assertHasNoErrors();

        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('client@acme.test'));
    }

    public function test_quote_is_emailed_to_the_customer(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'client@acme.test']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        QuoteLineItem::factory()->create(['quote_id' => $quote->id, 'quantity' => 1, 'unit_price' => 50, 'position' => 0]);

        Volt::test('quotes.show', ['quoteId' => $quote->id])
            ->call('emailToCustomer')
            ->assertHasNoErrors();

        Mail::assertSent(QuoteMail::class, fn ($mail) => $mail->hasTo('client@acme.test'));
    }

    public function test_nothing_is_sent_when_the_customer_has_no_email(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => null]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        QuoteLineItem::factory()->create(['quote_id' => $quote->id, 'quantity' => 1, 'unit_price' => 50, 'position' => 0]);

        Volt::test('quotes.show', ['quoteId' => $quote->id])
            ->call('emailToCustomer')
            ->assertHasErrors('email');

        Mail::assertNothingSent();
    }
}
