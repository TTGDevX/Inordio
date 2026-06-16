<?php

use App\Enums\Province;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';
    public string $contact_name = '';
    public string $email = '';
    public string $phone = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public ?string $province = null;
    public string $postal_code = '';
    public string $country = 'CA';
    public bool $tax_exempt = false;
    public string $tax_number = '';
    public string $notes = '';
    public bool $is_active = true;

    public function mount(?string $customerId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-customers'), 403);

        if ($customerId !== null) {
            $customer = Customer::findOrFail($customerId);
            $this->editingId = $customer->id;
            $this->name = $customer->name;
            $this->contact_name = (string) $customer->contact_name;
            $this->email = (string) $customer->email;
            $this->phone = (string) $customer->phone;
            $this->address_line1 = (string) $customer->address_line1;
            $this->address_line2 = (string) $customer->address_line2;
            $this->city = (string) $customer->city;
            $this->province = $customer->province?->value;
            $this->postal_code = (string) $customer->postal_code;
            $this->country = $customer->country ?: 'CA';
            $this->tax_exempt = $customer->tax_exempt;
            $this->tax_number = (string) $customer->tax_number;
            $this->notes = (string) $customer->notes;
            $this->is_active = $customer->is_active;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (Province $p) => $p->value, Province::cases()))],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country' => ['required', 'string', 'size:2'],
            'tax_exempt' => ['boolean'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-customers'), 403);

        $data = $this->validate();

        $customer = $this->editingId
            ? tap(Customer::findOrFail($this->editingId))->update($data)
            : Customer::create($data);

        session()->flash('status', $this->editingId ? 'Customer updated.' : 'Customer created.');

        return $this->redirect(route('customers.show', $customer->id), navigate: true);
    }

    public function with(): array
    {
        return ['provinceOptions' => Province::options()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('customers.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to customers</a>

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit customer' : 'New customer' }}</h1>

            <div>
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" wire:model="name" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="contact_name" value="Contact name" />
                    <x-text-input id="contact_name" wire:model="contact_name" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" wire:model="email" type="email" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" wire:model="phone" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="address_line1" value="Address" />
                <x-text-input id="address_line1" wire:model="address_line1" class="block mt-1 w-full" placeholder="Street address" />
                <x-text-input wire:model="address_line2" class="block mt-2 w-full" placeholder="Suite, unit, etc. (optional)" />
                <x-input-error :messages="$errors->get('address_line1')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="city" value="City" />
                    <x-text-input id="city" wire:model="city" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="province" value="Province" />
                    <select id="province" wire:model="province"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Select —</option>
                        @foreach ($provinceOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('province')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="postal_code" value="Postal code" />
                    <x-text-input id="postal_code" wire:model="postal_code" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-md bg-gray-50 p-4 space-y-3">
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model.live="tax_exempt"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ms-2 text-sm text-gray-700">Tax exempt</span>
                </label>
                @if ($tax_exempt)
                    <div>
                        <x-input-label for="tax_number" value="Tax / exemption number" />
                        <x-text-input id="tax_number" wire:model="tax_number" class="block mt-1 w-full font-mono" />
                        <x-input-error :messages="$errors->get('tax_number')" class="mt-2" />
                    </div>
                @endif
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" wire:model="notes" rows="2"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <label class="inline-flex items-center">
                <input type="checkbox" wire:model="is_active"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="ms-2 text-sm text-gray-600">Active</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>{{ $editingId ? 'Save changes' : 'Create customer' }}</x-primary-button>
                <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
