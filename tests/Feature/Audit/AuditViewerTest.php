<?php

namespace Tests\Feature\Audit;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditViewerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_admin_sees_audit_entries(): void
    {
        $this->actingAs(User::factory()->admin()->create(['tenant_id' => $this->tenant->id]));

        // Quote is an audited model (the Auditable trait), so creating one logs it.
        Quote::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        Volt::test('audit.index')
            ->assertOk()
            ->assertSee('Quote')
            ->assertSee('Created');
    }

    public function test_action_filter_narrows_results(): void
    {
        $this->actingAs(User::factory()->admin()->create(['tenant_id' => $this->tenant->id]));

        $quote = Quote::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $quote->update(['notes' => 'Changed']); // an "updated" entry

        Volt::test('audit.index')
            ->set('action', 'deleted')
            ->assertOk()
            ->assertSee('No audit entries');
    }

    public function test_office_user_cannot_open_the_audit_trail(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('audit.index')->assertForbidden();
    }
}
