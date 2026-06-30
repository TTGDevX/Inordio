<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function makeJob(Tenant $tenant): Job
    {
        return Job::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    public function test_technician_can_attach_a_photo_to_a_job(): void
    {
        Storage::fake('public');
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $tenant->id]));

        $job = $this->makeJob($tenant);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('photo', UploadedFile::fake()->image('panel.jpg'))
            ->set('caption', 'Old panel before swap')
            ->call('addPhoto')
            ->assertHasNoErrors();

        $photo = JobPhoto::where('job_id', $job->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame('Old panel before swap', $photo->caption);
        Storage::disk('public')->assertExists($photo->path);

        tenancy()->end();
    }

    public function test_viewer_cannot_attach_a_photo(): void
    {
        Storage::fake('public');
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $tenant->id]));

        $job = $this->makeJob($tenant);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('photo', UploadedFile::fake()->image('x.jpg'))
            ->call('addPhoto')
            ->assertForbidden();

        $this->assertSame(0, JobPhoto::where('job_id', $job->id)->count());

        tenancy()->end();
    }

    public function test_office_can_remove_a_photo(): void
    {
        Storage::fake('public');
        $tenant = Tenant::create(['name' => 'TTG']);
        tenancy()->initialize($tenant);
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $tenant->id]));

        $job = $this->makeJob($tenant);
        $path = UploadedFile::fake()->image('y.jpg')->store('job-photos/'.$tenant->id, 'public');
        $photo = $job->photos()->create(['path' => $path, 'caption' => null]);

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('removePhoto', $photo->id)
            ->assertHasNoErrors();

        $this->assertNull(JobPhoto::find($photo->id));
        Storage::disk('public')->assertMissing($path);

        tenancy()->end();
    }

    public function test_photos_do_not_leak_across_tenants(): void
    {
        Storage::fake('public');

        $tenantA = Tenant::create(['name' => 'A']);
        tenancy()->initialize($tenantA);
        $jobA = $this->makeJob($tenantA);
        $jobA->photos()->create(['path' => 'job-photos/a/1.jpg', 'caption' => 'A only']);
        tenancy()->end();

        $tenantB = Tenant::create(['name' => 'B']);
        tenancy()->initialize($tenantB);
        $this->assertSame(0, JobPhoto::count());
        tenancy()->end();
    }
}
