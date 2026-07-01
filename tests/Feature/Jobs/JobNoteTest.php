<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobNote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobNoteTest extends TestCase
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

    private function job(): Job
    {
        return Job::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    }

    public function test_technician_can_add_a_note(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('newNote', 'Replaced the compressor; customer notified.')
            ->call('addNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('job_notes', [
            'job_id' => $job->id,
            'body' => 'Replaced the compressor; customer notified.',
        ]);
    }

    public function test_blank_note_is_rejected(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('newNote', '')
            ->call('addNote')
            ->assertHasErrors('newNote');
    }

    public function test_office_can_remove_a_note(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();
        $note = $job->noteThread()->create(['user_id' => null, 'body' => 'Temp note']);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('removeNote', $note->id)
            ->assertHasNoErrors();

        $this->assertNull(JobNote::find($note->id));
    }

    public function test_viewer_cannot_add_a_note(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('newNote', 'Sneaky')
            ->call('addNote')
            ->assertForbidden();

        $this->assertSame(0, JobNote::where('job_id', $job->id)->count());
    }

    public function test_notes_do_not_leak_across_tenants(): void
    {
        $job = $this->job();
        $job->noteThread()->create(['user_id' => null, 'body' => 'Tenant A only']);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, JobNote::count());
    }
}
