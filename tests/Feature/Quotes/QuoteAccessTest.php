<?php

namespace Tests\Feature\Quotes;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Quote $quoteA;

    private Quote $quoteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->quoteA = $this->asTenant($this->tenantA, function () {
            $customer = Customer::factory()->create(['name' => 'Customer A']);

            return Quote::factory()->create(['customer_id' => $customer->id]);
        });

        $this->quoteB = $this->asTenant($this->tenantB, function () {
            $customer = Customer::factory()->create(['name' => 'Customer B']);

            return Quote::factory()->create(['customer_id' => $customer->id]);
        });

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

    public function test_index_lists_only_the_current_tenants_quotes(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertSee($this->quoteA->number)
            ->assertDontSee($this->quoteB->number);
    }

    public function test_user_cannot_view_a_quote_from_another_tenant(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('quotes.show', $this->quoteB->id))
            ->assertNotFound();
    }

    public function test_viewer_cannot_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('quotes.create'))
            ->assertForbidden();
    }

    public function test_office_can_open_the_create_page(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('quotes.create'))
            ->assertOk();
    }

    public function test_new_quote_button_is_hidden_from_a_viewer_but_shown_to_office(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertDontSee('New quote');

        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertSee('New quote');
    }
}
