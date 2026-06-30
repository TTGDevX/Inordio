<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ItemSupplierTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private InventoryItem $item;
    private Supplier $acme;
    private Supplier $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->item = InventoryItem::factory()->create(['cost' => 0]);
        $this->acme = Supplier::factory()->create(['name' => 'Acme']);
        $this->beta = Supplier::factory()->create(['name' => 'Beta']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_two_suppliers_can_back_one_item_with_different_costs(): void
    {
        Volt::test('inventory.show', ['itemId' => $this->item->id])
            ->set('offerSupplierId', $this->acme->id)->set('offerCost', '4.10')->call('addOffering')->assertHasNoErrors()
            ->set('offerSupplierId', $this->beta->id)->set('offerCost', '4.35')->call('addOffering')->assertHasNoErrors();

        $this->item->refresh()->load('supplierOfferings');

        $this->assertCount(2, $this->item->supplierOfferings);
        // First one added is preferred, so item cost follows it.
        $this->assertEqualsWithDelta(4.10, (float) $this->item->cost, 0.001);
    }

    public function test_changing_the_preferred_supplier_updates_the_item_cost(): void
    {
        Volt::test('inventory.show', ['itemId' => $this->item->id])
            ->set('offerSupplierId', $this->acme->id)->set('offerCost', '4.10')->call('addOffering')
            ->set('offerSupplierId', $this->beta->id)->set('offerCost', '4.35')->call('addOffering');

        $betaOffering = $this->item->supplierOfferings()->where('supplier_id', $this->beta->id)->first();

        Volt::test('inventory.show', ['itemId' => $this->item->id])
            ->call('setPreferredOffering', $betaOffering->id)
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(4.35, (float) $this->item->fresh()->cost, 0.001);
    }
}
