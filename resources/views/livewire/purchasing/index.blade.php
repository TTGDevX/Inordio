<?php

use App\Models\PurchaseOrder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);
    }

    public function with(): array
    {
        return [
            'orders' => PurchaseOrder::with(['supplier', 'lines'])->latest()->paginate(15),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Purchase orders</h1>
            <a href="{{ route('purchasing.create') }}" wire:navigate
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                New PO
            </a>
        </div>

        @if ($orders->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">No purchase orders yet.</div>
        @else
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">PO</th>
                            <th class="px-6 py-3">Supplier</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($orders as $po)
                            <tr wire:key="po-{{ $po->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('purchasing.show', $po->id) }}'">
                                <td class="px-6 py-3 font-mono text-sm text-gray-900">{{ $po->number }}</td>
                                <td class="px-6 py-3 text-gray-900">{{ $po->supplier->name }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $po->status->badgeClasses() }}">
                                        {{ $po->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-900">${{ number_format($po->total(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</div>
