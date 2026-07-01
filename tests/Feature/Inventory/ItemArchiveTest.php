<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ItemArchiveTest extends TestCase
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

    public function test_archiving_hides_an_item_from_the_default_catalogue(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Discontinued Widget']);

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->call('toggleArchive')
            ->assertHasNoErrors();

        $this->assertFalse($item->fresh()->is_active);

        Volt::test('inventory.index')->assertDontSee('Discontinued Widget');
        Volt::test('inventory.index')->set('archived', true)->assertSee('Discontinued Widget');
    }

    public function test_restore_brings_an_item_back(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Reinstated Part', 'is_active' => false]);

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->call('toggleArchive')
            ->assertHasNoErrors();

        $this->assertTrue($item->fresh()->is_active);
        Volt::test('inventory.index')->assertSee('Reinstated Part');
    }

    public function test_archived_items_are_not_offered_in_the_quote_builder(): void
    {
        InventoryItem::factory()->create(['name' => 'Active Cable']);
        InventoryItem::factory()->create(['name' => 'Archived Cable', 'is_active' => false]);

        Volt::test('quotes.form')
            ->assertSee('Active Cable')
            ->assertDontSee('Archived Cable');
    }

    public function test_viewer_cannot_archive_an_item(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $item = InventoryItem::factory()->create();

        Volt::test('inventory.show', ['itemId' => $item->id])
            ->call('toggleArchive')
            ->assertForbidden();

        $this->assertTrue($item->fresh()->is_active);
    }
}
