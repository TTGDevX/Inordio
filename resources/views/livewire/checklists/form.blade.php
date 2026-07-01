<?php

use App\Models\ChecklistTemplate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;
    public string $name = '';

    /** @var array<int, string> row labels */
    public array $items = [''];

    public function mount(?string $checklistTemplateId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        if ($checklistTemplateId !== null) {
            $template = ChecklistTemplate::with('items')->findOrFail($checklistTemplateId);
            $this->editingId = $template->id;
            $this->name = $template->name;
            $this->items = $template->items->pluck('label')->all() ?: [''];
        }
    }

    public function addRow(): void
    {
        $this->items[] = '';
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->items = [''];
        }
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'items' => ['array'],
            'items.*' => ['nullable', 'string', 'max:255'],
        ]);

        $labels = array_values(array_filter(array_map('trim', $this->items), fn ($l) => $l !== ''));
        if ($labels === []) {
            $this->addError('items', 'Add at least one checklist item.');

            return;
        }

        $template = $this->editingId
            ? tap(ChecklistTemplate::findOrFail($this->editingId))->update(['name' => $this->name])
            : ChecklistTemplate::create(['name' => $this->name]);

        // Replace the item set (templates are small; simplest correct approach).
        $template->items()->delete();
        foreach ($labels as $position => $label) {
            $template->items()->create(['label' => $label, 'position' => $position]);
        }

        session()->flash('status', $this->editingId ? 'Template updated.' : 'Template created.');

        return $this->redirect(route('checklists.index'), navigate: true);
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('checklists.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to templates</a>

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit template' : 'New template' }}</h1>

            <div>
                <x-input-label for="name" value="Template name" />
                <x-text-input id="name" wire:model="name" class="block mt-1 w-full" placeholder="e.g. Furnace install inspection" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Checklist items" />
                <div class="mt-2 space-y-2">
                    @foreach ($items as $i => $label)
                        <div class="flex items-center gap-2" wire:key="row-{{ $i }}">
                            <span class="text-xs text-gray-400 w-5 text-right">{{ $i + 1 }}.</span>
                            <x-text-input wire:model="items.{{ $i }}" class="block w-full" placeholder="Check / step to perform" />
                            <button type="button" wire:click="removeRow({{ $i }})"
                                class="text-sm text-gray-400 hover:text-red-600">&times;</button>
                        </div>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('items')" class="mt-2" />
                <button type="button" wire:click="addRow" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add item</button>
            </div>

            <div class="pt-2 flex items-center gap-3">
                <x-primary-button>{{ $editingId ? 'Save template' : 'Create template' }}</x-primary-button>
                <a href="{{ route('checklists.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
