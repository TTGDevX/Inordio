<?php

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Customer $customer;

    /**
     * Resolved here (not via implicit binding) so the BelongsToTenant scope is
     * active — another tenant's id 404s. Param is {customerId}, not {customer}.
     */
    public function mount(string $customerId): void
    {
        $this->customer = Customer::findOrFail($customerId);
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('customers.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to customers</a>
            @can('manage-customers')
                <a href="{{ route('customers.edit', $customer->id) }}" wire:navigate
                   class="text-sm text-indigo-600 hover:text-indigo-800">Edit customer</a>
            @endcan
        </div>

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $customer->name }}</h1>
                    @if ($customer->contact_name)
                        <p class="mt-1 text-sm text-gray-500">{{ $customer->contact_name }}</p>
                    @endif
                </div>
                @if ($customer->tax_exempt)
                    <span class="self-start inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Tax exempt</span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900">{{ $customer->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Phone</dt>
                    <dd class="text-gray-900">{{ $customer->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Province</dt>
                    <dd class="text-gray-900">{{ $customer->province?->label() ?? '—' }}</dd>
                </div>
                <div class="col-span-2 sm:col-span-3">
                    <dt class="text-gray-500">Address</dt>
                    <dd class="text-gray-900">
                        @php
                            $parts = array_filter([
                                $customer->address_line1,
                                $customer->address_line2,
                                trim(($customer->city ?? '').' '.($customer->province?->value ?? '').' '.($customer->postal_code ?? '')),
                                $customer->country,
                            ]);
                        @endphp
                        {{ $parts ? implode(', ', $parts) : '—' }}
                    </dd>
                </div>
                @if ($customer->tax_exempt && $customer->tax_number)
                    <div>
                        <dt class="text-gray-500">Tax number</dt>
                        <dd class="font-mono text-gray-900">{{ $customer->tax_number }}</dd>
                    </div>
                @endif
            </dl>

            @if ($customer->notes)
                <div class="mt-4 text-sm">
                    <p class="text-gray-500">Notes</p>
                    <p class="text-gray-900 whitespace-pre-line">{{ $customer->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Quotes, jobs and invoices for this customer will surface here in later phases. --}}
    </div>
</div>
