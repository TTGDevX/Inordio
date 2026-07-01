<?php

namespace App\Models;

use App\Enums\ChecklistItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['job_checklist_id', 'label', 'status', 'note', 'position'])]
class JobChecklistItem extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'status' => ChecklistItemStatus::class,
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(JobChecklist::class, 'job_checklist_id');
    }

    public function mark(ChecklistItemStatus $status, ?string $note = null): void
    {
        $this->status = $status;
        if ($note !== null) {
            $this->note = $note !== '' ? $note : null;
        }
        $this->save();
    }
}
