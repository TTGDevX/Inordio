<?php

namespace Tests\Feature\Print;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAppearanceTest extends TestCase
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

    private function invoice(): Invoice
    {
        $customer = Customer::factory()->taxExempt()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        return Invoice::fromJob($job);
    }

    public function test_terms_and_tax_number_appear_when_configured(): void
    {
        CompanySetting::current()->update([
            'tax_number' => '12345 RT0001',
            'show_tax_number' => true,
            'document_terms' => 'Warranty: one year on parts and labour.',
        ]);
        $invoice = $this->invoice();

        $this->get(route('invoices.print', $invoice->id))
            ->assertOk()
            ->assertSee('12345 RT0001')
            ->assertSee('Warranty: one year on parts and labour.');
    }

    public function test_tax_number_can_be_hidden(): void
    {
        CompanySetting::current()->update(['tax_number' => '12345 RT0001', 'show_tax_number' => false]);
        $invoice = $this->invoice();

        $this->get(route('invoices.print', $invoice->id))
            ->assertOk()
            ->assertDontSee('12345 RT0001');
    }
}
