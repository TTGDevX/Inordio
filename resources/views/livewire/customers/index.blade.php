<?php

use App\Models\Customer;
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
        $customers = Customer::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return ['customers' => $customers];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800">Customers</h1>
            @can('manage-customers')
                <a href="{{ route('customers.create') }}" wire:navigate
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Add customer
                </a>
            @endcan
        </div>

        <div class="relative">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search name, contact, email or phone"
                class="w-full sm:w-96 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                autocomplete="off"
            />
        </div>

        <div wire:loading.delay class="text-sm text-gray-400">Searching…</div>

        @if ($customers->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">
                @if ($search !== '')
                    No customers match “{{ $search }}”.
                @else
                    No customers yet.
                @endif
            </div>
        @else
            <div class="grid gap-3 sm:hidden">
                @foreach ($customers as $customer)
                    <a href="{{ route('customers.show', $customer->id) }}" wire:navigate
                       class="block bg-white rounded-lg shadow-sm p-4 active:bg-gray-50">
                        <p class="font-medium text-gray-900">{{ $customer->name }}</p>
                        <p class="text-sm text-gray-500">{{ $customer->contact_name ?? $customer->email ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ $customer->city }}{{ $customer->province ? ', '.$customer->province->value : '' }}</p>
                    </a>
                @endforeach
            </div>

            <div class="hidden sm:block bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Customer</th>
                            <th class="px-6 py-3">Contact</th>
                            <th class="px-6 py-3">Location</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($customers as $customer)
                            <tr wire:key="customer-{{ $customer->id }}" class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='{{ route('customers.show', $customer->id) }}'">
                                <td class="px-6 py-3">
                                    <div class="font-medium text-gray-900">{{ $customer->name }}</div>
                                    @unless ($customer->is_active)
                                        <span class="text-xs text-gray-400">(inactive)</span>
                                    @endunless
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    {{ $customer->contact_name ?? '—' }}
                                    @if ($customer->email)
                                        <div class="text-xs text-gray-400">{{ $customer->email }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    {{ $customer->city }}{{ $customer->province ? ', '.$customer->province->value : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $customers->links() }}</div>
        @endif
    </div>
</div>
