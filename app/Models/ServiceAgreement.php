<?php

namespace App\Models;

use App\Enums\Cadence;
use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A recurring/contract maintenance agreement that spawns scheduled jobs on a
 * cadence (the brief's Service Agreements). Line items are copied onto each
 * generated job so recurring visits come pre-populated.
 */
#[Fillable(['customer_id', 'title', 'cadence', 'next_run_at', 'last_run_at', 'is_active', 'notes'])]
class ServiceAgreement extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'cadence' => Cadence::class,
            'next_run_at' => 'date',
            'last_run_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceAgreementItem::class)->orderBy('position');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function isDue(?Carbon $on = null): bool
    {
        $on = $on ?: now();

        return $this->is_active && $this->next_run_at !== null && $this->next_run_at->lte($on);
    }

    /**
     * Spawn the next scheduled job from this agreement (copying the item
     * template), then advance the schedule. Returns the created job.
     */
    public function generateDueJob(): Job
    {
        $scheduledAt = $this->next_run_at;

        $job = Job::create([
            'customer_id' => $this->customer_id,
            'service_agreement_id' => $this->id,
            'title' => $this->title,
            'status' => JobStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);

        foreach ($this->items as $item) {
            $job->lines()->create([
                'inventory_item_id' => $item->inventory_item_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'position' => $item->position,
            ]);
        }

        $this->forceFill([
            'last_run_at' => $scheduledAt,
            'next_run_at' => $this->cadence->advance(Carbon::parse($scheduledAt)),
        ])->save();

        return $job;
    }
}
