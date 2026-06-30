<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_creating_an_audited_model_writes_a_log_scoped_to_the_tenant(): void
    {
        $quote = Quote::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $log = AuditLog::where('auditable_type', Quote::class)
            ->where('auditable_id', $quote->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame(auth()->id(), $log->user_id);
    }

    public function test_updating_records_the_changed_attributes(): void
    {
        $invoice = Invoice::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $invoice->markSent(); // changes status + sent_at

        $log = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $invoice->id)
            ->where('action', 'updated')
            ->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('status', $log->changes);
    }

    public function test_audit_logs_are_tenant_isolated(): void
    {
        $quote = Quote::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);

        $this->assertSame(0, AuditLog::count());
    }
}
