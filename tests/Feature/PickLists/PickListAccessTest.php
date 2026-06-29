<?php

namespace Tests\Feature\PickLists;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\PickList;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickListAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private PickList $pickListA;
    private PickList $pickListB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->pickListA = $this->asTenant($this->tenantA, fn () => $this->makePickList());
        $this->pickListB = $this->asTenant($this->tenantB, fn () => $this->makePickList());

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makePickList(): PickList
    {
        $customer = Customer::factory()->create();
        $item = InventoryItem::factory()->create();
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $item->id,
            'description' => 'Part', 'quantity' => 5, 'position' => 0,
        ]);

        return PickList::generateFrom($job);
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

    private function userWith(UserRole $role): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_cannot_view_a_pick_list_from_another_tenant(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('picklists.show', $this->pickListB->id))
            ->assertNotFound();
    }

    public function test_pick_controls_are_hidden_from_a_viewer_but_shown_to_a_technician(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('picklists.show', $this->pickListA->id))
            ->assertOk()
            ->assertDontSee('Pick from');

        $this->actingAs($this->userWith(UserRole::Technician))
            ->get(route('picklists.show', $this->pickListA->id))
            ->assertOk()
            ->assertSee('Pick from');
    }
}
