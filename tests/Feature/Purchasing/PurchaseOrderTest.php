<?php

namespace Tests\Feature\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Supplier $supplier;
    private InventoryItem $item;
    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->supplier = Supplier::factory()->create();
        $this->item = InventoryItem::factory()->create(['average_cost' => 0]);
        $this->warehouse = Location::factory()->warehouse()->create();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_office_can_create_a_purchase_order(): void
    {
        Volt::test('purchasing.form')
            ->set('supplier_id', $this->supplier->id)
            ->set('lines', [
                ['inventory_item_id' => $this->item->id, 'description' => 'Copper Pipe', 'quantity' => '10', 'unit_cost' => '4.00'],
            ])
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $po = PurchaseOrder::with('lines')->latest('id')->first();

        $this->assertSame($this->supplier->id, $po->supplier_id);
        $this->assertCount(1, $po->lines);
        $this->assertSame('PO-'.str_pad((string) $po->id, 5, '0', STR_PAD_LEFT), $po->number);
    }

    public function test_receiving_a_po_adds_stock_and_rolls_average_cost(): void
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id, 'inventory_item_id' => $this->item->id,
            'description' => 'Copper Pipe', 'quantity' => 10, 'unit_cost' => 4.00, 'position' => 0,
        ]);
        $po->markOrdered();

        Volt::test('purchasing.show', ['poId' => $po->id])
            ->set('receiveLocationId', $this->warehouse->id)
            ->call('receive')->assertHasNoErrors();

        $level = StockLevel::firstWhere(['inventory_item_id' => $this->item->id, 'location_id' => $this->warehouse->id]);
        $this->assertEqualsWithDelta(10, (float) $level->quantity, 0.001);
        $this->assertEqualsWithDelta(4.00, (float) $this->item->fresh()->average_cost, 0.001);
        $this->assertSame(PurchaseOrderStatus::Received, $po->fresh()->status);
    }

    public function test_viewer_cannot_open_purchasing(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Viewer)->create(['tenant_id' => $this->tenant->id]));

        $this->get(route('purchasing.index'))->assertForbidden();
    }
}
