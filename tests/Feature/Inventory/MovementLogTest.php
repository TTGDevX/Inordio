<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MovementLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function seedMovement(): InventoryItem
    {
        $stock = app(StockManager::class);
        $item = InventoryItem::factory()->create(['name' => 'Copper Pipe']);
        $warehouse = Location::factory()->warehouse()->create();
        $stock->receive($item, $warehouse, 10, null, 'Initial stock', null, 4.00);

        return $item;
    }

    public function test_log_lists_recorded_movements(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->seedMovement();

        Volt::test('movements.index')
            ->assertOk()
            ->assertSee('Copper Pipe')
            ->assertSee('Receipt');
    }

    public function test_type_filter_narrows_the_log(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->seedMovement();

        // Only a receipt exists — filtering to usage shows nothing.
        Volt::test('movements.index')
            ->set('type', 'usage')
            ->assertOk()
            ->assertSee('No stock movements');
    }

    public function test_viewer_cannot_open_the_movement_log(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('movements.index')->assertForbidden();
    }

    public function test_movements_do_not_leak_across_tenants(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->seedMovement();
        $this->assertGreaterThan(0, StockMovement::count());
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, StockMovement::count());
    }
}
