<?php

use App\Enums\AssetStatus;
use App\Models\SerializedAsset;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $assets = SerializedAsset::query()
            ->whereNull('parent_id') // top-level units; nested parts show inside their root
            ->with(['item', 'ownLocation'])
            ->withCount('children')
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('serial_number', 'like', $term)
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('serial_number')
            ->paginate(20);

        return ['assets' => $assets, 'statuses' => AssetStatus::cases()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Serialized assets</h1>
            @can('manage-inventory')
                <a href="{{ route('assets.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    New asset
                </a>
            @endcan
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <input type="search" wire:model.live.debounce.300ms="search"
                placeholder="Search serial or item"
                class="w-full sm:w-80 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                autocomplete="off" />
            <div>
                <label class="block text-xs text-gray-500">Status</label>
                <select wire:model.live="status"
                    class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($assets->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                No serialized assets{{ $search !== '' ? ' match your search' : ' yet' }}.
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Serial</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Location</th>
                            <th class="px-6 py-3 text-right">Parts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($assets as $asset)
                            <tr wire:key="asset-{{ $asset->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('assets.show', $asset->id) }}'">
                                <td class="px-6 py-3 font-mono text-sm text-gray-900">{{ $asset->serial_number }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $asset->item?->name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $asset->status->badgeClasses() }}">{{ $asset->status->label() }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $asset->ownLocation?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-600">{{ $asset->children_count ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $assets->links() }}</div>
        @endif
    </div>
</div>
