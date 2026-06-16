<?php

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public ?int $customer_id = null;
    public string $valid_until = '';
    public string $notes = '';

    /** @var array<int, array{inventory_item_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public function mount(?string $quoteId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);

        if ($quoteId !== null) {
            $quote = Quote::with('lines')->findOrFail($quoteId);
            $this->editingId = $quote->id;
            $this->customer_id = $quote->customer_id;
            $this->valid_until = optional($quote->valid_until)->toDateString() ?? '';
            $this->notes = (string) $quote->notes;
            $this->lines = $quote->lines->map(fn (QuoteLineItem $l) => [
                'inventory_item_id' => $l->inventory_item_id,
                'description' => $l->description,
                'quantity' => (string) $l->quantity,
                'unit_price' => (string) $l->unit_price,
            ])->all();
        }

        if ($this->lines === []) {
            $this->lines = [$this->emptyLine()];
            $this->valid_until = now()->addDays(30)->toDateString();
        }
    }

    private function emptyLine(): array
    {
        return ['inventory_item_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
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

    /**
     * When a line's catalogue item changes, snapshot its name + price onto the line.
     */
    public function updated(string $name, $value): void
    {
        if (preg_match('/^lines\.(\d+)\.inventory_item_id$/', $name, $m) && $value) {
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[(int) $m[1]]['description'] = $item->name;
                $this->lines[(int) $m[1]]['unit_price'] = (string) $item->price;
            }
        }
    }

    public function subtotal(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['quantity'] ?? 0) * (float) ($l['unit_price'] ?? 0));
    }

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);

        // A "custom" line has an empty <select> value; coerce it to null so the
        // nullable rule applies (an empty string would fail the integer rule).
        foreach ($this->lines as $i => $line) {
            if (($line['inventory_item_id'] ?? '') === '') {
                $this->lines[$i]['inventory_item_id'] = null;
            }
        }

        $data = $this->validate();

        if ($this->editingId) {
            $quote = Quote::findOrFail($this->editingId);
            $quote->update([
                'customer_id' => $data['customer_id'],
                'valid_until' => $data['valid_until'] ?: null,
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $quote = Quote::create([
                'customer_id' => $data['customer_id'],
                'valid_until' => $data['valid_until'] ?: null,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        // Replace the line set (simplest correct approach for an editable quote).
        $quote->lines()->delete();
        foreach (array_values($data['lines']) as $i => $line) {
            $quote->lines()->create([
                'inventory_item_id' => $line['inventory_item_id'] ?: null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'position' => $i,
            ]);
        }

        session()->flash('status', $this->editingId ? 'Quote updated.' : 'Quote created.');

        return $this->redirect(route('quotes.show', $quote->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('quotes.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to quotes</a>

        <form wire:submit="save" class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit quote' : 'New quote' }}</h1>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="customer_id" value="Customer" />
                        <select id="customer_id" wire:model="customer_id"
                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select customer —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="valid_until" value="Valid until" />
                        <x-text-input id="valid_until" wire:model="valid_until" type="date" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('valid_until')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Line items</h2>
                    <button type="button" wire:click="addLine"
                        class="text-sm text-indigo-600 hover:text-indigo-800">+ Add line</button>
                </div>

                <x-input-error :messages="$errors->get('lines')" class="mt-1" />

                @foreach ($lines as $i => $line)
                    <div wire:key="line-{{ $i }}" class="grid grid-cols-12 gap-2 items-start border-t border-gray-100 pt-3">
                        <div class="col-span-12 sm:col-span-4">
                            <select wire:model.live="lines.{{ $i }}.inventory_item_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Custom / no catalogue item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->internal_sku }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('lines.'.$i.'.inventory_item_id')" class="mt-1" />
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
                            <x-text-input wire:model.live="lines.{{ $i }}.unit_price" type="number" step="0.01" min="0" class="block w-full text-sm" placeholder="Price" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.unit_price')" class="mt-1" />
                        </div>
                        <div class="col-span-3 sm:col-span-1 flex items-center justify-end pt-1">
                            @if (count($lines) > 1)
                                <button type="button" wire:click="removeLine({{ $i }})"
                                    class="text-gray-400 hover:text-red-600 text-sm">Remove</button>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end border-t border-gray-100 pt-3">
                    <div class="text-right">
                        <span class="text-sm text-gray-500">Subtotal (pre-tax)</span>
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
                    <x-primary-button>{{ $editingId ? 'Save changes' : 'Create quote' }}</x-primary-button>
                    <a href="{{ route('quotes.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
