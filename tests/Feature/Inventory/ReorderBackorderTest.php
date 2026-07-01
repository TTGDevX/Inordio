<?php

namespace Tests\Feature\Inventory;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\PickList;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReorderBackorderTest extends TestCase
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

    private function shortPickOnJob(Job $job): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Copper Pipe']);
        JobLineItem::factory()->create([
            'job_id' => $job->id, 'inventory_item_id' => $item->id,
            'description' => 'Copper Pipe', 'quantity' => 10, 'position' => 0,
        ]);
        $pickList = PickList::generateFrom($job);
        $pickList->items->first()->markShort(); // whole 10 back-ordered
    }

    public function test_open_job_backorders_appear_on_the_reorder_view(): void
    {
        $job = Job::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $this->shortPickOnJob($job);

        Volt::test('inventory.reorder')
            ->assertOk()
            ->assertSee('Back-ordered from picks')
            ->assertSee('Copper Pipe')
            ->assertSee($job->number);
    }

    public function test_backorders_on_finished_jobs_are_not_shown(): void
    {
        $job = Job::factory()->done()->create(['customer_id' => Customer::factory()->create()->id]);
        $this->shortPickOnJob($job);

        Volt::test('inventory.reorder')
            ->assertOk()
            ->assertDontSee('Copper Pipe');
    }
}
