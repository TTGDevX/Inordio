<?php

use App\Models\StockLevel;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);
    }

    public function with(): array
    {
        // Every stock level that has hit or fallen below its reorder point,
        // grouped by the item's preferred supplier so a PO can go out per vendor.
        $groups = StockLevel::query()
            ->whereNotNull('min_quantity')
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->with(['item.supplierOfferings.supplier', 'location'])
            ->get()
            ->sortBy(fn (StockLevel $l) => $l->item?->name)
            ->groupBy(fn (StockLevel $l) => $l->item?->preferredSupplierName() ?? 'No preferred supplier');

        return ['groups' => $groups];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Reorder — low stock</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Inventory</a>
                @can('manage-purchasing')
                    <a href="{{ route('purchasing.create') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        New purchase order
                    </a>
                @endcan
            </div>
        </div>

        @forelse ($groups as $supplier => $levels)
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                    <h2 class="font-medium text-gray-800">{{ $supplier }} <span class="text-xs text-gray-500">· {{ $levels->count() }} item{{ $levels->count() === 1 ? '' : 's' }}</span></h2>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-white text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-5 py-2">Item</th>
                            <th class="px-5 py-2">Location</th>
                            <th class="px-5 py-2 text-right">On hand</th>
                            <th class="px-5 py-2 text-right">Min</th>
                            <th class="px-5 py-2 text-right">Short by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($levels as $level)
                            @php($short = max(0, (float) $level->min_quantity - (float) $level->quantity))
                            <tr wire:key="ro-{{ $level->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('inventory.show', $level->inventory_item_id) }}'">
                                <td class="px-5 py-2 text-gray-900">{{ $level->item?->name ?? '—' }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ $level->location?->name ?? '—' }}</td>
                                <td class="px-5 py-2 text-right tabular-nums text-red-600">{{ rtrim(rtrim(number_format((float) $level->quantity, 2), '0'), '.') }}</td>
                                <td class="px-5 py-2 text-right tabular-nums text-gray-500">{{ rtrim(rtrim(number_format((float) $level->min_quantity, 2), '0'), '.') }}</td>
                                <td class="px-5 py-2 text-right tabular-nums font-medium text-gray-900">{{ rtrim(rtrim(number_format($short, 2), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                Nothing to reorder — every stocked item is at or above its minimum.
            </div>
        @endforelse
    </div>
</div>
