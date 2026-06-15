<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private InventoryItem $itemA;

    private InventoryItem $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->itemA = $this->asTenant($this->tenantA, fn () => InventoryItem::factory()->create([
            'name' => 'Alpha Widget',
            'internal_sku' => 'TTG-ALPHA-001',
        ]));

        $this->itemB = $this->asTenant($this->tenantB, fn () => InventoryItem::factory()->create([
            'name' => 'Bravo Gadget',
            'internal_sku' => 'TTG-BRAVO-001',
        ]));

        // Ensure the request pipeline initializes tenancy from the acting user,
        // not from this setup.
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function asTenant(Tenant $tenant, callable $callback): mixed
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_tenants_items(): void
    {
        $this->actingAs($this->userA)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Alpha Widget')
            ->assertDontSee('Bravo Gadget');
    }

    public function test_search_filters_the_list(): void
    {
        $this->asTenant($this->tenantA, fn () => InventoryItem::factory()->create([
            'name' => 'Copper Fitting',
            'internal_sku' => 'TTG-COPPER-001',
        ]));
        tenancy()->end();

        $this->actingAs($this->userA)
            ->get(route('inventory.index', ['q' => 'Copper']))
            ->assertOk()
            ->assertSee('Copper Fitting')
            ->assertDontSee('Alpha Widget');
    }

    public function test_user_can_view_an_item_in_their_tenant(): void
    {
        $this->actingAs($this->userA)
            ->get(route('inventory.show', $this->itemA))
            ->assertOk()
            ->assertSee('Alpha Widget')
            ->assertSee('TTG-ALPHA-001');
    }

    public function test_user_cannot_view_an_item_from_another_tenant(): void
    {
        // The crux of the binding-order fix: resolving the item inside the
        // component (after tenancy is initialized) makes this a clean 404
        // rather than a cross-tenant data leak.
        $this->actingAs($this->userA)
            ->get(route('inventory.show', $this->itemB))
            ->assertNotFound();
    }
}
