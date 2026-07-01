<?php

namespace Tests\Feature\Print;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The PDF *views* are DomPDF-friendly HTML and render without the package.
 * The actual PDF attachment (barryvdh/laravel-dompdf) is guarded by class_exists
 * in the mailables, so email sending works with or without it installed.
 */
class PdfDocumentTest extends TestCase
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

    public function test_invoice_pdf_view_renders_with_key_details(): void
    {
        $customer = Customer::factory()->taxExempt()->create(['name' => 'Acme Co']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'description' => 'Compressor', 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        $invoice = Invoice::fromJob($job);

        $html = view('pdf.invoice', ['invoice' => $invoice, 'co' => CompanySetting::current()])->render();

        $this->assertStringContainsString($invoice->number, $html);
        $this->assertStringContainsString('Acme Co', $html);
        $this->assertStringContainsString('Compressor', $html);
        $this->assertStringContainsString('100.00', $html);
    }

    public function test_quote_pdf_view_renders_with_key_details(): void
    {
        $customer = Customer::factory()->create(['name' => 'Beta Corp']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $html = view('pdf.quote', ['quote' => $quote, 'co' => CompanySetting::current()])->render();

        $this->assertStringContainsString($quote->number, $html);
        $this->assertStringContainsString('Beta Corp', $html);
    }
}
