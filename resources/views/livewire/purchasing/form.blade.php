<?php

use App\Models\InventoryItem;
use App\Models\ItemSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;
    public ?int $supplier_id = null;
    public string $notes = '';

    /** @var array<int, array{inventory_item_id: ?int, description: string, quantity: string, unit_cost: string}> */
    public array $lines = [];

    public function mount(?string $poId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);

        if ($poId !== null) {
            $po = PurchaseOrder::with('lines')->findOrFail($poId);
            $this->editingId = $po->id;
            $this->supplier_id = $po->supplier_id;
            $this->notes = (string) $po->notes;
            $this->lines = $po->lines->map(fn (PurchaseOrderItem $l) => [
                'inventory_item_id' => $l->inventory_item_id,
                'description' => $l->description,
                'quantity' => (string) $l->quantity,
                'unit_cost' => (string) $l->unit_cost,
            ])->all();
        }

        if ($this->lines === []) {
            $this->lines = [$this->emptyLine()];
        }
    }

    private function emptyLine(): array
    {
        return ['inventory_item_id' => null, 'description' => '', 'quantity' => '1', 'unit_cost' => '0'];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function updated(string $name, $value): void
    {
        if (preg_match('/^lines\.(\d+)\.inventory_item_id$/', $name, $m) && $value) {
            $i = (int) $m[1];
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[$i]['description'] = $item->name;
                // Prefer this supplier's offering cost; fall back to item cost.
                $cost = null;
                if ($this->supplier_id) {
                    $cost = ItemSupplier::where('inventory_item_id', $item->id)
                        ->where('supplier_id', $this->supplier_id)->value('cost');
                }
                $this->lines[$i]['unit_cost'] = (string) ($cost ?? $item->cost);
            }
        }
    }

    public function subtotal(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['quantity'] ?? 0) * (float) ($l['unit_cost'] ?? 0));
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);

        foreach ($this->lines as $i => $line) {
            if (($line['inventory_item_id'] ?? '') === '') {
                $this->lines[$i]['inventory_item_id'] = null;
            }
        }

        $data = $this->validate();

        $po = $this->editingId
            ? tap(PurchaseOrder::findOrFail($this->editingId))->update(['supplier_id' => $data['supplier_id'], 'notes' => $data['notes'] ?? null])
            : PurchaseOrder::create(['supplier_id' => $data['supplier_id'], 'notes' => $data['notes'] ?? null]);

        $po->lines()->delete();
        foreach (array_values($data['lines']) as $i => $line) {
            $po->lines()->create([
                'inventory_item_id' => $line['inventory_item_id'] ?: null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'position' => $i,
            ]);
        }

        return $this->redirect(route('purchasing.show', $po->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => InventoryItem::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('purchasing.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to purchase orders</a>

        <form wire:submit="save" class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
                <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit purchase order' : 'New purchase order' }}</h1>
                <div class="mt-4">
                    <x-input-label for="supplier_id" value="Supplier" />
                    <select id="supplier_id" wire:model="supplier_id"
                        class="block mt-1 w-full sm:w-80 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Select supplier —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Line items</h2>
                    <button type="button" wire:click="addLine" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add line</button>
                </div>
                <x-input-error :messages="$errors->get('lines')" class="mt-1" />

                @foreach ($lines as $i => $line)
                    <div wire:key="poline-{{ $i }}" class="grid grid-cols-12 gap-2 items-start border-t border-gray-100 pt-3">
                        <div class="col-span-12 sm:col-span-4">
                            <select wire:model.live="lines.{{ $i }}.inventory_item_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Custom / no catalogue item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->internal_sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-3">
                            <x-text-input wire:model="lines.{{ $i }}.description" class="block w-full text-sm" placeholder="Description" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.description')" class="mt-1" />
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <x-text-input wire:model.live="lines.{{ $i }}.quantity" type="number" step="0.01" min="0" class="block w-full text-sm" placeholder="Qty" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.quantity')" class="mt-1" />
                        </div>
                        <div class="col-span-5 sm:col-span-2">
                            <x-text-input wire:model.live="lines.{{ $i }}.unit_cost" type="number" step="0.01" min="0" class="block w-full text-sm" placeholder="Cost" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.unit_cost')" class="mt-1" />
                        </div>
                        <div class="col-span-3 sm:col-span-1 flex items-center justify-end pt-1">
                            @if (count($lines) > 1)
                                <button type="button" wire:click="removeLine({{ $i }})" class="text-gray-400 hover:text-red-600 text-sm">Remove</button>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end border-t border-gray-100 pt-3">
                    <div class="text-right">
                        <span class="text-sm text-gray-500">Total</span>
                        <p class="text-lg font-semibold text-gray-900 tabular-nums">${{ number_format($this->subtotal(), 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                <div>
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" wire:model="notes" rows="2"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>{{ $editingId ? 'Save changes' : 'Create PO' }}</x-primary-button>
                    <a href="{{ route('purchasing.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
