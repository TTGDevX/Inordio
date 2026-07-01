<?php

namespace Tests\Feature\Jobs;

use App\Enums\ChecklistItemStatus;
use App\Models\ChecklistTemplate;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobChecklist;
use App\Models\JobChecklistItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ChecklistTest extends TestCase
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

    private function template(string $name, array $labels): ChecklistTemplate
    {
        $template = ChecklistTemplate::create(['name' => $name]);
        foreach ($labels as $i => $label) {
            $template->items()->create(['label' => $label, 'position' => $i]);
        }

        return $template;
    }

    private function job(): Job
    {
        return Job::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    }

    public function test_office_can_create_a_template_with_items(): void
    {
        Volt::test('checklists.form')
            ->set('name', 'Furnace install inspection')
            ->set('items', ['Isolate power', 'Pressure-test line', 'Photo of finished work'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('checklists.index'));

        $template = ChecklistTemplate::where('name', 'Furnace install inspection')->withCount('items')->first();
        $this->assertNotNull($template);
        $this->assertSame(3, $template->items_count);
    }

    public function test_blank_template_items_are_rejected(): void
    {
        Volt::test('checklists.form')
            ->set('name', 'Empty')
            ->set('items', ['', '  '])
            ->call('save')
            ->assertHasErrors('items');
    }

    public function test_attaching_a_template_snapshots_its_items(): void
    {
        $template = $this->template('Inspection', ['A', 'B']);
        $job = $this->job();

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->set('attachTemplateId', $template->id)
            ->call('attachChecklist')
            ->assertHasNoErrors();

        $checklist = JobChecklist::where('job_id', $job->id)->first();
        $this->assertNotNull($checklist);
        $this->assertSame(2, $checklist->items()->count());
        $this->assertTrue($checklist->items->every(fn ($i) => $i->status === ChecklistItemStatus::Pending));
    }

    public function test_editing_a_template_does_not_change_a_checklist_already_on_a_job(): void
    {
        $template = $this->template('Inspection', ['A', 'B']);
        $job = $this->job();
        JobChecklist::fromTemplate($job, $template);

        // Later, the template is trimmed to one item.
        $template->items()->delete();
        $template->items()->create(['label' => 'Only this', 'position' => 0]);

        $this->assertSame(2, JobChecklist::where('job_id', $job->id)->first()->items()->count());
    }

    public function test_marking_items_updates_status_and_completion(): void
    {
        $template = $this->template('Inspection', ['A', 'B']);
        $job = $this->job();
        $checklist = JobChecklist::fromTemplate($job, $template);
        [$first, $second] = $checklist->items->all();

        $component = Volt::test('jobs.show', ['jobId' => $job->id]);

        $component->call('markChecklistItem', $first->id, 'pass')->assertHasNoErrors();
        $this->assertSame(ChecklistItemStatus::Pass, $first->fresh()->status);
        $this->assertFalse($checklist->fresh()->isComplete());

        $component->call('markChecklistItem', $second->id, 'fail');
        $checklist->refresh();
        $this->assertTrue($checklist->isComplete());
        $this->assertTrue($checklist->hasFailures());
    }

    public function test_viewer_cannot_open_the_template_manager(): void
    {
        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('checklists.form')->assertForbidden();
    }

    public function test_viewer_cannot_mark_checklist_items(): void
    {
        $template = $this->template('Inspection', ['A']);
        $job = $this->job();
        $checklist = JobChecklist::fromTemplate($job, $template);
        $item = $checklist->items->first();

        $this->actingAs(User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]));

        Volt::test('jobs.show', ['jobId' => $job->id])
            ->call('markChecklistItem', $item->id, 'pass')
            ->assertForbidden();

        $this->assertSame(ChecklistItemStatus::Pending, $item->fresh()->status);
    }

    public function test_checklists_are_tenant_isolated(): void
    {
        $template = $this->template('Inspection', ['A']);
        JobChecklist::fromTemplate($this->job(), $template);
        tenancy()->end();

        $other = Tenant::create(['name' => 'Other']);
        tenancy()->initialize($other);
        $this->assertSame(0, ChecklistTemplate::count());
        $this->assertSame(0, JobChecklist::count());
        $this->assertSame(0, JobChecklistItem::count());
    }
}
