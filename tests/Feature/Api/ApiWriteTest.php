<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;        // office — can write
    private string $viewerToken;  // read-only
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->token = ApiToken::issue(User::factory()->office()->create(['tenant_id' => $this->tenant->id]), 'office')['plaintext'];
        $this->viewerToken = ApiToken::issue(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]), 'viewer')['plaintext'];
        $this->customerId = Customer::factory()->create(['name' => 'Existing Co'])->id;

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_can_create_a_customer(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/customers', ['name' => 'New Plumbing Co', 'email' => 'ops@new.test'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Plumbing Co');

        $this->assertDatabaseHas('customers', ['name' => 'New Plumbing Co', 'tenant_id' => $this->tenant->id]);
    }

    public function test_customer_creation_validates(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/customers', [])
            ->assertStatus(422);
    }

    public function test_read_only_token_cannot_write(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson('/api/v1/customers', ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Nope']);
    }

    public function test_can_update_a_customer(): void
    {
        $this->withToken($this->token)
            ->patchJson('/api/v1/customers/'.$this->customerId, ['name' => 'Renamed Co'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Co');
    }

    public function test_can_create_a_job(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/jobs', ['customer_id' => $this->customerId, 'title' => 'API-created job'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'API-created job');

        $this->assertDatabaseHas('service_jobs', ['title' => 'API-created job', 'tenant_id' => $this->tenant->id]);
    }
}
