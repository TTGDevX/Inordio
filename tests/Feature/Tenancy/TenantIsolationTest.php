<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        User::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'a@example.com']);
        User::factory()->create(['tenant_id' => $this->tenantB->id, 'email' => 'b@example.com']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_queries_are_scoped_to_the_initialized_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->assertSame(['a@example.com'], User::pluck('email')->all());

        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->assertSame(['b@example.com'], User::pluck('email')->all());
    }

    public function test_tenant_a_cannot_fetch_tenant_b_records_by_id(): void
    {
        $bUser = User::firstWhere('email', 'b@example.com');

        tenancy()->initialize($this->tenantA);

        $this->assertNull(User::find($bUser->id));
    }

    public function test_models_created_under_tenancy_get_tenant_id_automatically(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::factory()->create(['email' => 'new@example.com']);

        $this->assertSame($this->tenantA->id, $user->tenant_id);
    }

    public function test_role_is_cast_to_enum_with_working_hierarchy(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::Office,
        ]);

        $this->assertSame(UserRole::Office, $user->refresh()->role);
        $this->assertTrue($user->role->isAtLeast(UserRole::Technician));
        $this->assertFalse($user->role->isAtLeast(UserRole::Admin));
    }
}
