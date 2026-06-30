<?php

namespace Tests\Feature\Print;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->customer = Customer::factory()->taxExempt()->create(['name' => 'Acme Co']);
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        Invoice::fromJob($job)->markSent();

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

    public function test_statement_lists_invoices_and_balance(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('customers.statement', $this->customer->id))
            ->assertOk()
            ->assertSee('Acme Co')
            ->assertSee('Balance owing')
            ->assertSee('$100.00');
    }

    public function test_viewer_cannot_open_a_statement(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('customers.statement', $this->customer->id))
            ->assertForbidden();
    }
}
