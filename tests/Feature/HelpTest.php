<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_help_page_renders_for_signed_in_users(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertSee('Help &amp; guide', false)
            ->assertSee('The core workflow');
    }

    public function test_guests_cannot_view_help(): void
    {
        auth()->logout();

        $this->get(route('help'))->assertRedirect(route('login'));
    }

    public function test_eft_direct_deposit_is_a_payment_method(): void
    {
        $this->assertArrayHasKey('eft', PaymentMethod::options());

        Mail::fake();
        $customer = Customer::factory()->taxExempt()->create(['email' => null]);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        $invoice = Invoice::fromJob($job);

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->set('payAmount', '100')
            ->set('payMethod', 'eft')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'eft']);
    }
}
