<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);

        tenancy()->initialize($this->tenant);

        // Inventory valuation: 5 units, cost 10 / price 25  ->  $50 cost, $125 retail.
        $item = InventoryItem::factory()->create(['cost' => 10, 'price' => 25]);
        $warehouse = Location::factory()->warehouse()->create();
        StockLevel::factory()->create([
            'inventory_item_id' => $item->id, 'location_id' => $warehouse->id, 'quantity' => 5,
        ]);

        // AR: a tax-exempt customer's invoice for $100, sent, 40 days overdue (31–60 bucket).
        $customer = Customer::factory()->taxExempt()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        $invoice = Invoice::fromJob($job);
        $invoice->markSent();
        $invoice->update(['due_at' => now()->subDays(40)->toDateString()]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function userWith(UserRole $role): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_reports_show_receivables_and_inventory_valuation(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('$100.00')   // outstanding AR
            ->assertSee('$50.00')    // inventory at cost
            ->assertSee('$125.00');  // inventory at retail
    }

    public function test_viewer_cannot_open_reports(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_office_can_open_reports(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('reports.index'))
            ->assertOk();
    }
}
