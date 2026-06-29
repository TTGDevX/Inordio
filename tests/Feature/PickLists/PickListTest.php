<?php

namespace Tests\Feature\PickLists;

use App\Enums\PickListStatus;
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

class PickListTest extends TestCase
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

    private function jobWithCatalogueAndCustomLines(): Job
    {
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $this->item->id,
            'description' => 'Copper Pipe', 'quantity' => 10, 'position' => 0,
        ]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => null,
            'description' => 'Labour', 'quantity' => 2, 'position' => 1,
        ]);

        return $job;
    }

    private function qtyAt(Location $location): float
    {
        $level = StockLevel::firstWhere(['inventory_item_id' => $this->item->id, 'location_id' => $location->id]);

        return (float) ($level?->quantity ?? 0);
    }

    public function test_generate_includes_only_catalogue_lines(): void
    {
        $pickList = PickList::generateFrom($this->jobWithCatalogueAndCustomLines());

        // Labour line (no inventory item) is skipped.
        $this->assertCount(1, $pickList->items);
        $this->assertSame($this->item->id, $pickList->items->first()->inventory_item_id);
    }

    public function test_picking_transfers_stock_and_checks_the_line_off(): void
    {
        $pickList = PickList::generateFrom($this->jobWithCatalogueAndCustomLines());
        $line = $pickList->items->first();

        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $this->truck->id)
            ->set('sources', [$line->id => $this->warehouse->id])
            ->call('pick', $line->id)
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(90, $this->qtyAt($this->warehouse), 0.001);
        $this->assertEqualsWithDelta(10, $this->qtyAt($this->truck), 0.001);
        $this->assertTrue($line->fresh()->picked);
        // Only one stock line, so the list auto-completes.
        $this->assertSame(PickListStatus::Completed, $pickList->fresh()->status);
    }

    public function test_picking_more_than_on_hand_is_rejected(): void
    {
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $this->item->id,
            'description' => 'Copper Pipe', 'quantity' => 999, 'position' => 0,
        ]);
        $pickList = PickList::generateFrom($job);
        $line = $pickList->items->first();

        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $this->truck->id)
            ->set('sources', [$line->id => $this->warehouse->id])
            ->call('pick', $line->id)
            ->assertHasErrors('sources.'.$line->id);

        $this->assertFalse($line->fresh()->picked);
        $this->assertEqualsWithDelta(100, $this->qtyAt($this->warehouse), 0.001);
    }

    public function test_completing_the_job_consumes_picked_parts_off_the_truck(): void
    {
        $pickList = PickList::generateFrom($this->jobWithCatalogueAndCustomLines());
        $line = $pickList->items->first();

        // Pick the part onto the truck (warehouse 90, truck 10).
        Volt::test('picklists.show', ['pickListId' => $pickList->id])
            ->set('destinationId', $this->truck->id)
            ->set('sources', [$line->id => $this->warehouse->id])
            ->call('pick', $line->id);

        // Completing the job consumes the 10 off the truck.
        Volt::test('jobs.show', ['jobId' => $pickList->job_id])
            ->call('complete')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(0, $this->qtyAt($this->truck), 0.001);
    }
}
