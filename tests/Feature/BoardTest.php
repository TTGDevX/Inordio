<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\PickList;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
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

    public function test_board_shows_active_jobs_and_the_picking_queue(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));

        $customer = Customer::factory()->create(['name' => 'Acme Co']);
        $job = Job::factory()->create(['customer_id' => $customer->id, 'title' => 'Furnace tune-up']);
        $item = InventoryItem::factory()->create();
        JobLineItem::factory()->create(['job_id' => $job->id, 'inventory_item_id' => $item->id, 'quantity' => 1, 'position' => 0]);
        $pickList = PickList::generateFrom($job);

        $this->get(route('board'))
            ->assertOk()
            ->assertSee('Furnace tune-up')
            ->assertSee('Acme Co')
            ->assertSee($pickList->job->number);
    }

    public function test_completed_jobs_and_finished_pick_lists_drop_off_the_board(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));

        $customer = Customer::factory()->create();
        Job::factory()->done()->create(['customer_id' => $customer->id, 'title' => 'Old finished job']);

        $this->get(route('board'))->assertOk()->assertDontSee('Old finished job');
    }

    public function test_guests_cannot_view_the_board(): void
    {
        $this->get(route('board'))->assertRedirect(route('login'));
    }
}
