<?php

use App\Models\InventoryItem;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public InventoryItem $item;

    /**
     * Resolve the item here rather than via implicit route-model binding.
     * By the time the component mounts, the IdentifyTenant middleware has
     * initialized tenancy, so findOrFail runs under the BelongsToTenant global
     * scope and a request for another tenant's id correctly 404s.
     */
    public function mount(string $item): void
    {
        $this->item = InventoryItem::with(['category', 'supplier', 'stockLevels.location'])
            ->findOrFail($item);
    }

    public function with(): array
    {
        return [
            'levels' => $this->item->stockLevels->sortBy(fn ($l) => $l->location->name),
            'total' => (float) $this->item->stockLevels->sum('quantity'),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('inventory.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            &larr; Back to inventory
        </a>

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
    </div>
</div>
