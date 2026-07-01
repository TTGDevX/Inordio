<?php

use App\Models\InventoryItem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public bool $archived = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedArchived(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $items = InventoryItem::query()
            ->where('is_active', ! $this->archived)
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('internal_sku', 'like', $term)
                        ->orWhere('vendor_sku', 'like', $term)
                        ->orWhere('barcode', 'like', $term);
                });
            })
            ->withSum('stockLevels as total_quantity', 'quantity')
            ->orderBy('name')
            ->paginate(15);

        return ['items' => $items];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Inventory</h1>
            @can('manage-inventory')
                <a href="{{ route('exports.inventory') }}"
                   class="me-2 inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    Export CSV
                </a>
                <a href="{{ route('inventory.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Add item
                </a>
            @endcan
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search name, SKU or barcode"
                class="w-full sm:w-96 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                autocomplete="off"
            />
            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" wire:model.live="archived"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Show archived
            </label>
        </div>

        <div wire:loading.delay class="text-sm text-gray-400">Searching…</div>

        @if ($items->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                @if ($search !== '')
                    No items match “{{ $search }}”.
                @else
                    No inventory items yet.
                @endif
            </div>
        @else
            {{-- Mobile: stacked cards. Desktop: a table. --}}
            <div class="grid gap-3 sm:hidden">
                @foreach ($items as $item)
                    <a href="{{ route('inventory.show', $item) }}" wire:navigate
                       class="block bg-white rounded-lg shadow-sm p-4 active:bg-gray-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500 font-mono">{{ $item->internal_sku }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-lg font-semibold text-gray-900 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $item->total_quantity, 2), '0'), '.') }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $item->unit_of_measure }}</p>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-gray-500">${{ number_format((float) $item->price, 2) }}</span>
                            @if ($item->is_serialized)
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Serialized</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Item</th>
                            <th class="px-6 py-3">Internal SKU</th>
                            <th class="px-6 py-3 text-right">On hand</th>
                            <th class="px-6 py-3 text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($items as $item)
                            <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('inventory.show', $item) }}'">
                                <td class="px-6 py-3">
                                    <div class="font-medium text-gray-900">{{ $item->name }}</div>
                                    @if ($item->is_serialized)
                                        <span class="text-xs text-indigo-700">Serialized</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 font-mono text-sm text-gray-600">{{ $item->internal_sku }}</td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-900">
                                    {{ rtrim(rtrim(number_format((float) $item->total_quantity, 2), '0'), '.') }}
                                    <span class="text-xs text-gray-400">{{ $item->unit_of_measure }}</span>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-600">${{ number_format((float) $item->price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $items->links() }}</div>
        @endif
    </div>
</div>
