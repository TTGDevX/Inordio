<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaxReportTest extends TestCase
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

    private function ontarioInvoiceWorth(float $amount): Invoice
    {
        $customer = Customer::factory()->create(['province' => 'ON', 'tax_exempt' => false]);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => $amount, 'position' => 0]);

        return Invoice::fromJob($job);
    }

    public function test_report_sums_hst_collected_on_issued_invoices(): void
    {
        $this->ontarioInvoiceWorth(100)->markSent(); // 13% HST on 100 = $13.00

        Volt::test('reports.index')
            ->assertOk()
            ->assertSee('Tax collected')
            ->assertSee('HST')
            ->assertSee('$13.00')
            ->assertSee('$100.00'); // taxable sales
    }

    public function test_draft_invoices_are_excluded(): void
    {
        $this->ontarioInvoiceWorth(100); // stays Draft — not issued to the customer

        Volt::test('reports.index')
            ->assertOk()
            ->assertSee('No tax collected in this period');
    }

    public function test_date_range_filters_out_of_period_invoices(): void
    {
        $this->ontarioInvoiceWorth(100)->markSent(); // issued today

        Volt::test('reports.index')
            ->set('from', now()->subYear()->startOfYear()->toDateString())
            ->set('to', now()->subYear()->endOfYear()->toDateString())
            ->assertSee('No tax collected in this period');
    }

    public function test_viewer_cannot_open_reports(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('reports.index')->assertForbidden();
    }
}
