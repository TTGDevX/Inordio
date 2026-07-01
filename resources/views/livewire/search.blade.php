<?php

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Job;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    public function with(): array
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return ['customers' => collect(), 'jobs' => collect(), 'invoices' => collect(), 'items' => collect(), 'term' => $term];
        }

        $like = '%'.$term.'%';

        return [
            'term' => $term,
            'customers' => Customer::where(fn ($q) => $q
                ->where('name', 'like', $like)->orWhere('contact_name', 'like', $like)
                ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->orderBy('name')->limit(8)->get(),
            'jobs' => Job::with('customer')->where(fn ($q) => $q
                ->where('number', 'like', $like)->orWhere('title', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)))
                ->latest()->limit(8)->get(),
            'invoices' => Invoice::with('customer')->where(fn ($q) => $q
                ->where('number', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)))
                ->latest()->limit(8)->get(),
            'items' => InventoryItem::where(fn ($q) => $q
                ->where('name', 'like', $like)->orWhere('internal_sku', 'like', $like)
                ->orWhere('vendor_sku', 'like', $like)->orWhere('barcode', 'like', $like))
                ->orderBy('name')->limit(8)->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <h1 class="text-xl font-semibold text-gray-800">Search</h1>

        <input type="search" wire:model.live.debounce.300ms="search" autofocus
            placeholder="Search customers, jobs, invoices, inventory…"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

        @if (mb_strlen($term) < 2)
            <p class="text-sm text-gray-500">Type at least two characters to search across your data.</p>
        @elseif ($customers->isEmpty() && $jobs->isEmpty() && $invoices->isEmpty() && $items->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">Nothing matches “{{ $term }}”.</div>
        @else
            @php($sections = [
                ['label' => 'Customers', 'rows' => $customers, 'route' => 'customers.show', 'primary' => fn ($c) => $c->name, 'secondary' => fn ($c) => $c->email ?? $c->phone],
                ['label' => 'Jobs', 'rows' => $jobs, 'route' => 'jobs.show', 'primary' => fn ($j) => $j->number.' · '.$j->title, 'secondary' => fn ($j) => $j->customer?->name],
                ['label' => 'Invoices', 'rows' => $invoices, 'route' => 'invoices.show', 'primary' => fn ($i) => $i->number, 'secondary' => fn ($i) => $i->customer?->name],
                ['label' => 'Inventory', 'rows' => $items, 'route' => 'inventory.show', 'primary' => fn ($i) => $i->name, 'secondary' => fn ($i) => $i->internal_sku],
            ])
            @foreach ($sections as $section)
                @if ($section['rows']->isNotEmpty())
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="px-5 py-2 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $section['label'] }}</h2>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($section['rows'] as $row)
                                <li wire:key="{{ $section['label'] }}-{{ $row->id }}">
                                    <a href="{{ route($section['route'], $row->id) }}" wire:navigate class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                        <span class="text-gray-900">{{ $section['primary']($row) }}</span>
                                        <span class="text-sm text-gray-400">{{ $section['secondary']($row) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</div>
