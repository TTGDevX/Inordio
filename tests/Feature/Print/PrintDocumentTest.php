<?php

namespace Tests\Feature\Print;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private Invoice $invoiceA;
    private Quote $quoteA;
    private Invoice $invoiceB;
    private Quote $quoteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        [$this->invoiceA, $this->quoteA] = $this->asTenant($this->tenantA, fn () => [$this->makeInvoice(), $this->makeQuote()]);
        [$this->invoiceB, $this->quoteB] = $this->asTenant($this->tenantB, fn () => [$this->makeInvoice(), $this->makeQuote()]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeInvoice(): Invoice
    {
        $customer = Customer::factory()->create(['province' => 'ON']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'description' => 'Labour', 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        return Invoice::fromJob($job);
    }

    private function makeQuote(): Quote
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        QuoteLineItem::factory()->create(['quote_id' => $quote->id, 'description' => 'Estimate line', 'quantity' => 1, 'unit_price' => 50, 'position' => 0]);

        return $quote;
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

    public function test_invoice_print_document_renders(): void
    {
        $this->actingAs($this->userA)
            ->get(route('invoices.print', $this->invoiceA->id))
            ->assertOk()
            ->assertSee($this->invoiceA->number)
            ->assertSee($this->invoiceA->customer->name)
            ->assertSee('Print / Save as PDF');
    }

    public function test_quote_print_document_renders(): void
    {
        $this->actingAs($this->userA)
            ->get(route('quotes.print', $this->quoteA->id))
            ->assertOk()
            ->assertSee($this->quoteA->number);
    }

    public function test_cannot_print_an_invoice_from_another_tenant(): void
    {
        $this->actingAs($this->userA)
            ->get(route('invoices.print', $this->invoiceB->id))
            ->assertNotFound();
    }

    public function test_cannot_print_a_quote_from_another_tenant(): void
    {
        $this->actingAs($this->userA)
            ->get(route('quotes.print', $this->quoteB->id))
            ->assertNotFound();
    }
}
