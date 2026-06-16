<?php

use App\Models\Job;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $jobs = Job::query()
            ->with(['customer', 'assignedUser'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('number', 'like', $term)
                        ->orWhere('title', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15);

        return ['jobs' => $jobs];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Jobs</h1>
            @can('manage-jobs')
                <a href="{{ route('jobs.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    New job
                </a>
            @endcan
        </div>

        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search"
                placeholder="Search by number, title or customer"
                class="w-full sm:w-96 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                autocomplete="off" />
        </div>

        @if ($jobs->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                @if ($search !== '')
                    No jobs match “{{ $search }}”.
                @else
                    No jobs yet.
                @endif
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Job</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Tech</th>
                            <th class="px-6 py-3">Scheduled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($jobs as $job)
                            <tr wire:key="job-{{ $job->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('jobs.show', $job->id) }}'">
                                <td class="px-6 py-3">
                                    <div class="font-mono text-sm text-gray-900">{{ $job->number }}</div>
                                    <div class="text-sm text-gray-500">{{ $job->title }}</div>
                                </td>
                                <td class="px-6 py-3 text-gray-900">{{ $job->customer->name }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $job->status->badgeClasses() }}">
                                        {{ $job->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $job->assignedUser?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $job->scheduled_at?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $jobs->links() }}</div>
        @endif
    </div>
</div>
