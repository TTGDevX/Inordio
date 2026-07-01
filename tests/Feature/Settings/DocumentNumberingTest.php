<?php

namespace Tests\Feature\Settings;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_numbers_are_a_per_tenant_sequence(): void
    {
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);

        $customer = Customer::factory()->create();
        $first = Invoice::factory()->create(['customer_id' => $customer->id]);
        $second = Invoice::factory()->create(['customer_id' => $customer->id]);

        $this->assertSame('INV-00001', $first->number);
        $this->assertSame('INV-00002', $second->number);
        $this->assertSame(3, CompanySetting::current()->invoice_next_number);

        tenancy()->end();
    }

    public function test_custom_prefix_and_starting_number_are_applied(): void
    {
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);

        CompanySetting::current()->update(['invoice_prefix' => '2026-', 'invoice_next_number' => 100]);

        $invoice = Invoice::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $this->assertSame('2026-00100', $invoice->number);
        $this->assertSame(101, CompanySetting::current()->invoice_next_number);

        tenancy()->end();
    }

    public function test_quotes_have_their_own_counter(): void
    {
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);

        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        // Separate sequences — creating a quote doesn't advance invoices.
        $this->assertSame('Q-00001', $quote->number);
        $this->assertSame('INV-00001', $invoice->number);

        tenancy()->end();
    }

    public function test_each_tenant_starts_its_own_sequence(): void
    {
        $tenantA = Tenant::create(['name' => 'A']);
        tenancy()->initialize($tenantA);
        $a = Invoice::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $this->assertSame('INV-00001', $a->number);
        tenancy()->end();

        $tenantB = Tenant::create(['name' => 'B']);
        tenancy()->initialize($tenantB);
        $b = Invoice::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $this->assertSame('INV-00001', $b->number);
        tenancy()->end();
    }
}
