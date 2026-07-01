<?php

use App\Enums\JobStatus;
use App\Enums\PickListStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Invoice;
use App\Models\Job;
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

    private const EAGER = ['customer', 'quote', 'assignedUser', 'lines.item', 'invoice', 'pickList.items.item', 'pickList.destination', 'photos.uploader', 'noteThread.author'];

    public function mount(string $jobId): void
    {
        $this->job = Job::with(self::EAGER)->findOrFail($jobId);
        $this->assignUserId = $this->job->assigned_user_id;
    }

    private function reload(): void
    {
        $this->job = Job::with(self::EAGER)->findOrFail($this->job->id);
    }

    public function generatePickList()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $pickList = $this->job->pickList ?: PickList::generateFrom($this->job);

        return $this->redirect(route('picklists.show', $pickList->id), navigate: true);
    }

    /**
     * Raise an invoice from this job (idempotent — one invoice per job).
     */
    public function createInvoice()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);

        $invoice = $this->job->invoice ?: Invoice::fromJob($this->job);

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
                if ($item->picked && $item->item) {
                    try {
                        $stock->consume($item->item, $pickList->destination, (float) $item->quantity, auth()->user(), 'Used on '.$this->job->number, $this->job);
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
        return ['technicians' => User::orderBy('name')->get()];
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

        {{-- Invoicing --}}
        @if ($job->status === JobStatus::Done || $job->invoice)
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex items-center gap-3">
                @if ($job->invoice)
                    <span class="text-sm text-gray-600">Invoiced</span>
                    <a href="{{ route('invoices.show', $job->invoice->id) }}" wire:navigate
                       class="text-sm font-mono text-indigo-600 hover:text-indigo-800">{{ $job->invoice->number }}</a>
                @else
                    @can('manage-invoices')
                        <x-primary-button wire:click="createInvoice" type="button">Create invoice</x-primary-button>
                    @endcan
                @endif
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
