<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Job;
use App\Models\Location;
use App\Models\Quote;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);
        $this->userA = User::factory()->office()->create(['tenant_id' => $this->tenantA->id]);

        $this->asTenant($this->tenantA, function () {
            $customer = Customer::factory()->create(['name' => 'Acme']);
            Quote::factory()->sent()->create(['customer_id' => $customer->id]);
            Job::factory()->create(['customer_id' => $customer->id, 'title' => 'Fix the sink', 'status' => JobStatus::Scheduled]);

            $invoice = Invoice::factory()->sent()->create(['customer_id' => $customer->id]);
            InvoiceLineItem::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

            $item = InventoryItem::factory()->create(['name' => 'Widget Gizmo']);
            $location = Location::factory()->warehouse()->create();
            StockLevel::factory()->create([
                'inventory_item_id' => $item->id, 'location_id' => $location->id,
                'quantity' => 1, 'min_quantity' => 5,
            ]);
        });

        $this->asTenant($this->tenantB, function () {
            $customer = Customer::factory()->create();
            Job::factory()->create(['customer_id' => $customer->id, 'title' => 'Bravo tenant job', 'status' => JobStatus::Scheduled]);
        });

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function asTenant(Tenant $tenant, callable $callback): void
    {
        tenancy()->initialize($tenant);

        try {
            $callback();
        } finally {
            tenancy()->end();
        }
    }

    public function test_dashboard_shows_this_tenants_operational_overview(): void
    {
        $this->actingAs($this->userA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fix the sink')      // upcoming jobs
            ->assertSee('Widget Gizmo')      // low stock
            ->assertDontSee('Bravo tenant job'); // tenant B's data stays out
    }
}
