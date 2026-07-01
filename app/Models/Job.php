<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['customer_id', 'quote_id', 'service_agreement_id', 'assigned_user_id', 'number', 'title', 'status', 'scheduled_at', 'notes'])]
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

    public function serviceAgreement(): BelongsTo
    {
        return $this->belongsTo(ServiceAgreement::class);
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
     * The first invoice raised from this job (kept for the simple single-invoice
     * flow). A job may have several when staged/progress-billed — see invoices().
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * All invoices raised from this job (deposit / progress / final billing).
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    /**
     * Pre-tax amount already billed across non-void invoices for this job.
     */
    public function amountInvoiced(): float
    {
        return \App\Support\Money::sum(
            $this->invoices
                ->reject(fn (Invoice $i) => $i->status === InvoiceStatus::Void)
                ->map(fn (Invoice $i) => $i->subtotal())
        );
    }

    /**
     * Pre-tax amount of the job not yet billed (job subtotal − already billed).
     */
    public function amountRemaining(): float
    {
        return \App\Support\Money::round($this->subtotal() - $this->amountInvoiced());
    }

    /**
     * The pick list generated for this job, if any (one per job).
     */
    public function pickList(): HasOne
    {
        return $this->hasOne(PickList::class);
    }

    /**
     * Field photos documenting the work (newest first).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(JobPhoto::class)->latest();
    }

    /**
     * Timestamped note thread (updates from the field/office). Named
     * noteThread() to avoid colliding with the single `notes` text column.
     */
    public function noteThread(): HasMany
    {
        return $this->hasMany(JobNote::class)->latest();
    }

    /**
     * Inspection/QA checklists attached to this job.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(JobChecklist::class)->latest();
    }

    public function subtotal(): float
    {
        return \App\Support\Money::sum($this->lines->map(fn (JobLineItem $line) => $line->lineTotal()));
    }

    /**
     * Cost of parts consumed on this job (valued at average cost when used).
     */
    public function costOfGoods(): float
    {
        return \App\Support\Money::sum(
            StockMovement::where('job_id', $this->id)
                ->where('type', \App\Enums\StockMovementType::Usage)
                ->get()
                ->map(fn (StockMovement $m) => \App\Support\Money::round((float) $m->quantity * (float) $m->unit_cost))
        );
    }

    /**
     * Gross margin: job revenue (pre-tax) minus cost of goods used.
     */
    public function margin(): float
    {
        return \App\Support\Money::round($this->subtotal() - $this->costOfGoods());
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
