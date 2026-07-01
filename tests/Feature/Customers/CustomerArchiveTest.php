<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerArchiveTest extends TestCase
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

    public function test_archiving_hides_a_customer_from_the_default_list(): void
    {
        $customer = Customer::factory()->create(['name' => 'Retired Roofing']);

        Volt::test('customers.show', ['customerId' => $customer->id])
            ->call('toggleArchive')
            ->assertHasNoErrors();

        $this->assertFalse($customer->fresh()->is_active);

        // Default list excludes it; the archived view includes it.
        Volt::test('customers.index')->assertDontSee('Retired Roofing');
        Volt::test('customers.index')->set('archived', true)->assertSee('Retired Roofing');
    }

    public function test_restore_brings_a_customer_back(): void
    {
        $customer = Customer::factory()->create(['name' => 'Back In Business', 'is_active' => false]);

        Volt::test('customers.show', ['customerId' => $customer->id])
            ->call('toggleArchive')
            ->assertHasNoErrors();

        $this->assertTrue($customer->fresh()->is_active);
        Volt::test('customers.index')->assertSee('Back In Business');
    }

    public function test_viewer_cannot_archive(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $customer = Customer::factory()->create();

        Volt::test('customers.show', ['customerId' => $customer->id])
            ->call('toggleArchive')
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->is_active);
    }
}
