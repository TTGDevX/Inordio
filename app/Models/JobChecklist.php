<?php

namespace App\Models;

use App\Enums\ChecklistItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['job_id', 'checklist_template_id', 'name'])]
class JobChecklist extends Model
{
    use BelongsToTenant;

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobChecklistItem::class)->orderBy('position');
    }

    /**
     * Build a job checklist by snapshotting a template's items (so later edits
     * to the template don't change a checklist already filled out on a job).
     */
    public static function fromTemplate(Job $job, ChecklistTemplate $template): self
    {
        $checklist = static::create([
            'job_id' => $job->id,
            'checklist_template_id' => $template->id,
            'name' => $template->name,
        ]);

        foreach ($template->items as $item) {
            $checklist->items()->create([
                'label' => $item->label,
                'status' => ChecklistItemStatus::Pending,
                'position' => $item->position,
            ]);
        }

        return $checklist;
    }

    public function answeredCount(): int
    {
        return $this->items->reject(fn (JobChecklistItem $i) => $i->status === ChecklistItemStatus::Pending)->count();
    }

    public function isComplete(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (JobChecklistItem $i) => $i->status !== ChecklistItemStatus::Pending);
    }

    public function hasFailures(): bool
    {
        return $this->items->contains(fn (JobChecklistItem $i) => $i->status === ChecklistItemStatus::Fail);
    }
}
