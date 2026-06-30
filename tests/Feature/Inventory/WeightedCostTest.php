<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightedCostTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private StockManager $stock;
    private InventoryItem $item;
    private Location $warehouse;
    private Supplier $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->stock = app(StockManager::class);
        $this->item = InventoryItem::factory()->create(['cost' => 0, 'average_cost' => 0]);
        $this->warehouse = Location::factory()->warehouse()->create();
        $this->acme = Supplier::factory()->create();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_receipts_roll_into_a_weighted_average_cost(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 10, null, null, $this->acme, 4.00);
        $this->assertEqualsWithDelta(4.00, (float) $this->item->fresh()->average_cost, 0.001);

        // 10 @ 4.00 + 10 @ 5.00 = 90 / 20 = 4.50
        $this->stock->receive($this->item, $this->warehouse, 10, null, null, $this->acme, 5.00);
        $this->assertEqualsWithDelta(4.50, (float) $this->item->fresh()->average_cost, 0.001);
    }

    public function test_receipt_records_the_supplier_and_unit_cost(): void
    {
        $this->stock->receive($this->item, $this->warehouse, 5, null, null, $this->acme, 4.25);

        $movement = StockMovement::where('inventory_item_id', $this->item->id)->latest('id')->first();

        $this->assertSame($this->acme->id, $movement->supplier_id);
        $this->assertEqualsWithDelta(4.25, (float) $movement->unit_cost, 0.001);
    }
}
