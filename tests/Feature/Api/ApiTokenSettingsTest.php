<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ApiTokenSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
        $this->actingAs(User::factory()->admin()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_admin_can_create_a_token_and_sees_the_plaintext_once(): void
    {
        $component = Volt::test('settings.api-tokens')
            ->set('newName', 'Zapier')
            ->call('create')
            ->assertHasNoErrors();

        $plaintext = $component->get('plaintext');
        $this->assertNotNull($plaintext);
        $this->assertStringStartsWith('ttg_', $plaintext);

        // Only the hash is stored, never the plaintext.
        $token = ApiToken::where('name', 'Zapier')->first();
        $this->assertNotNull($token);
        $this->assertSame(ApiToken::hashFor($plaintext), $token->token_hash);
    }

    public function test_admin_can_revoke_a_token(): void
    {
        $token = ApiToken::issue(auth()->user(), 'Old')['token'];

        Volt::test('settings.api-tokens')
            ->call('revoke', $token->id)
            ->assertHasNoErrors();

        $this->assertNull(ApiToken::find($token->id));
    }

    public function test_non_admin_cannot_manage_tokens(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('settings.api-tokens')->assertForbidden();
    }
}
