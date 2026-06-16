<?php

namespace Tests\Feature\Customers;

use App\Enums\Province;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        // Office can manage customers.
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_office_can_create_a_customer(): void
    {
        Volt::test('customers.form')
            ->set('name', 'Acme Plumbing')
            ->set('email', 'ops@acme.test')
            ->set('province', 'ON')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $customer = Customer::firstWhere('name', 'Acme Plumbing');

        $this->assertNotNull($customer);
        $this->assertSame($this->tenant->id, $customer->tenant_id);
        $this->assertSame(Province::ON, $customer->province);
    }

    public function test_name_is_required(): void
    {
        Volt::test('customers.form')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_invalid_email_is_rejected(): void
    {
        Volt::test('customers.form')
            ->set('name', 'Bad Email Co')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_tax_exempt_details_persist(): void
    {
        Volt::test('customers.form')
            ->set('name', 'Exempt Co')
            ->set('tax_exempt', true)
            ->set('tax_number', 'EX-12345')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::firstWhere('name', 'Exempt Co');

        $this->assertTrue($customer->tax_exempt);
        $this->assertSame('EX-12345', $customer->tax_number);
    }

    public function test_editing_prefills_and_updates(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Co']);

        Volt::test('customers.form', ['customerId' => $customer->id])
            ->assertSet('name', 'Old Co')
            ->set('name', 'New Co')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Co', $customer->fresh()->name);
    }

    public function test_province_is_cast_to_the_enum(): void
    {
        $customer = Customer::factory()->create(['province' => 'BC']);

        $this->assertSame(Province::BC, $customer->fresh()->province);
    }
}
