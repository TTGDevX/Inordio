<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Exercises the write side of the inventory UI (locations, item create/edit,
 * and stock movements) through the Volt components, with tenancy initialized
 * the way the middleware would in a real request.
 */
class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_can_create_a_warehouse_location(): void
    {
        Volt::test('locations.index')
            ->set('name', 'Main Warehouse')
            ->set('type', 'warehouse')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Main Warehouse',
            'type' => 'warehouse',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_truck_keeps_its_assigned_technician_but_other_types_do_not(): void
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Volt::test('locations.index')
            ->set('name', 'Truck A')
            ->set('type', 'truck')
            ->set('assigned_user_id', $tech->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Truck A',
            'assigned_user_id' => $tech->id,
        ]);

        // Assigning a tech to a non-truck is silently dropped.
        Volt::test('locations.index')
            ->set('name', 'Overflow Warehouse')
            ->set('type', 'warehouse')
            ->set('assigned_user_id', $tech->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'name' => 'Overflow Warehouse',
            'assigned_user_id' => null,
        ]);
    }

    public function test_can_create_an_item_with_a_new_category(): void
    {
        Volt::test('inventory.form')
            ->set('name', 'Cat6 Cable')
            ->set('internal_sku', 'TTG-CAT6-001')
            ->set('cost', '50')
            ->set('price', '110')
            ->set('unit_of_measure', 'box')
            ->set('new_category', 'Cabling')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $item = InventoryItem::firstWhere('internal_sku', 'TTG-CAT6-001');

        $this->assertNotNull($item);
        $this->assertSame($this->tenant->id, $item->tenant_id);
        $this->assertSame('Cabling', $item->category->name);
        $this->assertDatabaseHas('categories', [
            'name' => 'Cabling',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_internal_sku_must_be_unique_within_the_tenant(): void
    {
        InventoryItem::factory()->create(['internal_sku' => 'TTG-DUP-001']);

        Volt::test('inventory.form')
            ->set('name', 'Duplicate')
            ->set('internal_sku', 'TTG-DUP-001')
            ->set('cost', '1')
            ->set('price', '2')
            ->call('save')
            ->assertHasErrors('internal_sku');
    }

    public function test_editing_an_item_prefills_and_updates(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Old name']);

        Volt::test('inventory.form', ['itemId' => $item->id])
            ->assertSet('name', 'Old name')
            ->set('name', 'New name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New name', $item->fresh()->name);
    }

    public function test_receiving_stock_through_the_item_page_updates_the_level(): void
    {
        $item = InventoryItem::factory()->create();
        $warehouse = Location::factory()->warehouse()->create();

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->set('action', 'receive')
            ->set('to_location_id', $warehouse->id)
            ->set('quantity', '25')
            ->call('applyAction')
            ->assertHasNoErrors();

        $level = StockLevel::firstWhere([
            'inventory_item_id' => $item->id,
            'location_id' => $warehouse->id,
        ]);

        $this->assertEqualsWithDelta(25, (float) $level->quantity, 0.001);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_transfer_in_ui_surfaces_insufficient_stock_without_recording(): void
    {
        $item = InventoryItem::factory()->create();
        $warehouse = Location::factory()->warehouse()->create();
        $truck = Location::factory()->truck()->create();

        app(StockManager::class)->receive($item, $warehouse, 5);

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->set('action', 'transfer')
            ->set('from_location_id', $warehouse->id)
            ->set('to_location_id', $truck->id)
            ->set('quantity', '99')
            ->call('applyAction')
            ->assertHasErrors('quantity');

        // Nothing landed on the truck and only the original receipt exists.
        $this->assertNull(StockLevel::firstWhere([
            'inventory_item_id' => $item->id,
            'location_id' => $truck->id,
        ]));
        $this->assertSame(1, StockMovement::count());
    }
}
