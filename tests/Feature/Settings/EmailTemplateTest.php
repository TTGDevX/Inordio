<?php

namespace Tests\Feature\Settings;

use App\Mail\InvoiceMail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
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

    private function invoiceForCustomer(string $name): Invoice
    {
        $customer = Customer::factory()->create(['name' => $name, 'contact_name' => null, 'email' => 'c@acme.test']);
        $job = Job::factory()->create(['customer_id' => $customer->id]);
        JobLineItem::factory()->create(['job_id' => $job->id, 'quantity' => 1, 'unit_price' => 100, 'position' => 0]);

        return Invoice::fromJob($job);
    }

    public function test_render_substitutes_known_tokens_and_never_evaluates_input(): void
    {
        $out = DocumentTemplate::render('Hi {{ customer_name }} {{ nope }} {{ 7*7 }}', ['customer_name' => 'Acme']);

        // Known token replaced, unknown token blanked, and {{ 7*7 }} left LITERAL
        // (never evaluated to 49 — no code-execution surface).
        $this->assertSame('Hi Acme  {{ 7*7 }}', $out);
        $this->assertStringNotContainsString('49', $out);
    }

    public function test_a_saved_template_drives_the_invoice_email(): void
    {
        DocumentTemplate::updateOrCreate(['type' => 'invoice_email'], [
            'subject' => 'Bill {{ invoice_number }}',
            'body' => 'Hello {{ customer_name }}, you owe {{ invoice_balance }}.',
        ]);

        $invoice = $this->invoiceForCustomer('Acme Co');
        $mailable = new InvoiceMail($invoice, CompanySetting::current());

        $this->assertSame('Bill '.$invoice->number, $mailable->envelope()->subject);
        $this->assertStringContainsString('Hello Acme Co', $mailable->render());
    }

    public function test_defaults_are_used_when_no_template_is_saved(): void
    {
        $resolved = DocumentTemplate::resolve('invoice_email');
        $this->assertSame(DocumentTemplate::defaults('invoice_email')['subject'], $resolved['subject']);

        $invoice = $this->invoiceForCustomer('Acme');
        $subject = (new InvoiceMail($invoice, CompanySetting::current()))->envelope()->subject;
        $this->assertStringContainsString($invoice->number, $subject);
    }

    public function test_settings_page_saves_templates(): void
    {
        Volt::test('settings.templates')
            ->set('subject.invoice_email', 'Your invoice {{ invoice_number }}')
            ->set('body.invoice_email', 'Thanks {{ customer_name }}')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_templates', [
            'type' => 'invoice_email',
            'subject' => 'Your invoice {{ invoice_number }}',
        ]);
    }

    public function test_reset_restores_the_default_and_removes_the_saved_row(): void
    {
        DocumentTemplate::updateOrCreate(['type' => 'invoice_email'], ['subject' => 'Custom', 'body' => 'Custom body']);

        Volt::test('settings.templates')
            ->call('resetType', 'invoice_email')
            ->assertHasNoErrors();

        $this->assertSame(0, DocumentTemplate::where('type', 'invoice_email')->count());
    }

    public function test_non_admin_cannot_edit_templates(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('settings.templates')->assertForbidden();
    }

    public function test_templates_are_tenant_isolated(): void
    {
        DocumentTemplate::updateOrCreate(['type' => 'invoice_email'], ['subject' => 'A only', 'body' => 'x']);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, DocumentTemplate::count());
    }
}
