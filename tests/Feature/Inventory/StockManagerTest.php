<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private StockManager $stock;

    private InventoryItem $item;

    private Location $warehouse;

    private Location $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->stock = app(StockManager::class);
        $this->item = InventoryItem::factory()->create();
        $this->warehouse = Location::factory()->warehouse()->create();
        $this->truck = Location::factory()->truck()->create();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function quantityAt(Location $location): float
    {
        $level = StockLevel::firstWhere([
            'inventory_item_id' => $this->item->id,
            'location_id' => $location->id,
        ]);

        return (float) ($level?->quantity ?? 0);
    }

    public function test_receiving_stock_increases_the_location_quantity(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 100);

        $this->assertEqualsWithDelta(100, $this->quantityAt($this->warehouse), 0.001);
    }

    public function test_full_pick_flow_moves_stock_warehouse_to_truck_to_job(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 100);
        $this->stock->transfer($this->item, $this->warehouse, $this->truck, 30);
        $this->stock->consume($this->item, $this->truck, 10);
        $this->stock->adjust($this->item, $this->warehouse, -5);

        $this->assertEqualsWithDelta(65, $this->quantityAt($this->warehouse), 0.001);
        $this->assertEqualsWithDelta(20, $this->quantityAt($this->truck), 0.001);

        // Four movements recorded, each scoped to the tenant.
        $this->assertSame(4, StockMovement::count());
        $this->assertSame(1, StockMovement::where('type', StockMovementType::Transfer)->count());
    }

    public function test_transfer_fails_when_source_lacks_enough_stock(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 10);

        try {
            $this->stock->transfer($this->item, $this->warehouse, $this->truck, 999);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException) {
            // expected
        }

        // Transaction rolled back: warehouse untouched, nothing landed on the truck,
        // and no movement was recorded beyond the original receipt.
        $this->assertEqualsWithDelta(10, $this->quantityAt($this->warehouse), 0.001);
        $this->assertEqualsWithDelta(0, $this->quantityAt($this->truck), 0.001);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_total_quantity_sums_across_locations(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 40);
        $this->stock->receive($this->item, $this->truck, 12);

        $this->assertEqualsWithDelta(52, $this->item->totalQuantity(), 0.001);
    }
}
