<?php

namespace Tests\Feature\Exports;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->asTenant($this->tenantA, function () {
            Customer::factory()->create(['name' => 'Alpha Plumbing']);
            InventoryItem::factory()->create(['name' => 'Copper Pipe', 'internal_sku' => 'TTG-PIPE-1']);
        });
        $this->asTenant($this->tenantB, fn () => Customer::factory()->create(['name' => 'Bravo Heating']));

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function asTenant(Tenant $tenant, callable $cb): void
    {
        tenancy()->initialize($tenant);
        try {
            $cb();
        } finally {
            tenancy()->end();
        }
    }

    private function userWith(UserRole $role, Tenant $tenant): User
    {
        return User::factory()->role($role)->create(['tenant_id' => $tenant->id]);
    }

    public function test_customers_csv_contains_only_this_tenants_rows(): void
    {
        $response = $this->actingAs($this->userWith(UserRole::Office, $this->tenantA))
            ->get(route('exports.customers'));

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Alpha Plumbing', $body);
        $this->assertStringNotContainsString('Bravo Heating', $body);
    }

    public function test_inventory_csv_contains_items(): void
    {
        $response = $this->actingAs($this->userWith(UserRole::Office, $this->tenantA))
            ->get(route('exports.inventory'));

        $response->assertOk();
        $this->assertStringContainsString('Copper Pipe', $response->streamedContent());
    }

    public function test_viewer_cannot_export(): void
    {
        $this->actingAs($this->userWith(UserRole::Viewer, $this->tenantA))
            ->get(route('exports.customers'))
            ->assertForbidden();
    }
}
