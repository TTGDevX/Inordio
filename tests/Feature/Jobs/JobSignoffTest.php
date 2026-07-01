<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class JobSignoffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    // A valid 1×1 transparent PNG as a data URL.
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
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

    public function test_technician_can_capture_a_sign_off(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('signerName', 'Jane Homeowner')
            ->call('saveSignature', self::PNG)
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertNotNull($job->signature_path);
        $this->assertSame('Jane Homeowner', $job->signed_by_name);
        $this->assertNotNull($job->signed_at);
        Storage::disk('public')->assertExists($job->signature_path);
    }

    public function test_signer_name_is_required(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('signerName', '')
            ->call('saveSignature', self::PNG)
            ->assertHasErrors('signerName');
    }

    public function test_invalid_signature_data_is_rejected(): void
    {
        $this->actingAs(User::factory()->technician()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('signerName', 'Jane')
            ->call('saveSignature', 'not-a-data-url')
            ->assertHasErrors('signature');

        $this->assertNull($job->fresh()->signature_path);
    }

    public function test_office_can_clear_a_sign_off(): void
    {
        $this->actingAs(User::factory()->office()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();
        $path = 'signatures/'.$this->tenant->id.'/sig.png';
        Storage::disk('public')->put($path, 'binary');
        $job->forceFill(['signature_path' => $path, 'signed_by_name' => 'Jane', 'signed_at' => now()])->save();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('clearSignature')
            ->assertHasNoErrors();

        $this->assertNull($job->fresh()->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_viewer_cannot_capture_a_sign_off(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('signerName', 'Jane')
            ->call('saveSignature', self::PNG)
            ->assertForbidden();

        $this->assertNull($job->fresh()->signature_path);
    }
}
