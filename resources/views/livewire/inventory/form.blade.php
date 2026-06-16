<?php

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';
    public string $description = '';
    public string $internal_sku = '';
    public string $vendor_sku = '';
    public string $barcode = '';
    public string $unit_of_measure = 'each';
    public string $cost = '0';
    public string $price = '0';
    public ?int $category_id = null;
    public string $new_category = '';
    public ?int $supplier_id = null;
    public string $new_supplier = '';
    public bool $is_serialized = false;

    public function mount(?string $itemId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        if ($itemId !== null) {
            $item = InventoryItem::findOrFail($itemId);
            $this->editingId = $item->id;
            $this->name = $item->name;
            $this->description = (string) $item->description;
            $this->internal_sku = $item->internal_sku;
            $this->vendor_sku = (string) $item->vendor_sku;
            $this->barcode = (string) $item->barcode;
            $this->unit_of_measure = $item->unit_of_measure;
            $this->cost = (string) $item->cost;
            $this->price = (string) $item->price;
            $this->category_id = $item->category_id;
            $this->supplier_id = $item->supplier_id;
            $this->is_serialized = $item->is_serialized;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_sku' => [
                'required', 'string', 'max:255',
                // Unique per tenant (matches the DB unique(tenant_id, internal_sku)).
                Rule::unique('inventory_items', 'internal_sku')
                    ->where(fn ($q) => $q->where('tenant_id', tenant('id')))
                    ->ignore($this->editingId),
            ],
            'vendor_sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'unit_of_measure' => ['required', 'string', 'max:32'],
            'cost' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'new_category' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'new_supplier' => ['nullable', 'string', 'max:255'],
            'is_serialized' => ['boolean'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $data = $this->validate();

        // "Add new" text wins over the select when provided.
        if (trim($this->new_category) !== '') {
            $data['category_id'] = Category::firstOrCreate(['name' => trim($this->new_category)])->id;
        }
        if (trim($this->new_supplier) !== '') {
            $data['supplier_id'] = Supplier::firstOrCreate(['name' => trim($this->new_supplier)])->id;
        }

        unset($data['new_category'], $data['new_supplier']);

        $item = $this->editingId
            ? tap(InventoryItem::findOrFail($this->editingId))->update($data)
            : InventoryItem::create($data);

        session()->flash('status', $this->editingId ? 'Item updated.' : 'Item created.');

        return $this->redirect(route('inventory.show', $item->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('inventory.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to inventory</a>

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit item' : 'New item' }}</h1>

            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" wire:model="name" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" wire:model="description" rows="2"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="internal_sku" value="Internal SKU" />
                    <x-text-input id="internal_sku" wire:model="internal_sku" class="block mt-1 w-full font-mono" />
                    <x-input-error :messages="$errors->get('internal_sku')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="vendor_sku" value="Vendor SKU" />
                    <x-text-input id="vendor_sku" wire:model="vendor_sku" class="block mt-1 w-full font-mono" />
                    <x-input-error :messages="$errors->get('vendor_sku')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="cost" value="Cost" />
                    <x-text-input id="cost" wire:model="cost" type="number" step="0.01" min="0" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('cost')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="price" value="Price" />
                    <x-text-input id="price" wire:model="price" type="number" step="0.01" min="0" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="unit_of_measure" value="Unit" />
                    <x-text-input id="unit_of_measure" wire:model="unit_of_measure" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('unit_of_measure')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="barcode" value="Barcode" />
                <x-text-input id="barcode" wire:model="barcode" class="block mt-1 w-full font-mono" />
                <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="category_id" value="Category" />
                    <select id="category_id" wire:model="category_id"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-text-input wire:model="new_category" class="block mt-2 w-full text-sm" placeholder="…or add a new category" />
                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="supplier_id" value="Supplier" />
                    <select id="supplier_id" wire:model="supplier_id"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-text-input wire:model="new_supplier" class="block mt-2 w-full text-sm" placeholder="…or add a new supplier" />
                    <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                </div>
            </div>

            <label class="inline-flex items-center">
                <input type="checkbox" wire:model="is_serialized"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="ms-2 text-sm text-gray-600">Track as serialized assets (individual units with serial numbers)</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>{{ $editingId ? 'Save changes' : 'Create item' }}</x-primary-button>
                <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
