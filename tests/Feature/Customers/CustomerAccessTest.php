<?php

namespace Tests\Feature\Customers;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant isolation and role gating for customers, exercised over HTTP so the
 * IdentifyTenant middleware and the manage-customers gate are both in play.
 */
class CustomerAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->customerA = $this->asTenant($this->tenantA, fn () => Customer::factory()->create(['name' => 'Alpha Co']));
        $this->customerB = $this->asTenant($this->tenantB, fn () => Customer::factory()->create(['name' => 'Bravo Co']));

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

    private function userWith(UserRole $role): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_index_lists_only_the_current_tenants_customers(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Alpha Co')
            ->assertDontSee('Bravo Co');
    }

    public function test_user_cannot_view_a_customer_from_another_tenant(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('customers.show', $this->customerB->id))
            ->assertNotFound();
    }

    public function test_viewer_cannot_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('customers.create'))
            ->assertForbidden();
    }

    public function test_office_can_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('customers.create'))
            ->assertOk();
    }

    public function test_add_customer_button_is_hidden_from_a_viewer_but_shown_to_office(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('Add customer');

        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Add customer');
    }
}
