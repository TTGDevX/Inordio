<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\SerializedAsset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelTest extends TestCase
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

    public function test_item_label_sheet_encodes_the_sku_token(): void
    {
        InventoryItem::factory()->create(['name' => 'Copper Pipe', 'internal_sku' => 'TTG-CU-1']);

        $this->get(route('inventory.labels'))
            ->assertOk()
            ->assertSee('Copper Pipe')
            ->assertSee('INV:TTG-CU-1');
    }

    public function test_single_item_label_renders(): void
    {
        $item = InventoryItem::factory()->create(['internal_sku' => 'TTG-XYZ-9']);

        $this->get(route('inventory.label', $item->id))
            ->assertOk()
            ->assertSee('INV:TTG-XYZ-9');
    }

    public function test_asset_label_encodes_the_serial_token(): void
    {
        $asset = SerializedAsset::factory()->create(['serial_number' => 'SN-100']);

        $this->get(route('assets.label', $asset->id))
            ->assertOk()
            ->assertSee('AST:SN-100');
    }

    public function test_location_labels_encode_the_location_token(): void
    {
        $loc = Location::factory()->truck()->create(['name' => 'Truck 12']);

        $this->get(route('locations.labels'))
            ->assertOk()
            ->assertSee('Truck 12')
            ->assertSee('LOC:'.$loc->id);
    }

    public function test_viewer_cannot_print_labels(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        $this->get(route('inventory.labels'))->assertForbidden();
    }

    public function test_cannot_print_a_label_for_another_tenants_item(): void
    {
        $item = InventoryItem::factory()->create();
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $other->id]));

        $this->get(route('inventory.label', $item->id))->assertNotFound();
    }
}
