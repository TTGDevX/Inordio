<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function level(string $itemName, float $qty, ?float $min): StockLevel
    {
        return StockLevel::factory()->create([
            'inventory_item_id' => InventoryItem::factory()->create(['name' => $itemName])->id,
            'location_id' => Location::factory()->warehouse()->create()->id,
            'quantity' => $qty,
            'min_quantity' => $min,
        ]);
    }

    public function test_low_items_are_listed_and_healthy_ones_are_not(): void
    {
        $this->level('Low Widget', 1, 5);   // below min
        $this->level('Healthy Widget', 20, 5); // above min
        $this->level('No-Min Widget', 0, null); // no reorder point set

        Volt::test('inventory.reorder')
            ->assertOk()
            ->assertSee('Low Widget')
            ->assertDontSee('Healthy Widget')
            ->assertDontSee('No-Min Widget');
    }

    public function test_at_the_reorder_point_counts_as_low(): void
    {
        $this->level('Exactly At Min', 5, 5);

        Volt::test('inventory.reorder')->assertSee('Exactly At Min');
    }

    public function test_viewer_cannot_open_the_reorder_view(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('inventory.reorder')->assertForbidden();
    }
}
