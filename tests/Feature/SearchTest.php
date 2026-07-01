<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SearchTest extends TestCase
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

    public function test_search_finds_customers_jobs_and_items(): void
    {
        $customer = Customer::factory()->create(['name' => 'Zephyr Plumbing']);
        Job::factory()->create(['customer_id' => $customer->id, 'title' => 'Zephyr furnace swap']);
        InventoryItem::factory()->create(['name' => 'Zephyr valve']);

        Volt::test('search')
            ->set('search', 'Zephyr')
            ->assertSee('Zephyr Plumbing')
            ->assertSee('Zephyr furnace swap')
            ->assertSee('Zephyr valve');
    }

    public function test_short_queries_do_not_search(): void
    {
        Customer::factory()->create(['name' => 'Acme']);

        Volt::test('search')
            ->set('search', 'A')
            ->assertSee('Type at least two characters')
            ->assertDontSee('Acme');
    }

    public function test_no_results_message(): void
    {
        Customer::factory()->create(['name' => 'Acme']);

        Volt::test('search')
            ->set('search', 'zznotfound')
            ->assertSee('Nothing matches');
    }

    public function test_search_is_tenant_scoped(): void
    {
        Customer::factory()->create(['name' => 'Alpha Co']);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $other->id]));

        Volt::test('search')
            ->set('search', 'Alpha')
            ->assertDontSee('Alpha Co');
    }

    public function test_guests_cannot_search(): void
    {
        auth()->logout();

        $this->get(route('search'))->assertRedirect(route('login'));
    }
}
