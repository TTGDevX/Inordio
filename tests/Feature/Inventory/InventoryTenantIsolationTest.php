<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\SerializedAsset;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data leakage between tenants is the worst possible bug in this product
 * (PROJECT-BRIEF.md §3). Every inventory model carries tenant_id via
 * BelongsToTenant; these tests prove the global scope holds for each one.
 */
class InventoryTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * Run a callback with the given tenant initialized, then revert.
     */
    private function asTenant(Tenant $tenant, callable $callback): mixed
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    public function test_each_inventory_model_is_scoped_to_its_tenant(): void
    {
        $created = $this->asTenant($this->tenantA, function () {
            $category = Category::factory()->create();
            $supplier = Supplier::factory()->create();
            $location = Location::factory()->warehouse()->create();
            $item = InventoryItem::factory()->create([
                'category_id' => $category->id,
                'supplier_id' => $supplier->id,
            ]);
            $stock = StockLevel::factory()->create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
            ]);
            $asset = SerializedAsset::factory()->create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
            ]);

            return compact('category', 'supplier', 'location', 'item', 'stock', 'asset');
        });

        $this->asTenant($this->tenantB, function () use ($created) {
            $this->assertNull(Category::find($created['category']->id));
            $this->assertNull(Supplier::find($created['supplier']->id));
            $this->assertNull(Location::find($created['location']->id));
            $this->assertNull(InventoryItem::find($created['item']->id));
            $this->assertNull(StockLevel::find($created['stock']->id));
            $this->assertNull(SerializedAsset::find($created['asset']->id));

            $this->assertSame(0, InventoryItem::count());
            $this->assertSame(0, Location::count());
        });

        // Tenant A still sees its own rows.
        $this->asTenant($this->tenantA, function () {
            $this->assertSame(1, InventoryItem::count());
            $this->assertSame(1, SerializedAsset::count());
        });
    }

    public function test_models_created_under_tenancy_inherit_tenant_id(): void
    {
        $item = $this->asTenant($this->tenantA, fn () => InventoryItem::factory()->create());

        $this->assertSame($this->tenantA->id, $item->tenant_id);
    }

    public function test_internal_sku_uniqueness_is_per_tenant(): void
    {
        // The same internal SKU may exist in two different tenants.
        $a = $this->asTenant($this->tenantA, fn () => InventoryItem::factory()->create(['internal_sku' => 'TTG-SHARED-001']));
        $b = $this->asTenant($this->tenantB, fn () => InventoryItem::factory()->create(['internal_sku' => 'TTG-SHARED-001']));

        $this->assertSame('TTG-SHARED-001', $a->internal_sku);
        $this->assertSame('TTG-SHARED-001', $b->internal_sku);
        $this->assertNotSame($a->tenant_id, $b->tenant_id);
    }
}
