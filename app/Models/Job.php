<?php

namespace App\Models;

use App\Enums\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['customer_id', 'quote_id', 'assigned_user_id', 'number', 'title', 'status', 'scheduled_at', 'notes'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use BelongsToTenant, HasFactory;
    use \App\Models\Concerns\Auditable;

    // The queue uses the reserved "jobs" table; the domain model lives elsewhere.
    protected $table = 'service_jobs';

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Job $job) {
            if (! $job->number) {
                $job->forceFill([
                    'number' => 'J-'.str_pad((string) $job->id, 5, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JobLineItem::class)->orderBy('position');
    }

    /**
     * The invoice raised from this job, if any (one per job for now).
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The pick list generated for this job, if any (one per job).
     */
    public function pickList(): HasOne
    {
        return $this->hasOne(PickList::class);
    }

    public function subtotal(): float
    {
        return \App\Support\Money::sum($this->lines->map(fn (JobLineItem $line) => $line->lineTotal()));
    }

    /**
     * Build a scheduled job from an approved (or any) quote, copying its lines.
     */
    public static function fromQuote(Quote $quote): self
    {
        $job = static::create([
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'title' => 'Job for '.$quote->number,
            'status' => JobStatus::Scheduled,
        ]);

        foreach ($quote->lines as $line) {
            $job->lines()->create([
                'inventory_item_id' => $line->inventory_item_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'position' => $line->position,
            ]);
        }

        return $job;
    }

    // Status transitions — *_at columns are set directly (not mass-assignable).
    public function start(): void
    {
        $this->status = JobStatus::InProgress;
        $this->started_at = now();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = JobStatus::Done;
        $this->completed_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = JobStatus::Cancelled;
        $this->save();
    }
}
