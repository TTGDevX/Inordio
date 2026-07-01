<?php

namespace Tests\Feature\Inventory;

use App\Enums\AssetStatus;
use App\Models\AssetEvent;
use App\Models\Location;
use App\Models\SerializedAsset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SerializedAssetUiTest extends TestCase
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

    public function test_registering_an_asset_records_a_created_event(): void
    {
        Volt::test('assets.form')
            ->set('serial_number', 'SN-9001')
            ->set('status', 'in_stock')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $asset = SerializedAsset::where('serial_number', 'SN-9001')->first();
        $this->assertNotNull($asset);
        $this->assertDatabaseHas('asset_events', [
            'serialized_asset_id' => $asset->id,
            'type' => 'created',
        ]);
    }

    public function test_assembling_nests_the_part_and_inherits_location(): void
    {
        $site = Location::factory()->jobSite()->create();
        $rack = SerializedAsset::factory()->create(['location_id' => $site->id]);
        $server = SerializedAsset::factory()->create();

        Volt::test('assets.show', ['assetId' => $rack->id])
            ->set('assembleId', $server->id)
            ->call('assemble')
            ->assertHasNoErrors();

        $server->refresh();
        $this->assertSame($rack->id, $server->parent_id);
        $this->assertNull($server->location_id);
        $this->assertSame($site->id, $server->effectiveLocationId());
        $this->assertSame(AssetStatus::Deployed, $server->status);
        $this->assertDatabaseHas('asset_events', [
            'serialized_asset_id' => $server->id,
            'parent_asset_id' => $rack->id,
            'type' => 'assembled',
        ]);
    }

    public function test_cannot_assemble_an_asset_into_its_own_part(): void
    {
        $rack = SerializedAsset::factory()->create();
        $server = SerializedAsset::factory()->create(['parent_id' => $rack->id]);

        // Try to assemble the rack into the server (a cycle) — guarded.
        Volt::test('assets.show', ['assetId' => $server->id])
            ->set('assembleId', $rack->id)
            ->call('assemble')
            ->assertHasErrors('assembleId');

        $this->assertNull($rack->fresh()->parent_id);
    }

    public function test_detaching_floats_the_part_up_with_a_home_location(): void
    {
        $site = Location::factory()->jobSite()->create();
        $rack = SerializedAsset::factory()->create(['location_id' => $site->id]);
        $server = SerializedAsset::factory()->create(['parent_id' => $rack->id]);

        Volt::test('assets.show', ['assetId' => $rack->id])
            ->call('detach', $server->id)
            ->assertHasNoErrors();

        $server->refresh();
        $this->assertNull($server->parent_id);
        $this->assertSame($site->id, $server->location_id); // kept the inherited home
        $this->assertSame(AssetStatus::InStock, $server->status);
        $this->assertDatabaseHas('asset_events', [
            'serialized_asset_id' => $server->id,
            'type' => 'disassembled',
        ]);
    }

    public function test_moving_the_root_relocates_the_whole_tree(): void
    {
        $siteA = Location::factory()->jobSite()->create();
        $siteB = Location::factory()->warehouse()->create();
        $rack = SerializedAsset::factory()->create(['location_id' => $siteA->id]);
        $drive = SerializedAsset::factory()->create(['parent_id' => $rack->id]);

        Volt::test('assets.show', ['assetId' => $rack->id])
            ->set('moveLocationId', $siteB->id)
            ->call('move')
            ->assertHasNoErrors();

        $this->assertSame($siteB->id, $rack->fresh()->location_id);
        $this->assertSame($siteB->id, $drive->fresh()->effectiveLocationId());
    }

    public function test_retiring_sets_status_and_logs(): void
    {
        $asset = SerializedAsset::factory()->create();

        Volt::test('assets.show', ['assetId' => $asset->id])
            ->call('retire')
            ->assertHasNoErrors();

        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->assertDatabaseHas('asset_events', ['serialized_asset_id' => $asset->id, 'type' => 'retired']);
    }

    public function test_index_lists_only_top_level_units(): void
    {
        $rack = SerializedAsset::factory()->create(['serial_number' => 'ROOT-1']);
        SerializedAsset::factory()->create(['serial_number' => 'PART-1', 'parent_id' => $rack->id]);

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('ROOT-1')
            ->assertDontSee('PART-1');
    }

    public function test_viewer_cannot_register_or_modify_assets(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $asset = SerializedAsset::factory()->create();
        $other = SerializedAsset::factory()->create();

        $this->get(route('assets.create'))->assertForbidden();

        Volt::test('assets.show', ['assetId' => $asset->id])
            ->set('assembleId', $other->id)
            ->call('assemble')
            ->assertForbidden();

        Volt::test('assets.show', ['assetId' => $asset->id])
            ->call('retire')
            ->assertForbidden();
    }

    public function test_assets_are_tenant_isolated(): void
    {
        $mine = SerializedAsset::factory()->create();
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $other->id]));

        $this->assertSame(0, SerializedAsset::count());
        $this->get(route('assets.show', $mine->id))->assertNotFound();
    }
}
