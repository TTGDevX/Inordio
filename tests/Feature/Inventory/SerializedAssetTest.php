<?php

namespace Tests\Feature\Inventory;

use App\Models\Location;
use App\Models\SerializedAsset;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The LEGO model (PROJECT-BRIEF.md §5): arbitrary-depth nesting, with the
 * effective location inherited from the topmost parent.
 */
class SerializedAssetTest extends TestCase
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

    public function test_nested_asset_inherits_location_from_its_root(): void
    {
        $site = Location::factory()->jobSite()->create();

        // Rack R-001 -> Server SRV-014 -> Hard Drive WD-44521 (three levels deep).
        $rack = SerializedAsset::factory()->create(['location_id' => $site->id]);
        $server = SerializedAsset::factory()->create(['parent_id' => $rack->id]);
        $drive = SerializedAsset::factory()->create(['parent_id' => $server->id]);

        $this->assertNull($drive->location_id, 'Nested assets do not store their own location.');
        $this->assertSame($site->id, $drive->effectiveLocationId());
        $this->assertSame($site->id, $server->effectiveLocationId());
    }

    public function test_moving_the_root_moves_everything_inside_it(): void
    {
        $siteA = Location::factory()->jobSite()->create();
        $siteB = Location::factory()->jobSite()->create();

        $rack = SerializedAsset::factory()->create(['location_id' => $siteA->id]);
        $drive = SerializedAsset::factory()->create(['parent_id' => $rack->id]);

        // Relocate the rack; the drive follows automatically.
        $rack->update(['location_id' => $siteB->id]);

        $this->assertSame($siteB->id, $drive->fresh()->effectiveLocationId());
    }

    public function test_descendants_walks_the_whole_tree(): void
    {
        $rack = SerializedAsset::factory()->create();
        $server = SerializedAsset::factory()->create(['parent_id' => $rack->id]);
        $driveOne = SerializedAsset::factory()->create(['parent_id' => $server->id]);
        $driveTwo = SerializedAsset::factory()->create(['parent_id' => $server->id]);

        $ids = $rack->descendants()->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$server->id, $driveOne->id, $driveTwo->id])->sort()->values()->all(),
            $ids
        );
    }

    public function test_serial_numbers_are_unique_within_a_tenant(): void
    {
        SerializedAsset::factory()->create(['serial_number' => 'SV-8812']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SerializedAsset::factory()->create(['serial_number' => 'SV-8812']);
    }
}
