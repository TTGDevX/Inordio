<?php

use App\Enums\JobStatus;
use App\Enums\PickListStatus;
use App\Models\Job;
use App\Models\PickList;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'jobs' => Job::whereIn('status', [JobStatus::Scheduled->value, JobStatus::InProgress->value])
                ->with(['customer', 'assignedUser'])
                ->orderBy('scheduled_at')
                ->take(12)->get(),
            'pickLists' => PickList::where('status', PickListStatus::Open->value)
                ->with(['job.customer', 'items', 'destination'])
                ->latest()->take(8)->get(),
        ];
    }
}; ?>

<div wire:poll.30s class="py-6 sm:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Ops board</h1>
            <span class="text-sm text-gray-400">Auto-refreshing · {{ now()->format('g:i A') }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Active jobs --}}
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Active jobs</h2>
                    <span class="text-sm text-gray-400">{{ $jobs->count() }}</span>
                </div>
                @forelse ($jobs as $job)
                    <div wire:key="bj-{{ $job->id }}" class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-50">
                        <div class="min-w-0">
                            <p class="text-lg text-gray-900 truncate">{{ $job->title }}</p>
                            <p class="text-sm text-gray-500">
                                <span class="font-mono">{{ $job->number }}</span> · {{ $job->customer->name }}
                                · {{ $job->assignedUser?->name ?? 'Unassigned' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-medium {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                            <p class="mt-1 text-xs text-gray-400">{{ $job->scheduled_at?->format('M j · g:i A') ?? 'unscheduled' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-gray-400">No active jobs.</p>
                @endforelse
            </div>

            {{-- Picking queue --}}
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Picking queue</h2>
                    <span class="text-sm text-gray-400">{{ $pickLists->count() }}</span>
                </div>
                @forelse ($pickLists as $pl)
                    @php($remaining = $pl->items->where('picked', false)->count())
                    <div wire:key="bp-{{ $pl->id }}" class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-50">
                        <div class="min-w-0">
                            <p class="text-lg text-gray-900">
                                <span class="font-mono">{{ $pl->job->number }}</span> · {{ $pl->job->customer->name }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $remaining }} of {{ $pl->items->count() }} line{{ $pl->items->count() === 1 ? '' : 's' }} to pick
                                @if ($pl->destination) · to {{ $pl->destination->name }} @endif
                            </p>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-sm font-medium {{ $pl->status->badgeClasses() }}">{{ $pl->status->label() }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-gray-400">Nothing waiting to be picked.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
