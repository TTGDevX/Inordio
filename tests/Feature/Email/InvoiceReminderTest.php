<?php

namespace Tests\Feature\Email;

use App\Enums\PaymentMethod;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $customer = Customer::factory()->create(['email' => 'a@b.test']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        $this->invoice = Invoice::fromJob($job);
        $this->invoice->markSent();
        $this->invoice->update(['due_at' => now()->subDays(5)->toDateString()]); // overdue

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_overdue_unpaid_invoice_gets_a_reminder(): void
    {
        Mail::fake();

        $this->artisan('invoices:send-reminders')->assertExitCode(0);

        Mail::assertSent(InvoiceMail::class, fn ($m) => $m->reminder === true && $m->hasTo('a@b.test'));

        tenancy()->initialize($this->tenant);
        $this->assertNotNull($this->invoice->fresh()->reminder_sent_at);
    }

    public function test_recently_reminded_invoice_is_skipped(): void
    {
        tenancy()->initialize($this->tenant);
        $this->invoice->forceFill(['reminder_sent_at' => now()])->save();
        tenancy()->end();

        Mail::fake();
        $this->artisan('invoices:send-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_paid_invoice_is_skipped(): void
    {
        tenancy()->initialize($this->tenant);
        $this->invoice->recordPayment((float) $this->invoice->total(), PaymentMethod::Cash);
        tenancy()->end();

        Mail::fake();
        $this->artisan('invoices:send-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
    }
}
