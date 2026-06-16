<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Role gates: move-stock (Technician+), manage-inventory / manage-locations
 * (Office+), Viewers read-only. Verified three ways — the Gate matrix directly,
 * HTTP guards on mutating pages, and the show/hide of controls in the UI.
 */
class InventoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->item = InventoryItem::factory()->create(['name' => 'Gate Test Item']);
        Location::factory()->warehouse()->create();

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function userWith(UserRole $role): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_gate_matrix_follows_the_role_hierarchy(): void
    {
        $viewer = $this->userWith(UserRole::Viewer);
        $technician = $this->userWith(UserRole::Technician);
        $office = $this->userWith(UserRole::Office);
        $owner = $this->userWith(UserRole::Owner);

        // move-stock: Technician and up.
        $this->assertFalse(Gate::forUser($viewer)->allows('move-stock'));
        $this->assertTrue(Gate::forUser($technician)->allows('move-stock'));

        // manage-inventory / manage-locations: Office and up.
        $this->assertFalse(Gate::forUser($technician)->allows('manage-inventory'));
        $this->assertTrue(Gate::forUser($office)->allows('manage-inventory'));
        $this->assertFalse(Gate::forUser($technician)->allows('manage-locations'));
        $this->assertTrue(Gate::forUser($office)->allows('manage-locations'));

        // Owner inherits everything.
        $this->assertTrue(Gate::forUser($owner)->allows('manage-inventory'));
    }

    public function test_viewer_cannot_open_the_item_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('inventory.create'))
            ->assertForbidden();
    }

    public function test_office_can_open_the_item_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('inventory.create'))
            ->assertOk();
    }

    public function test_technician_cannot_open_the_item_edit_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Technician))
            ->get(route('inventory.edit', $this->item->id))
            ->assertForbidden();
    }

    public function test_move_stock_form_is_hidden_from_a_viewer_but_shown_to_a_technician(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('inventory.show', $this->item->id))
            ->assertOk()
            ->assertDontSee('Move stock');

        $this->actingAs($this->userWith(UserRole::Technician))
            ->get(route('inventory.show', $this->item->id))
            ->assertOk()
            ->assertSee('Move stock');
    }

    public function test_add_item_button_is_hidden_from_a_viewer_but_shown_to_office(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertDontSee('Add item');

        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Add item');
    }
}
