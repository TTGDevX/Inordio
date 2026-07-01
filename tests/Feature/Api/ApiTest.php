<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $user = User::factory()->office()->create(['tenant_id' => $this->tenant->id]);
        Customer::factory()->create(['name' => 'Acme Co']);
        $this->token = ApiToken::issue($user, 'test integration')['plaintext'];

        tenancy()->end(); // simulate a fresh, unauthenticated API request
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_a_valid_token_returns_the_tenants_data(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Acme Co']);
    }

    public function test_missing_or_invalid_tokens_are_unauthorized(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->withToken('ttg_not-a-real-token')->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_the_me_endpoint_reports_the_token_tenant(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('tenant.name', 'TTG');
    }

    public function test_a_token_only_sees_its_own_tenants_data(): void
    {
        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        Customer::factory()->create(['name' => 'Beta Corp']);
        tenancy()->end();

        $this->withToken($this->token)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Acme Co'])
            ->assertJsonMissing(['name' => 'Beta Corp']);
    }

    public function test_a_revoked_token_stops_working(): void
    {
        tenancy()->initialize($this->tenant);
        ApiToken::query()->delete();
        tenancy()->end();

        $this->withToken($this->token)->getJson('/api/v1/customers')->assertUnauthorized();
    }
}
