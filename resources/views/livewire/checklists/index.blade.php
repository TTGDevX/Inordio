<?php

use App\Models\ChecklistTemplate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
    }

    public function delete(int $templateId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $template = ChecklistTemplate::find($templateId);
        if ($template) {
            $template->delete();
            $this->statusMessage = 'Template deleted.';
        }
    }

    public function with(): array
    {
        return ['templates' => ChecklistTemplate::withCount('items')->orderBy('name')->get()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">Checklist templates</h1>
                <p class="text-sm text-gray-500">Reusable inspections crews fill out on a job.</p>
            </div>
            <a href="{{ route('checklists.create') }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                New template
            </a>
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        @if ($templates->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">No templates yet.</div>
        @else
            <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
                @foreach ($templates as $template)
                    <div wire:key="tpl-{{ $template->id }}" class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ $template->name }}</p>
                            <p class="text-xs text-gray-500">{{ $template->items_count }} item{{ $template->items_count === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('checklists.edit', $template->id) }}" wire:navigate
                               class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                            <button type="button" wire:click="delete({{ $template->id }})"
                                wire:confirm="Delete this template? Checklists already on jobs keep their copy."
                                class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
