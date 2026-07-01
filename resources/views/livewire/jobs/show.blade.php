<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\JobStatus;
use App\Enums\PickListStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\ChecklistTemplate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobChecklist;
use App\Models\JobChecklistItem;
use App\Models\PickList;
use App\Models\User;
use App\Models\JobPhoto;
use App\Services\StockManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public Job $job;
    public ?int $assignUserId = null;
    public string $statusMessage = '';

    #[Validate('nullable|image|max:8192')]
    public $photo = null;
    public string $caption = '';

    public string $newNote = '';

    public ?int $attachTemplateId = null;
    /** @var array<int, string> checklistItemId => note */
    public array $checklistNotes = [];

    public ?string $billAmount = null;
    public string $billLabel = 'Deposit';

    public string $timeHours = '';
    public string $timeRate = '';
    public string $timeDescription = '';

    private const EAGER = ['customer', 'quote', 'assignedUser', 'lines.item', 'invoices.lines', 'invoices.payments', 'pickList.items.item', 'pickList.destination', 'photos.uploader', 'noteThread.author', 'checklists.items', 'timeEntries.user'];

    public function mount(string $jobId): void
    {
        $this->job = Job::with(self::EAGER)->findOrFail($jobId);
        $this->assignUserId = $this->job->assigned_user_id;
        $this->timeRate = (string) (\App\Models\CompanySetting::current()->default_labour_rate ?: '');
        $this->syncChecklistNotes();
    }

    private function reload(): void
    {
        $this->job = Job::with(self::EAGER)->findOrFail($this->job->id);
        $this->syncChecklistNotes();
    }

    public function generatePickList()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $pickList = $this->job->pickList ?: PickList::generateFrom($this->job);

        return $this->redirect(route('picklists.show', $pickList->id), navigate: true);
    }

    /**
     * Raise a single full, itemised invoice from the job (the simple flow).
     * Only when nothing has been billed yet; staged billing uses bill()/billRemaining().
     */
    public function createInvoice()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);

        if ($this->job->invoices()->exists()) {
            return null;
        }

        $invoice = Invoice::fromJob($this->job);

        return $this->redirect(route('invoices.show', $invoice->id), navigate: true);
    }

    /**
     * Raise a deposit / progress invoice for a portion of the job. The amount
     * can't exceed the uninvoiced balance.
     */
    public function bill()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);
        $this->validate([
            'billAmount' => ['required', 'numeric', 'min:0.01'],
            'billLabel' => ['required', 'string', 'max:255'],
        ]);

        $amount = \App\Support\Money::round((float) $this->billAmount);
        $remaining = $this->job->amountRemaining();

        if ($amount > $remaining + 0.001) {
            $this->addError('billAmount', 'Amount exceeds the uninvoiced balance ($'.number_format($remaining, 2).').');

            return null;
        }

        $invoice = Invoice::forJobAmount($this->job, $amount, $this->billLabel ?: 'Progress payment');

        return $this->redirect(route('invoices.show', $invoice->id), navigate: true);
    }

    /**
     * Bill everything not yet invoiced as one final invoice.
     */
    public function billRemaining()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);

        $remaining = $this->job->amountRemaining();
        if ($remaining <= 0) {
            return null;
        }

        $invoice = Invoice::forJobAmount($this->job, $remaining, 'Final payment');

        return $this->redirect(route('invoices.show', $invoice->id), navigate: true);
    }

    public function start(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->job->start();
        $this->reload();
        $this->statusMessage = 'Job started.';
    }

    public function complete(StockManager $stock): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);

        $this->job->complete();

        // If parts were picked to a truck, consume them off that truck now so
        // stock reflects what was actually used on the job (brief §5).
        $pickList = $this->job->pickList;
        if ($pickList && $pickList->status === PickListStatus::Completed && $pickList->destination) {
            foreach ($pickList->items as $item) {
                // Consume what was actually picked onto the truck (a short pick
                // moved less than the full need; back-ordered qty never arrived).
                $used = $item->picked_quantity !== null ? (float) $item->picked_quantity : (float) $item->quantity;
                if ($item->picked && $item->item && $used > 0) {
                    try {
                        $stock->consume($item->item, $pickList->destination, $used, auth()->user(), 'Used on '.$this->job->number, $this->job);
                    } catch (InsufficientStockException) {
                        // Truck doesn't hold enough (manual adjustment elsewhere) — skip.
                    }
                }
            }
        }

        $this->reload();
        $this->statusMessage = 'Job completed.';
    }

    public function cancel(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
        $this->job->cancel();
        $this->reload();
        $this->statusMessage = 'Job cancelled.';
    }

    /**
     * Attach a field photo. Techs document their work (work-jobs).
     * Stored under a tenant-prefixed path on the public disk.
     */
    public function addPhoto(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->validate();

        if (! $this->photo) {
            return;
        }

        $path = $this->photo->store('job-photos/'.tenant('id'), 'public');

        $this->job->photos()->create([
            'uploaded_by' => auth()->id(),
            'path' => $path,
            'caption' => $this->caption ?: null,
        ]);

        $this->reset('photo', 'caption');
        $this->reload();
        $this->statusMessage = 'Photo added.';
    }

    public function removePhoto(int $photoId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $photo = $this->job->photos()->whereKey($photoId)->first();
        if ($photo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
            $photo->delete();
            $this->reload();
            $this->statusMessage = 'Photo removed.';
        }
    }

    /**
     * Add a timestamped note to the job thread. Field techs + office (work-jobs).
     */
    public function addNote(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->validate(['newNote' => ['required', 'string', 'max:2000']]);

        $this->job->noteThread()->create([
            'user_id' => auth()->id(),
            'body' => $this->newNote,
        ]);

        $this->reset('newNote');
        $this->reload();
        $this->statusMessage = 'Note added.';
    }

    public function removeNote(int $noteId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $note = $this->job->noteThread()->whereKey($noteId)->first();
        if ($note) {
            $note->delete();
            $this->reload();
            $this->statusMessage = 'Note removed.';
        }
    }

    /** Log billable labour hours on the job (techs — work-jobs). */
    public function addTimeEntry(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->validate([
            'timeHours' => ['required', 'numeric', 'min:0.01'],
            'timeRate' => ['required', 'numeric', 'min:0'],
            'timeDescription' => ['nullable', 'string', 'max:255'],
        ]);

        $this->job->timeEntries()->create([
            'user_id' => auth()->id(),
            'description' => $this->timeDescription ?: null,
            'hours' => $this->timeHours,
            'rate' => $this->timeRate,
            'performed_on' => now()->toDateString(),
        ]);

        $this->reset(['timeHours', 'timeDescription']);
        $this->reload();
        $this->statusMessage = 'Time logged.';
    }

    public function removeTimeEntry(int $entryId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $entry = $this->job->timeEntries()->whereKey($entryId)->first();
        if ($entry) {
            $entry->delete();
            $this->reload();
            $this->statusMessage = 'Time entry removed.';
        }
    }

    private function syncChecklistNotes(): void
    {
        $this->checklistNotes = [];
        foreach ($this->job->checklists as $checklist) {
            foreach ($checklist->items as $item) {
                $this->checklistNotes[$item->id] = (string) $item->note;
            }
        }
    }

    /**
     * Attach a checklist to the job by snapshotting a template (manage-jobs).
     */
    public function attachChecklist(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
        $this->validate(['attachTemplateId' => ['required', 'integer', 'exists:checklist_templates,id']]);

        $template = ChecklistTemplate::with('items')->findOrFail($this->attachTemplateId);
        JobChecklist::fromTemplate($this->job, $template);

        $this->reset('attachTemplateId');
        $this->reload();
        $this->statusMessage = 'Checklist added.';
    }

    /** Techs fill checklists in the field (work-jobs). */
    public function markChecklistItem(int $itemId, string $status): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);

        $item = $this->checklistItem($itemId);
        if ($item) {
            $item->mark(ChecklistItemStatus::from($status), $this->checklistNotes[$itemId] ?? null);
            $this->reload();
        }
    }

    public function saveChecklistNote(int $itemId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);

        $item = $this->checklistItem($itemId);
        if ($item) {
            $item->mark($item->status, $this->checklistNotes[$itemId] ?? null);
            $this->reload();
            $this->statusMessage = 'Note saved.';
        }
    }

    public function removeChecklist(int $checklistId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $checklist = $this->job->checklists()->whereKey($checklistId)->first();
        if ($checklist) {
            $checklist->delete();
            $this->reload();
            $this->statusMessage = 'Checklist removed.';
        }
    }

    /** Resolve a checklist item that belongs to this job (tenant + job scoped). */
    private function checklistItem(int $itemId): ?JobChecklistItem
    {
        return JobChecklistItem::whereKey($itemId)
            ->whereHas('checklist', fn ($q) => $q->where('job_id', $this->job->id))
            ->first();
    }

    public function assign(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
        $this->validate(['assignUserId' => ['nullable', 'integer', 'exists:users,id']]);
        $this->job->update(['assigned_user_id' => $this->assignUserId ?: null]);
        $this->reload();
        $this->statusMessage = 'Technician assigned.';
    }

    public function with(): array
    {
        return [
            'technicians' => User::orderBy('name')->get(),
            'checklistTemplates' => ChecklistTemplate::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('jobs.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to jobs</a>
            @can('manage-jobs')
                @if (in_array($job->status, [JobStatus::Scheduled, JobStatus::InProgress], true))
                    <a href="{{ route('jobs.edit', $job->id) }}" wire:navigate
                       class="text-sm text-indigo-600 hover:text-indigo-800">Edit job</a>
                @endif
            @endcan
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $job->title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        <span class="font-mono">{{ $job->number }}</span>
                        · {{ $job->customer->name }}
                        @if ($job->quote)
                            · from <a href="{{ route('quotes.show', $job->quote->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 font-mono">{{ $job->quote->number }}</a>
                        @endif
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $job->status->badgeClasses() }}">
                    {{ $job->status->label() }}
                </span>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-gray-500">Technician</dt>
                    <dd class="text-gray-900">{{ $job->assignedUser?->name ?? 'Unassigned' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Scheduled</dt>
                    <dd class="text-gray-900">{{ $job->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Total (pre-tax)</dt>
                    <dd class="text-gray-900 tabular-nums">${{ number_format($job->subtotal(), 2) }}</dd>
                </div>
                @can('manage-jobs')
                    <div>
                        <dt class="text-gray-500">Parts cost</dt>
                        <dd class="text-gray-900 tabular-nums">${{ number_format($job->costOfGoods(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Margin</dt>
                        <dd class="tabular-nums font-medium {{ $job->margin() >= 0 ? 'text-green-700' : 'text-red-600' }}">${{ number_format($job->margin(), 2) }}</dd>
                    </div>
                @endcan
            </dl>

            @if ($job->lines->isNotEmpty())
                <div class="mt-4 overflow-hidden border border-gray-100 rounded-md">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2">Item</th>
                                <th class="px-4 py-2 text-right">Qty</th>
                                <th class="px-4 py-2 text-right">Unit</th>
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($job->lines as $line)
                                <tr wire:key="jl-{{ $line->id }}">
                                    <td class="px-4 py-2 text-gray-900">{{ $line->description }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">${{ number_format((float) $line->unit_price, 2) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">${{ number_format($line->lineTotal(), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($job->notes)
                <div class="mt-4 text-sm">
                    <p class="text-gray-500">Notes</p>
                    <p class="text-gray-900 whitespace-pre-line">{{ $job->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Field photos: techs document the work (before/after, what they found) --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Photos</h2>

            @if ($job->photos->isNotEmpty())
                <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($job->photos as $photo)
                        <div class="group relative" wire:key="photo-{{ $photo->id }}">
                            <a href="{{ $photo->url() }}" target="_blank" class="block">
                                <img src="{{ $photo->url() }}" alt="{{ $photo->caption }}"
                                     class="h-32 w-full rounded-md object-cover border border-gray-100">
                            </a>
                            @if ($photo->caption)
                                <p class="mt-1 text-xs text-gray-600 truncate">{{ $photo->caption }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400">{{ $photo->uploader?->name }} · {{ $photo->created_at->format('M j') }}</p>
                            @can('manage-jobs')
                                <button type="button" wire:click="removePhoto({{ $photo->id }})"
                                    wire:confirm="Remove this photo?"
                                    class="absolute top-1 right-1 hidden group-hover:block rounded bg-white/90 px-1.5 py-0.5 text-xs text-red-600 shadow">Remove</button>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">No photos yet.</p>
            @endif

            @can('work-jobs')
                <div class="mt-4 border-t border-gray-100 pt-4 space-y-3">
                    <div>
                        <input type="file" wire:model="photo" accept="image/*" capture="environment"
                            class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700 hover:file:bg-indigo-100">
                        @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="photo" class="mt-1 text-xs text-gray-500">Uploading…</div>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <input type="text" wire:model="caption" placeholder="Caption (optional)"
                            class="block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-secondary-button wire:click="addPhoto" type="button" wire:loading.attr="disabled" wire:target="photo,addPhoto">Add photo</x-secondary-button>
                    </div>
                </div>
            @endcan
        </div>

        {{-- Labour & time: techs log billable hours; they flow onto the invoice --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-medium text-gray-800">Labour &amp; time</h2>
                @if ($job->timeEntries->isNotEmpty())
                    <p class="text-sm text-gray-500 tabular-nums">
                        {{ rtrim(rtrim(number_format($job->loggedHours(), 2), '0'), '.') }} hrs · ${{ number_format($job->labourTotal(), 2) }}
                    </p>
                @endif
            </div>

            @can('work-jobs')
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">Hours</label>
                        <input type="number" step="0.25" min="0" wire:model="timeHours"
                            class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Rate ($/hr)</label>
                        <input type="number" step="0.01" min="0" wire:model="timeRate"
                            class="mt-1 block w-28 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex-1 min-w-[10rem]">
                        <label class="block text-xs text-gray-500">Description (optional)</label>
                        <input type="text" wire:model="timeDescription"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <x-secondary-button wire:click="addTimeEntry" type="button">Log time</x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('timeHours')" class="mt-1" />
                <x-input-error :messages="$errors->get('timeRate')" class="mt-1" />
            @endcan

            @if ($job->timeEntries->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                    @foreach ($job->timeEntries as $entry)
                        <li wire:key="te-{{ $entry->id }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="text-gray-900">{{ $entry->description ?: 'Labour' }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ rtrim(rtrim(number_format((float) $entry->hours, 2), '0'), '.') }} hrs × ${{ number_format((float) $entry->rate, 2) }}
                                    · {{ $entry->user?->name ?? '—' }} · {{ $entry->performed_on?->format('M j') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="tabular-nums text-gray-900">${{ number_format($entry->amount(), 2) }}</span>
                                @can('manage-jobs')
                                    <button type="button" wire:click="removeTimeEntry({{ $entry->id }})" wire:confirm="Remove this time entry?"
                                        class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-gray-500">No time logged.</p>
            @endif
        </div>

        {{-- Notes & updates: a timestamped thread from the field/office --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Notes &amp; updates</h2>

            @can('work-jobs')
                <div class="mt-3 flex flex-col gap-2">
                    <textarea wire:model="newNote" rows="2" placeholder="Add an update…"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('newNote') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div>
                        <x-secondary-button wire:click="addNote" type="button">Add note</x-secondary-button>
                    </div>
                </div>
            @endcan

            @if ($job->noteThread->isNotEmpty())
                <ul class="mt-4 space-y-3">
                    @foreach ($job->noteThread as $note)
                        <li class="flex items-start justify-between gap-3 border-t border-gray-100 pt-3" wire:key="note-{{ $note->id }}">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-900 whitespace-pre-line">{{ $note->body }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $note->author?->name ?? 'System' }} · {{ $note->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            @can('manage-jobs')
                                <button type="button" wire:click="removeNote({{ $note->id }})" wire:confirm="Remove this note?"
                                    class="shrink-0 text-xs text-red-600 hover:text-red-800">Remove</button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-gray-500">No notes yet.</p>
            @endif
        </div>

        {{-- Checklists & inspections --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Checklists &amp; inspections</h2>

            @can('manage-jobs')
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <select wire:model="attachTemplateId"
                        class="block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Choose a template —</option>
                        @foreach ($checklistTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button wire:click="attachChecklist" type="button">Add checklist</x-secondary-button>
                    @if ($checklistTemplates->isEmpty())
                        <a href="{{ route('checklists.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">Create a template first</a>
                    @endif
                </div>
                <x-input-error :messages="$errors->get('attachTemplateId')" class="mt-1" />
            @endcan

            @forelse ($job->checklists as $checklist)
                <div class="mt-4 border-t border-gray-100 pt-4" wire:key="cl-{{ $checklist->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-gray-900">{{ $checklist->name }}</p>
                            <span class="text-xs text-gray-500">{{ $checklist->answeredCount() }}/{{ $checklist->items->count() }}</span>
                            @if ($checklist->isComplete())
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Complete</span>
                            @endif
                            @if ($checklist->hasFailures())
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Has failures</span>
                            @endif
                        </div>
                        @can('manage-jobs')
                            <button type="button" wire:click="removeChecklist({{ $checklist->id }})"
                                wire:confirm="Remove this checklist from the job?"
                                class="text-xs text-red-600 hover:text-red-800">Remove</button>
                        @endcan
                    </div>

                    <ul class="mt-3 space-y-3">
                        @foreach ($checklist->items as $item)
                            <li wire:key="cli-{{ $item->id }}" class="rounded-md border border-gray-100 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-gray-900">{{ $item->label }}</p>
                                    <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status->badgeClasses() }}">{{ $item->status->label() }}</span>
                                </div>
                                @can('work-jobs')
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <button type="button" wire:click="markChecklistItem({{ $item->id }}, 'pass')"
                                            class="rounded px-2 py-1 text-xs font-medium {{ $item->status === \App\Enums\ChecklistItemStatus::Pass ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Pass</button>
                                        <button type="button" wire:click="markChecklistItem({{ $item->id }}, 'fail')"
                                            class="rounded px-2 py-1 text-xs font-medium {{ $item->status === \App\Enums\ChecklistItemStatus::Fail ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Fail</button>
                                        <button type="button" wire:click="markChecklistItem({{ $item->id }}, 'na')"
                                            class="rounded px-2 py-1 text-xs font-medium {{ $item->status === \App\Enums\ChecklistItemStatus::Na ? 'bg-gray-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">N/A</button>
                                        <input type="text" wire:model="checklistNotes.{{ $item->id }}" placeholder="Note (optional)"
                                            class="flex-1 min-w-[10rem] rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                        <button type="button" wire:click="saveChecklistNote({{ $item->id }})"
                                            class="text-xs text-indigo-600 hover:text-indigo-800">Save</button>
                                    </div>
                                @else
                                    @if ($item->note)
                                        <p class="mt-1 text-xs text-gray-500">{{ $item->note }}</p>
                                    @endif
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="mt-3 text-sm text-gray-500">No checklists on this job.</p>
            @endforelse
        </div>

        {{-- Status actions: techs work the job; office cancels. --}}
        @if (in_array($job->status, [JobStatus::Scheduled, JobStatus::InProgress], true))
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
                @can('work-jobs')
                    @if ($job->status === JobStatus::Scheduled)
                        <x-primary-button wire:click="start" type="button">Start job</x-primary-button>
                    @elseif ($job->status === JobStatus::InProgress)
                        <x-primary-button wire:click="complete" type="button">Mark complete</x-primary-button>
                    @endif
                @endcan
                @can('manage-jobs')
                    <x-danger-button wire:click="cancel" type="button">Cancel job</x-danger-button>
                @endcan
            </div>
        @endif

        {{-- Pick list: pull parts from the warehouse to the truck --}}
        @php($catalogueLines = $job->lines->whereNotNull('inventory_item_id'))
        @if ($job->pickList || $catalogueLines->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex items-center gap-3">
                <span class="font-medium text-gray-800">Pick list</span>
                @if ($job->pickList)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $job->pickList->status->badgeClasses() }}">
                        {{ $job->pickList->status->label() }}
                    </span>
                    <a href="{{ route('picklists.show', $job->pickList->id) }}" wire:navigate
                       class="text-sm text-indigo-600 hover:text-indigo-800">Open</a>
                @else
                    @can('manage-jobs')
                        <x-secondary-button wire:click="generatePickList" type="button">Generate pick list</x-secondary-button>
                    @endcan
                @endif
            </div>
        @endif

        {{-- Invoicing (supports deposit / progress / final staged billing) --}}
        @if ($job->status === JobStatus::Done || $job->invoices->isNotEmpty())
            @php($invoiced = $job->amountInvoiced())
            @php($remaining = $job->amountRemaining())
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Invoicing</h2>
                    <p class="text-sm text-gray-500 tabular-nums">
                        Billed ${{ number_format($invoiced, 2) }} of ${{ number_format($job->subtotal(), 2) }}
                        · <span class="{{ $remaining > 0 ? 'text-amber-700' : 'text-green-700' }}">${{ number_format(max(0, $remaining), 2) }} left</span>
                    </p>
                </div>

                @if ($job->invoices->isNotEmpty())
                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-md">
                        @foreach ($job->invoices as $inv)
                            <li wire:key="inv-{{ $inv->id }}" class="flex items-center justify-between px-4 py-2 text-sm">
                                <a href="{{ route('invoices.show', $inv->id) }}" wire:navigate class="font-mono text-indigo-600 hover:text-indigo-800">{{ $inv->number }}</a>
                                <span class="text-gray-500">{{ $inv->lines->first()?->description }}</span>
                                <span class="tabular-nums text-gray-900">${{ number_format($inv->total(), 2) }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $inv->status->badgeClasses() }}">{{ $inv->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('manage-invoices')
                    @if ($remaining > 0)
                        <div class="flex flex-wrap items-end gap-3">
                            @if ($invoiced == 0)
                                <x-primary-button wire:click="createInvoice" type="button">Create full invoice</x-primary-button>
                                <span class="text-xs text-gray-400 self-center">or bill in stages:</span>
                            @endif
                            <div>
                                <label class="block text-xs text-gray-500">Label</label>
                                <input type="text" wire:model="billLabel"
                                    class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Amount (pre-tax)</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $remaining }}" wire:model="billAmount"
                                    placeholder="{{ number_format($remaining, 2, '.', '') }}"
                                    class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <x-secondary-button wire:click="bill" type="button">Bill amount</x-secondary-button>
                            <x-secondary-button wire:click="billRemaining" type="button">Bill remaining</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('billAmount')" class="mt-1" />
                        <x-input-error :messages="$errors->get('billLabel')" class="mt-1" />
                    @else
                        <p class="text-sm text-green-700">Fully invoiced.</p>
                    @endif
                @endcan
            </div>
        @endif

        {{-- Assign technician --}}
        @can('manage-jobs')
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
                <h2 class="font-medium text-gray-800">Assign technician</h2>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div>
                        <select wire:model="assignUserId"
                            class="block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Unassigned —</option>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button wire:click="assign" type="button">Save</x-secondary-button>
                </div>
            </div>
        @endcan
    </div>
</div>
