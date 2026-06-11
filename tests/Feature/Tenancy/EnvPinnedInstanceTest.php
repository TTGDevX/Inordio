<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvPinnedInstanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_pinned_instance_serves_users_of_its_tenant(): void
    {
        config(['inordio.tenant_id' => $this->tenantA->id]);
        $user = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame($this->tenantA->id, tenant('id'));
    }

    public function test_pinned_instance_rejects_users_of_other_tenants(): void
    {
        config(['inordio.tenant_id' => $this->tenantA->id]);
        $user = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_unpinned_instance_resolves_tenant_from_authenticated_user(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame($this->tenantB->id, tenant('id'));
    }
}
