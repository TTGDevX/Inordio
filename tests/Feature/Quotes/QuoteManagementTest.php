<?php

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
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

    public function test_office_can_build_a_quote_with_line_items(): void
    {
        Volt::test('quotes.form')
            ->set('customer_id', $this->customer->id)
            ->set('lines', [
                // Empty string mirrors the "custom / no catalogue item" select option.
                ['inventory_item_id' => '', 'description' => 'Labour', 'quantity' => '2', 'unit_price' => '50'],
                ['inventory_item_id' => '', 'description' => 'Cable', 'quantity' => '3', 'unit_price' => '10'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $quote = Quote::with('lines')->latest('id')->first();

        $this->assertSame($this->customer->id, $quote->customer_id);
        $this->assertSame($this->tenant->id, $quote->tenant_id);
        $this->assertCount(2, $quote->lines);
        // 2*50 + 3*10 = 130, pre-tax.
        $this->assertEqualsWithDelta(130.0, $quote->subtotal(), 0.001);
        // Auto-numbered on create.
        $this->assertMatchesRegularExpression('/^Q-\d{5}$/', $quote->number);
    }

    public function test_selecting_a_catalogue_item_prefills_description_and_price(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Widget', 'price' => '42.50']);

        Volt::test('quotes.form')
            ->set('lines.0.inventory_item_id', $item->id)
            ->assertSet('lines.0.description', 'Widget')
            ->assertSet('lines.0.unit_price', '42.50');
    }

    public function test_customer_is_required(): void
    {
        Volt::test('quotes.form')
            ->set('customer_id', null)
            ->call('save')
            ->assertHasErrors('customer_id');
    }

    public function test_each_line_needs_a_description(): void
    {
        Volt::test('quotes.form')
            ->set('customer_id', $this->customer->id)
            ->set('lines', [
                ['inventory_item_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '5'],
            ])
            ->call('save')
            ->assertHasErrors('lines.0.description');
    }

    public function test_editing_replaces_the_line_set(): void
    {
        $quote = Quote::factory()->create(['customer_id' => $this->customer->id]);
        QuoteLineItem::factory()->create([
            'quote_id' => $quote->id, 'description' => 'Old', 'quantity' => 1, 'unit_price' => 10, 'position' => 0,
        ]);

        Volt::test('quotes.form', ['quoteId' => $quote->id])
            ->assertSet('customer_id', $this->customer->id)
            ->set('lines.0.description', 'Updated line')
            ->call('save')
            ->assertHasNoErrors();

        $quote->refresh()->load('lines');
        $this->assertCount(1, $quote->lines);
        $this->assertSame('Updated line', $quote->lines->first()->description);
    }

    public function test_status_transitions_through_the_lifecycle(): void
    {
        $quote = Quote::factory()->create(['customer_id' => $this->customer->id]);
        $this->assertSame(QuoteStatus::Draft, $quote->status);

        $quote->markSent();
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);

        $quote->approve();
        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->approved_at);
    }

    public function test_send_action_on_show_marks_the_quote_sent(): void
    {
        $quote = Quote::factory()->create(['customer_id' => $this->customer->id]);

        Volt::test('quotes.show', ['quoteId' => $quote->id])
            ->call('send')
            ->assertHasNoErrors();

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }
}
