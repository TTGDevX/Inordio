<?php

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Supplier;
use App\Services\StockManager;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public InventoryItem $item;

    // Stock-action form state.
    public string $action = 'receive';
    public ?int $from_location_id = null;
    public ?int $to_location_id = null;
    public string $quantity = '';
    public string $note = '';
    public string $statusMessage = '';

    // Supplier-offering form state.
    public ?int $offerSupplierId = null;
    public string $offerVendorSku = '';
    public string $offerCost = '';

    /**
     * Resolve the item here rather than via implicit route-model binding.
     * By the time the component mounts, the IdentifyTenant middleware has
     * initialized tenancy, so findOrFail runs under the BelongsToTenant global
     * scope and a request for another tenant's id correctly 404s.
     *
     * The route param is {itemId} (not {item}) on purpose — a name matching the
     * $item property would make Livewire route-model-bind it and skip this.
     */
    public function mount(string $itemId): void
    {
        $this->item = InventoryItem::with(['category', 'supplier', 'stockLevels.location', 'supplierOfferings.supplier'])
            ->findOrFail($itemId);
    }

    private function reloadItem(): void
    {
        $this->item = InventoryItem::with(['category', 'supplier', 'stockLevels.location', 'supplierOfferings.supplier'])
            ->findOrFail($this->item->id);
    }

    public function addOffering(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $this->validate([
            'offerSupplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'offerVendorSku' => ['nullable', 'string', 'max:255'],
            'offerCost' => ['required', 'numeric', 'min:0'],
        ]);

        $isFirst = $this->item->supplierOfferings()->count() === 0;

        $this->item->supplierOfferings()->updateOrCreate(
            ['supplier_id' => $this->offerSupplierId],
            ['vendor_sku' => $this->offerVendorSku ?: null, 'cost' => $this->offerCost, 'is_preferred' => $isFirst],
        );

        $this->item->applyPreferredCost();
        $this->reset(['offerSupplierId', 'offerVendorSku', 'offerCost']);
        $this->reloadItem();
        $this->statusMessage = 'Supplier saved.';
    }

    public function setPreferredOffering(int $offeringId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $this->item->supplierOfferings()->update(['is_preferred' => false]);
        $this->item->supplierOfferings()->whereKey($offeringId)->update(['is_preferred' => true]);
        $this->item->applyPreferredCost();
        $this->reloadItem();
        $this->statusMessage = 'Preferred supplier updated.';
    }

    public function removeOffering(int $offeringId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $offering = $this->item->supplierOfferings()->find($offeringId);
        if (! $offering) {
            return;
        }

        $wasPreferred = $offering->is_preferred;
        $offering->delete();

        if ($wasPreferred && ($next = $this->item->supplierOfferings()->orderBy('id')->first())) {
            $next->update(['is_preferred' => true]);
        }

        $this->item->applyPreferredCost();
        $this->reloadItem();
        $this->statusMessage = 'Supplier removed.';
    }

    protected function actionRules(): array
    {
        $rules = [
            'action' => ['required', 'in:receive,transfer,consume'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];

        if (in_array($this->action, ['transfer', 'consume'], true)) {
            $rules['from_location_id'] = ['required', 'integer', 'exists:locations,id'];
        }

        if (in_array($this->action, ['receive', 'transfer'], true)) {
            $rules['to_location_id'] = ['required', 'integer', 'exists:locations,id'];
        }

        if ($this->action === 'transfer') {
            $rules['to_location_id'][] = 'different:from_location_id';
        }

        return $rules;
    }

    public function applyAction(StockManager $stock): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);

        $this->validate($this->actionRules());

        $qty = (float) $this->quantity;
        $note = trim($this->note) !== '' ? trim($this->note) : null;
        $user = auth()->user();

        try {
            match ($this->action) {
                'receive' => $stock->receive($this->item, Location::findOrFail($this->to_location_id), $qty, $user, $note),
                'transfer' => $stock->transfer($this->item, Location::findOrFail($this->from_location_id), Location::findOrFail($this->to_location_id), $qty, $user, $note),
                'consume' => $stock->consume($this->item, Location::findOrFail($this->from_location_id), $qty, $user, $note),
            };
        } catch (InsufficientStockException) {
            $this->addError('quantity', 'Not enough stock at the source location.');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->reset(['quantity', 'note', 'from_location_id', 'to_location_id']);
        $this->item->load('stockLevels.location');
        $this->statusMessage = ucfirst($this->action).' recorded.';
    }

    public function with(): array
    {
        return [
            'levels' => $this->item->stockLevels->sortBy(fn ($l) => $l->location->name),
            'total' => (float) $this->item->stockLevels->sum('quantity'),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('inventory.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to inventory</a>
            @can('manage-inventory')
                <a href="{{ route('inventory.edit', $item->id) }}" wire:navigate
                   class="text-sm text-indigo-600 hover:text-indigo-800">Edit item</a>
            @endcan
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $item->name }}</h1>
                    @if ($item->description)
                        <p class="mt-1 text-sm text-gray-500">{{ $item->description }}</p>
                    @endif
                </div>
                @if ($item->is_serialized)
                    <span class="self-start inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">Serialized</span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4 text-sm">
                <div>
                    <dt class="text-gray-500">Internal SKU</dt>
                    <dd class="font-mono text-gray-900">{{ $item->internal_sku }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Vendor SKU</dt>
                    <dd class="font-mono text-gray-900">{{ $item->vendor_sku ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Cost</dt>
                    <dd class="text-gray-900">${{ number_format((float) $item->cost, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Price</dt>
                    <dd class="text-gray-900">${{ number_format((float) $item->price, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Category</dt>
                    <dd class="text-gray-900">{{ $item->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Supplier</dt>
                    <dd class="text-gray-900">{{ $item->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Unit</dt>
                    <dd class="text-gray-900">{{ $item->unit_of_measure }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Barcode</dt>
                    <dd class="font-mono text-gray-900">{{ $item->barcode ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Suppliers & pricing (one item, many wholesalers). Cost follows the preferred. --}}
        @can('manage-inventory')
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="font-medium text-gray-800">Suppliers &amp; pricing</h2>
                </div>

                @if ($item->supplierOfferings->isEmpty())
                    <p class="px-5 py-4 text-sm text-gray-500">No suppliers linked yet. Add one below.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($item->supplierOfferings->sortByDesc('is_preferred') as $offering)
                            <li wire:key="offer-{{ $offering->id }}" class="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p class="text-gray-900">
                                        {{ $offering->supplier?->name ?? '—' }}
                                        @if ($offering->is_preferred)
                                            <span class="ms-1 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Preferred</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        ${{ number_format((float) $offering->cost, 2) }}
                                        @if ($offering->vendor_sku) · {{ $offering->vendor_sku }} @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    @unless ($offering->is_preferred)
                                        <button type="button" wire:click="setPreferredOffering({{ $offering->id }})"
                                            class="text-indigo-600 hover:text-indigo-800">Make preferred</button>
                                    @endunless
                                    <button type="button" wire:click="removeOffering({{ $offering->id }})"
                                        class="text-gray-400 hover:text-red-600">Remove</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="border-t border-gray-100 px-5 py-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <div>
                            <x-input-label for="offerSupplierId" value="Supplier" />
                            <select id="offerSupplierId" wire:model="offerSupplierId"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Select —</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('offerSupplierId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="offerVendorSku" value="Vendor SKU" />
                            <x-text-input id="offerVendorSku" wire:model="offerVendorSku" class="block mt-1 w-full text-sm font-mono" />
                        </div>
                        <div>
                            <x-input-label for="offerCost" value="Cost" />
                            <x-text-input id="offerCost" wire:model="offerCost" type="number" step="0.01" min="0" class="block mt-1 w-full text-sm" />
                            <x-input-error :messages="$errors->get('offerCost')" class="mt-1" />
                        </div>
                        <div>
                            <x-primary-button wire:click="addOffering" type="button">Add supplier</x-primary-button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <h2 class="font-medium text-gray-800">Stock by location</h2>
                <span class="text-sm text-gray-500 tabular-nums">
                    Total: {{ rtrim(rtrim(number_format($total, 2), '0'), '.') }} {{ $item->unit_of_measure }}
                </span>
            </div>

            @if ($levels->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-500">No stock recorded at any location yet.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($levels as $level)
                        <li wire:key="level-{{ $level->id }}" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-gray-900">{{ $level->location->name }}</p>
                                <p class="text-xs text-gray-500">{{ $level->location->type->label() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-900 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $level->quantity, 2), '0'), '.') }}
                                </p>
                                @if ($level->isLow())
                                    <p class="text-xs font-medium text-red-600">
                                        Low (min {{ rtrim(rtrim(number_format((float) $level->min_quantity, 2), '0'), '.') }})
                                    </p>
                                @elseif ($level->min_quantity !== null)
                                    <p class="text-xs text-gray-400">
                                        min {{ rtrim(rtrim(number_format((float) $level->min_quantity, 2), '0'), '.') }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Stock actions --}}
        @can('move-stock')
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Move stock</h2>

            <form wire:submit="applyAction" class="mt-4 space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['receive' => 'Receive', 'transfer' => 'Transfer', 'consume' => 'Consume'] as $value => $label)
                        <button type="button" wire:click="$set('action', '{{ $value }}')"
                            class="px-3 py-1.5 rounded-full text-sm font-medium border
                                {{ $action === $value ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if (in_array($action, ['transfer', 'consume'], true))
                        <div>
                            <x-input-label for="from_location_id" value="From" />
                            <select id="from_location_id" wire:model="from_location_id"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Select location —</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('from_location_id')" class="mt-2" />
                        </div>
                    @endif

                    @if (in_array($action, ['receive', 'transfer'], true))
                        <div>
                            <x-input-label for="to_location_id" value="To" />
                            <select id="to_location_id" wire:model="to_location_id"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Select location —</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('to_location_id')" class="mt-2" />
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="quantity" value="Quantity" />
                        <x-text-input id="quantity" wire:model="quantity" type="number" step="0.01" min="0" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="note" value="Note (optional)" />
                        <x-text-input id="note" wire:model="note" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('note')" class="mt-2" />
                    </div>
                </div>

                <x-primary-button>Record {{ $action }}</x-primary-button>
            </form>
        </div>
        @endcan
    </div>
</div>
