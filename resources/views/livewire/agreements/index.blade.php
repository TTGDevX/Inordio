<?php

use App\Models\ServiceAgreement;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
    }

    public function toggleActive(int $id): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $agreement = ServiceAgreement::find($id);
        if ($agreement) {
            $agreement->update(['is_active' => ! $agreement->is_active]);
            $this->statusMessage = $agreement->is_active ? 'Agreement resumed.' : 'Agreement paused.';
        }
    }

    public function generateNow(int $id)
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $agreement = ServiceAgreement::with('items')->findOrFail($id);
        $job = $agreement->generateDueJob();

        return $this->redirect(route('jobs.show', $job->id), navigate: true);
    }

    public function delete(int $id): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $agreement = ServiceAgreement::find($id);
        if ($agreement) {
            $agreement->delete();
            $this->statusMessage = 'Agreement deleted. Jobs already generated are kept.';
        }
    }

    public function with(): array
    {
        return [
            'agreements' => ServiceAgreement::with('customer')->withCount('items')->orderBy('next_run_at')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Service agreements</h1>
                <p class="text-sm text-gray-500">Recurring maintenance that spawns scheduled jobs automatically.</p>
            </div>
            <a href="{{ route('agreements.create') }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                New agreement
            </a>
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        @if ($agreements->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">No service agreements yet.</div>
        @else
            <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
                @foreach ($agreements as $agreement)
                    <div wire:key="sa-{{ $agreement->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $agreement->title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $agreement->customer->name }} · {{ $agreement->cadence->label() }} ·
                                next {{ $agreement->next_run_at?->format('M j, Y') }}
                                @if ($agreement->isDue())
                                    <span class="text-amber-700 font-medium">· due now</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $agreement->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $agreement->is_active ? 'Active' : 'Paused' }}
                            </span>
                            <button type="button" wire:click="generateNow({{ $agreement->id }})"
                                class="text-indigo-600 hover:text-indigo-800">Generate now</button>
                            <button type="button" wire:click="toggleActive({{ $agreement->id }})"
                                class="text-gray-500 hover:text-gray-700">{{ $agreement->is_active ? 'Pause' : 'Resume' }}</button>
                            <a href="{{ route('agreements.edit', $agreement->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">Edit</a>
                            <button type="button" wire:click="delete({{ $agreement->id }})"
                                wire:confirm="Delete this agreement? Already-generated jobs are kept."
                                class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
