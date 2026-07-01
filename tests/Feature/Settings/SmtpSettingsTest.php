<?php

namespace Tests\Feature\Settings;

use App\Mail\InvoiceMail;
use App\Mail\TestEmail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
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

    public function test_smtp_settings_save_and_the_password_is_encrypted_at_rest(): void
    {
        Volt::test('settings.company')
            ->set('mail_host', 'smtp.mailgun.org')
            ->set('mail_port', 587)
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'postmaster@ttg')
            ->set('mail_password', 'super-secret')
            ->set('mail_from_address', 'billing@ttg.test')
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::current();
        $this->assertSame('smtp.mailgun.org', $settings->mail_host);
        $this->assertSame('super-secret', $settings->mail_password); // decrypted by cast

        // Stored ciphertext must not equal the plaintext.
        $raw = DB::table('company_settings')->where('tenant_id', $this->tenant->id)->value('mail_password');
        $this->assertNotNull($raw);
        $this->assertNotSame('super-secret', $raw);
    }

    public function test_a_blank_password_keeps_the_existing_secret(): void
    {
        CompanySetting::current()->update(['mail_host' => 'smtp.old', 'mail_password' => 'keep-me']);

        Volt::test('settings.company')
            ->set('mail_host', 'smtp.new')
            ->set('mail_password', '') // untouched
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::current();
        $this->assertSame('smtp.new', $settings->mail_host);
        $this->assertSame('keep-me', $settings->mail_password);
    }

    public function test_tenant_mailer_uses_custom_smtp_when_configured_else_default(): void
    {
        // No host → default mailer.
        $default = TenantMailer::resolve(CompanySetting::current());
        $this->assertSame(config('mail.default'), $default['mailer']);

        CompanySetting::current()->update([
            'mail_host' => 'smtp.mailgun.org', 'mail_port' => 2525, 'mail_username' => 'u', 'mail_password' => 'p',
        ]);

        $custom = TenantMailer::resolve(CompanySetting::current());
        $this->assertSame('tenant', $custom['mailer']);
        $this->assertSame('smtp.mailgun.org', config('mail.mailers.tenant.host'));
        $this->assertSame(2525, config('mail.mailers.tenant.port'));
    }

    public function test_send_test_email_dispatches_a_test_message(): void
    {
        Mail::fake();
        CompanySetting::current()->update(['mail_from_address' => 'billing@ttg.test']);

        Volt::test('settings.company')
            ->set('testEmailTo', 'owner@ttg.test')
            ->call('sendTest')
            ->assertHasNoErrors();

        Mail::assertSent(TestEmail::class, fn ($mail) => $mail->hasTo('owner@ttg.test'));
    }

    public function test_invoice_email_is_stamped_with_the_tenant_from_address(): void
    {
        Mail::fake();
        CompanySetting::current()->update(['mail_from_address' => 'billing@ttg.test', 'mail_from_name' => 'TTG Billing']);

        $customer = Customer::factory()->create(['email' => 'client@acme.test']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);
        $invoice = Invoice::fromJob($job);

        Volt::test('invoices.show', ['invoiceId' => $invoice->id])
            ->call('emailToCustomer')
            ->assertHasNoErrors();

        // The mailable carries the tenant's company (whose from-address the
        // envelope uses); asserting it is reliable under Mail::fake.
        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasTo('client@acme.test')
            && $mail->company->mail_from_address === 'billing@ttg.test');
    }
}
