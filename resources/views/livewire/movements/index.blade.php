<?php

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = '';

    #[Url]
    public string $itemId = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);
    }

    public function updating($name): void
    {
        if (in_array($name, ['type', 'itemId'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $query = StockMovement::with(['item', 'fromLocation', 'toLocation', 'user', 'job', 'supplier'])
            ->latest();

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }
        if ($this->itemId !== '') {
            $query->where('inventory_item_id', $this->itemId);
        }

        return [
            'movements' => $query->paginate(50),
            'items' => InventoryItem::orderBy('name')->get(['id', 'name']),
            'types' => StockMovementType::cases(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <h1 class="text-xl font-semibold text-gray-800">Stock movement log</h1>

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500">Type</label>
                <select wire:model.live="type"
                    class="mt-1 block w-44 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All types</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500">Item</label>
                <select wire:model.live="itemId"
                    class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All items</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Item</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2 text-right">Qty</th>
                        <th class="px-4 py-2">Movement</th>
                        <th class="px-4 py-2">By</th>
                        <th class="px-4 py-2">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $m)
                        <tr wire:key="mv-{{ $m->id }}">
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $m->created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-2 text-gray-900">
                                <a href="{{ route('inventory.show', $m->inventory_item_id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">{{ $m->item?->name ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $m->type->badgeClasses() }}">{{ $m->type->label() }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $m->quantity, 2), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $m->fromLocation?->name ?? '—' }} <span class="text-gray-400">→</span> {{ $m->toLocation?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $m->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-2 text-gray-500">
                                @if ($m->job)
                                    <a href="{{ route('jobs.show', $m->job_id) }}" wire:navigate class="font-mono text-indigo-600 hover:text-indigo-800">{{ $m->job->number }}</a>
                                @elseif ($m->supplier)
                                    {{ $m->supplier->name }}
                                @endif
                                @if ($m->note)
                                    <span class="text-gray-400">{{ $m->note }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-gray-500" colspan="7">No stock movements.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $movements->links() }}</div>
    </div>
</div>
