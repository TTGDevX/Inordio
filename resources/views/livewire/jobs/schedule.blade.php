<?php

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // '' = all techs, 'unassigned' = no tech, or a numeric user id.
    #[Url]
    public string $tech = '';

    private function baseQuery()
    {
        return Job::query()
            ->whereIn('status', [JobStatus::Scheduled->value, JobStatus::InProgress->value])
            ->with(['customer', 'assignedUser'])
            ->when($this->tech === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($this->tech !== '' && $this->tech !== 'unassigned', fn ($q) => $q->where('assigned_user_id', (int) $this->tech));
    }

    public function with(): array
    {
        $scheduled = $this->baseQuery()
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn (Job $job) => $job->scheduled_at->format('Y-m-d'));

        $unscheduled = $this->baseQuery()
            ->whereNull('scheduled_at')
            ->latest()
            ->get();

        return [
            'days' => $scheduled,
            'unscheduled' => $unscheduled,
            'technicians' => User::orderBy('name')->get(),
            'today' => now()->format('Y-m-d'),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Schedule</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('jobs.index') }}" wire:navigate
                   class="text-sm text-gray-500 hover:text-gray-700">List view</a>
                <select wire:model.live="tech"
                    class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All technicians</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach ($technicians as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @forelse ($days as $date => $jobs)
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 {{ $date < $today ? 'bg-red-50' : ($date === $today ? 'bg-indigo-50' : 'bg-gray-50') }}">
                    <h2 class="font-medium text-gray-800">
                        {{ \Illuminate\Support\Carbon::parse($date)->format('l, M j, Y') }}
                        @if ($date === $today) <span class="text-xs text-indigo-600">· Today</span>
                        @elseif ($date < $today) <span class="text-xs text-red-600">· Overdue</span>
                        @endif
                    </h2>
                    <span class="text-xs text-gray-500">{{ $jobs->count() }} job{{ $jobs->count() === 1 ? '' : 's' }}</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($jobs as $job)
                        <li wire:key="sch-{{ $job->id }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location='{{ route('jobs.show', $job->id) }}'">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-900">
                                    <span class="tabular-nums text-gray-500">{{ $job->scheduled_at->format('g:i A') }}</span>
                                    · {{ $job->title }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $job->number }}</span> · {{ $job->customer->name }}
                                    · {{ $job->assignedUser?->name ?? 'Unassigned' }}
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">No scheduled jobs.</div>
        @endforelse

        @if ($unscheduled->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
                    <h2 class="font-medium text-gray-800">Needs scheduling <span class="text-xs text-amber-700">· {{ $unscheduled->count() }}</span></h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($unscheduled as $job)
                        <li wire:key="uns-{{ $job->id }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer"
                            onclick="window.location='{{ route('jobs.show', $job->id) }}'">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-900">{{ $job->title }}</p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $job->number }}</span> · {{ $job->customer->name }}
                                    · {{ $job->assignedUser?->name ?? 'Unassigned' }}
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $job->status->badgeClasses() }}">{{ $job->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
