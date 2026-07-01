<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\JobAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    private function job(): Job
    {
        return Job::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    }

    public function test_assigning_a_job_notifies_the_technician(): void
    {
        $tech = User::factory()->technician()->create(['tenant_id' => $this->tenant->id]);
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('assignUserId', $tech->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame(1, $tech->fresh()->notifications()->count());
        $this->assertSame(1, $tech->fresh()->unreadNotifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $tech->id,
            'type' => JobAssigned::class,
        ]);
    }

    public function test_assigning_to_yourself_does_not_notify(): void
    {
        $office = auth()->user();
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('assignUserId', $office->id)
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame(0, $office->fresh()->notifications()->count());
    }

    public function test_notifications_page_lists_and_marks_read(): void
    {
        $tech = User::factory()->technician()->create(['tenant_id' => $this->tenant->id]);
        $job = $this->job();
        $tech->notify(new JobAssigned($job));

        $this->actingAs($tech);

        Volt::test('notifications')
            ->assertSee($job->number)
            ->call('markAllRead')
            ->assertHasNoErrors();

        $this->assertSame(0, $tech->fresh()->unreadNotifications()->count());
    }
}
