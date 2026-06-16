<?php

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Job $job;
    public ?int $assignUserId = null;
    public string $statusMessage = '';

    public function mount(string $jobId): void
    {
        $this->job = Job::with(['customer', 'quote', 'assignedUser', 'lines.item'])->findOrFail($jobId);
        $this->assignUserId = $this->job->assigned_user_id;
    }

    private function reload(): void
    {
        $this->job = Job::with(['customer', 'quote', 'assignedUser', 'lines.item'])->findOrFail($this->job->id);
    }

    public function start(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->job->start();
        $this->reload();
        $this->statusMessage = 'Job started.';
    }

    public function complete(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('work-jobs'), 403);
        $this->job->complete();
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
