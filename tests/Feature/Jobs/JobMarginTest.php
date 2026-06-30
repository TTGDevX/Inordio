<?php

namespace Tests\Feature\Jobs;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Location;
use App\Models\PickList;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobMarginTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_margin_is_revenue_minus_average_cost_of_parts_used(): void
    {
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $tenant->id]));

        $stock = app(StockManager::class);
        $item = InventoryItem::factory()->create();
        $warehouse = Location::factory()->warehouse()->create();
        $truck = Location::factory()->truck()->create();

        // Receive 10 @ $4.00 -> average cost 4.00.
        $stock->receive($item, $warehouse, 10, null, null, null, 4.00);

        // Job sells 5 of the item at $20 each -> revenue 100.
        $customer = Customer::factory()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $item->id,
            'description' => 'Part', 'quantity' => 5, 'unit_price' => 20, 'position' => 0,
        ]);

        // Generate pick list, pick the 5 onto the truck.
        $pickList = PickList::generateFrom($job);
        $line = $pickList->items->first();
        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $truck->id)
            ->set('sources', [$line->id => $warehouse->id])
            ->call('pick', $line->id);

        // Complete the job -> consumes the 5 off the truck at average cost.
        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('complete')
            ->assertHasNoErrors();

        $job->refresh();

        $this->assertEqualsWithDelta(100.0, $job->subtotal(), 0.001);
        $this->assertEqualsWithDelta(20.0, $job->costOfGoods(), 0.001);  // 5 × $4.00
        $this->assertEqualsWithDelta(80.0, $job->margin(), 0.001);       // 100 − 20

        $usage = StockMovement::where('job_id', $job->id)
            ->where('type', StockMovementType::Usage)->first();
        $this->assertNotNull($usage);
        $this->assertEqualsWithDelta(4.0, (float) $usage->unit_cost, 0.001);

        tenancy()->end();
    }
}
