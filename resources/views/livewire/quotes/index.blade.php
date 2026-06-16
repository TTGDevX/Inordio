<?php

use App\Models\Quote;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $quotes = Quote::query()
            ->with(['customer', 'lines'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15);

        return ['quotes' => $quotes];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Quotes</h1>
            @can('manage-quotes')
                <a href="{{ route('quotes.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    New quote
                </a>
            @endcan
        </div>

        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="search"
                placeholder="Search by number or customer"
                class="w-full sm:w-96 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                autocomplete="off" />
        </div>

        @if ($quotes->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                @if ($search !== '')
                    No quotes match “{{ $search }}”.
                @else
                    No quotes yet.
                @endif
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Quote</th>
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotes as $quote)
                            <tr wire:key="quote-{{ $quote->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('quotes.show', $quote->id) }}'">
                                <td class="px-6 py-3 font-mono text-sm text-gray-900">{{ $quote->number }}</td>
                                <td class="px-6 py-3 text-gray-900">{{ $quote->customer->name }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $quote->status->badgeClasses() }}">
                                        {{ $quote->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-900">${{ number_format($quote->subtotal(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $quotes->links() }}</div>
        @endif
    </div>
</div>
