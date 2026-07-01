<?php

namespace Tests\Feature\Invoices;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Invoice $invoiceA;

    private Invoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->invoiceA = $this->asTenant($this->tenantA, fn () => $this->makeInvoice('Customer A'));
        $this->invoiceB = $this->asTenant($this->tenantB, fn () => $this->makeInvoice('Customer B'));

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function makeInvoice(string $customerName = 'Acme'): Invoice
    {
        $customer = Customer::factory()->create(['name' => $customerName, 'province' => 'ON']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        return Invoice::fromJob($job);
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

    public function test_index_lists_only_the_current_tenants_invoices(): void
    {
        // Both tenants' first invoice is legitimately INV-00001 (per-tenant
        // counter), so discriminate by the tenant-scoped customer name.
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Customer A')
            ->assertDontSee('Customer B');
    }

    public function test_user_cannot_view_an_invoice_from_another_tenant(): void
    {
        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('invoices.show', $this->invoiceB->id))
            ->assertNotFound();
    }

    public function test_payment_form_is_hidden_from_a_viewer_but_shown_to_office(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('invoices.show', $this->invoiceA->id))
            ->assertOk()
            ->assertDontSee('Record payment');

        $this->actingAs($this->userWith(UserRole::Office))
            ->get(route('invoices.show', $this->invoiceA->id))
            ->assertOk()
            ->assertSee('Record payment');
    }

    public function test_mark_as_sent_is_hidden_from_a_viewer(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer))
            ->get(route('invoices.show', $this->invoiceA->id))
            ->assertOk()
            ->assertDontSee('Mark as sent');
    }
}
