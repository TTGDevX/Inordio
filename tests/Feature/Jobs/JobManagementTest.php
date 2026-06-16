<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($this->tenant);

        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $this->customer = Customer::factory()->create();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_office_can_create_a_job(): void
    {
        Volt::test('jobs.form')
            ->set('customer_id', $this->customer->id)
            ->set('title', 'Replace water heater')
            ->set('lines', [
                ['inventory_item_id' => '', 'description' => 'Labour', 'quantity' => '4', 'unit_price' => '60'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $job = Job::with('lines')->latest('id')->first();

        $this->assertSame('Replace water heater', $job->title);
        $this->assertSame($this->tenant->id, $job->tenant_id);
        $this->assertSame(JobStatus::Scheduled, $job->status);
        $this->assertSame('J-'.str_pad((string) $job->id, 5, '0', STR_PAD_LEFT), $job->number);
        $this->assertCount(1, $job->lines);
    }

    public function test_title_and_customer_are_required(): void
    {
        Volt::test('jobs.form')
            ->set('customer_id', null)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['customer_id', 'title']);
    }

    public function test_converting_an_approved_quote_copies_lines_and_links_back(): void
    {
        $quote = Quote::factory()->approved()->create(['customer_id' => $this->customer->id]);
        QuoteLineItem::factory()->count(2)->create(['quote_id' => $quote->id]);

        Volt::test('quotes.show', ['quoteId' => $quote->id])
            ->call('convertToJob')
            ->assertRedirect();

        $job = Job::where('quote_id', $quote->id)->with('lines')->first();

        $this->assertNotNull($job);
        $this->assertSame($this->customer->id, $job->customer_id);
        $this->assertSame(JobStatus::Scheduled, $job->status);
        $this->assertCount(2, $job->lines);
        $this->assertTrue($quote->fresh()->job()->exists());
    }

    public function test_conversion_is_idempotent(): void
    {
        $quote = Quote::factory()->approved()->create(['customer_id' => $this->customer->id]);
        QuoteLineItem::factory()->create(['quote_id' => $quote->id]);

        Volt::test('quotes.show', ['quoteId' => $quote->id])->call('convertToJob');
        Volt::test('quotes.show', ['quoteId' => $quote->id])->call('convertToJob');

        $this->assertSame(1, Job::where('quote_id', $quote->id)->count());
    }

    public function test_status_transitions_through_the_lifecycle(): void
    {
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);
        $this->assertSame(JobStatus::Scheduled, $job->status);

        $job->start();
        $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
        $this->assertNotNull($job->fresh()->started_at);

        $job->complete();
        $this->assertSame(JobStatus::Done, $job->fresh()->status);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_technician_can_start_a_job(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('start')
            ->assertHasNoErrors();

        $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
    }

    public function test_assigning_a_technician_persists(): void
    {
        $tech = User::factory()->technician()->create(['tenant_id' => $this->tenant->id]);
        $job = Job::factory()->create(['customer_id' => $this->customer->id]);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('assignUserId', $tech->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame($tech->id, $job->fresh()->assigned_user_id);
    }
}
