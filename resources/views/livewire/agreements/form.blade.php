<?php

use App\Enums\Cadence;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\ServiceAgreement;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public ?int $customer_id = null;
    public string $title = '';
    public string $cadence = 'quarterly';
    public string $next_run_at = '';
    public bool $is_active = true;

    /** @var array<int, array{inventory_item_id:?int, description:string, quantity:string, unit_price:string}> */
    public array $items = [];

    public function mount(?string $serviceAgreementId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $this->next_run_at = now()->toDateString();

        if ($serviceAgreementId !== null) {
            $agreement = ServiceAgreement::with('items')->findOrFail($serviceAgreementId);
            $this->editingId = $agreement->id;
            $this->customer_id = $agreement->customer_id;
            $this->title = $agreement->title;
            $this->cadence = $agreement->cadence->value;
            $this->next_run_at = $agreement->next_run_at->toDateString();
            $this->is_active = $agreement->is_active;
            $this->items = $agreement->items->map(fn ($i) => [
                'inventory_item_id' => $i->inventory_item_id,
                'description' => $i->description,
                'quantity' => (string) (float) $i->quantity,
                'unit_price' => (string) (float) $i->unit_price,
            ])->all();
        }

        if ($this->items === []) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->items[] = ['inventory_item_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'cadence' => ['required', 'string', 'in:'.implode(',', array_map(fn (Cadence $c) => $c->value, Cadence::cases()))],
            'next_run_at' => ['required', 'date'],
            'is_active' => ['boolean'],
            'items' => ['array'],
            'items.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);
        $this->validate();

        $rows = array_values(array_filter($this->items, fn ($r) => trim((string) ($r['description'] ?? '')) !== ''));

        $agreement = $this->editingId
            ? tap(ServiceAgreement::findOrFail($this->editingId))->update([
                'customer_id' => $this->customer_id,
                'title' => $this->title,
                'cadence' => $this->cadence,
                'next_run_at' => $this->next_run_at,
                'is_active' => $this->is_active,
            ])
            : ServiceAgreement::create([
                'customer_id' => $this->customer_id,
                'title' => $this->title,
                'cadence' => $this->cadence,
                'next_run_at' => $this->next_run_at,
                'is_active' => $this->is_active,
            ]);

        $agreement->items()->delete();
        foreach ($rows as $position => $row) {
            $agreement->items()->create([
                'inventory_item_id' => $row['inventory_item_id'] ?: null,
                'description' => $row['description'],
                'quantity' => (float) ($row['quantity'] ?: 1),
                'unit_price' => (float) ($row['unit_price'] ?: 0),
                'position' => $position,
            ]);
        }

        session()->flash('status', $this->editingId ? 'Agreement updated.' : 'Agreement created.');

        return $this->redirect(route('agreements.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'catalogue' => InventoryItem::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cadences' => Cadence::cases(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('agreements.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to agreements</a>

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit agreement' : 'New agreement' }}</h1>

            <div>
                <x-input-label for="customer_id" value="Customer" />
                <select id="customer_id" wire:model="customer_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Select customer —</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" wire:model="title" class="block mt-1 w-full" placeholder="e.g. Quarterly HVAC service" />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="cadence" value="Frequency" />
                    <select id="cadence" wire:model="cadence"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($cadences as $c)
                            <option value="{{ $c->value }}">{{ $c->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="next_run_at" value="Next visit" />
                    <x-text-input id="next_run_at" type="date" wire:model="next_run_at" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('next_run_at')" class="mt-2" />
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Active
                    </label>
                </div>
            </div>

            <div>
                <x-input-label value="Visit line items (copied onto each generated job)" />
                <div class="mt-2 space-y-2">
                    @foreach ($items as $i => $row)
                        <div class="flex flex-wrap items-center gap-2" wire:key="ai-{{ $i }}">
                            <select wire:model="items.{{ $i }}.inventory_item_id"
                                class="rounded-md border-gray-300 shadow-sm text-sm w-40 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Custom</option>
                                @foreach ($catalogue as $it)
                                    <option value="{{ $it->id }}">{{ $it->name }}</option>
                                @endforeach
                            </select>
                            <x-text-input wire:model="items.{{ $i }}.description" class="flex-1 min-w-[8rem]" placeholder="Description" />
                            <x-text-input type="number" step="0.01" min="0" wire:model="items.{{ $i }}.quantity" class="w-20" placeholder="Qty" />
                            <x-text-input type="number" step="0.01" min="0" wire:model="items.{{ $i }}.unit_price" class="w-24" placeholder="Price" />
                            <button type="button" wire:click="removeRow({{ $i }})" class="text-gray-400 hover:text-red-600">&times;</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addRow" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add line</button>
            </div>

            <div class="pt-2 flex items-center gap-3">
                <x-primary-button>{{ $editingId ? 'Save agreement' : 'Create agreement' }}</x-primary-button>
                <a href="{{ route('agreements.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
