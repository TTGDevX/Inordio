<?php

namespace Tests\Feature\PickLists;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Location;
use App\Models\PickList;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShortPickTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Customer $customer;
    private InventoryItem $item;
    private Location $warehouse;
    private Location $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->customer = Customer::factory()->create();
        $this->item = InventoryItem::factory()->create(['name' => 'Copper Pipe']);
        $this->warehouse = Location::factory()->warehouse()->create();
        $this->truck = Location::factory()->truck()->create();

        app(StockManager::class)->receive($this->item, $this->warehouse, 100);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function pickListForQty(float $qty): PickList
    {
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $this->item->id,
            'description' => 'Copper Pipe', 'quantity' => $qty, 'position' => 0,
        ]);

        return PickList::generateFrom($job);
    }

    private function qtyAt(Location $location): float
    {
        $level = StockLevel::firstWhere(['inventory_item_id' => $this->item->id, 'location_id' => $location->id]);

        return (float) ($level?->quantity ?? 0);
    }

    public function test_a_partial_pick_moves_only_what_was_picked_and_flags_the_shortfall(): void
    {
        $pickList = $this->pickListForQty(10);
        $line = $pickList->items->first();

        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $this->truck->id)
            ->set('sources', [$line->id => $this->warehouse->id])
            ->set('pickQty', [$line->id => 6])
            ->call('pick', $line->id)
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(94, $this->qtyAt($this->warehouse), 0.001);
        $this->assertEqualsWithDelta(6, $this->qtyAt($this->truck), 0.001);

        $line->refresh();
        $this->assertTrue($line->picked);
        $this->assertEqualsWithDelta(6, (float) $line->picked_quantity, 0.001);
        $this->assertEqualsWithDelta(4, (float) $line->short_quantity, 0.001);
        $this->assertTrue($pickList->fresh()->hasBackorders());
    }

    public function test_none_available_back_orders_the_whole_line_without_moving_stock(): void
    {
        $pickList = $this->pickListForQty(10);
        $line = $pickList->items->first();

        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->call('markShort', $line->id)
            ->assertHasNoErrors();

        $line->refresh();
        $this->assertTrue($line->picked);
        $this->assertEqualsWithDelta(0, (float) $line->picked_quantity, 0.001);
        $this->assertEqualsWithDelta(10, (float) $line->short_quantity, 0.001);
        // No stock moved.
        $this->assertEqualsWithDelta(100, $this->qtyAt($this->warehouse), 0.001);
        $this->assertEqualsWithDelta(0, $this->qtyAt($this->truck), 0.001);
    }

    public function test_completing_the_job_consumes_only_the_picked_quantity(): void
    {
        $pickList = $this->pickListForQty(10);
        $line = $pickList->items->first();

        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $this->truck->id)
            ->set('sources', [$line->id => $this->warehouse->id])
            ->set('pickQty', [$line->id => 6])
            ->call('pick', $line->id);

        // Truck holds the 6 that were picked.
        $this->assertEqualsWithDelta(6, $this->qtyAt($this->truck), 0.001);

        Volt::test('jobs.show', ['jobId' => $pickList->job_id])
            ->call('complete')
            ->assertHasNoErrors();

        // The 6 are consumed off the truck; nothing left over.
        $this->assertEqualsWithDelta(0, $this->qtyAt($this->truck), 0.001);
    }
}
