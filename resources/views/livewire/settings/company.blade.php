<?php

use App\Enums\Province;
use App\Models\CompanySetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public string $legal_name = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public ?string $province = null;
    public string $postal_code = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public string $tax_number = '';
    public string $payment_terms = '';
    public string $invoice_footer = '';
    public string $accent_color = '#4f46e5';
    public string $invoice_prefix = 'INV-';
    public int $invoice_next_number = 1;
    public string $quote_prefix = 'Q-';
    public int $quote_next_number = 1;
    public ?string $existingLogo = null;

    public $logo = null; // temporary upload
    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);

        $s = CompanySetting::current();
        $this->legal_name = (string) $s->legal_name;
        $this->address_line1 = (string) $s->address_line1;
        $this->address_line2 = (string) $s->address_line2;
        $this->city = (string) $s->city;
        $this->province = $s->province;
        $this->postal_code = (string) $s->postal_code;
        $this->phone = (string) $s->phone;
        $this->email = (string) $s->email;
        $this->website = (string) $s->website;
        $this->tax_number = (string) $s->tax_number;
        $this->payment_terms = (string) $s->payment_terms;
        $this->invoice_footer = (string) $s->invoice_footer;
        $this->accent_color = $s->accent_color ?: '#4f46e5';
        $this->invoice_prefix = $s->invoice_prefix ?? 'INV-';
        $this->invoice_next_number = (int) ($s->invoice_next_number ?: 1);
        $this->quote_prefix = $s->quote_prefix ?? 'Q-';
        $this->quote_next_number = (int) ($s->quote_next_number ?: 1);
        $this->existingLogo = $s->logo_path;
    }

    protected function rules(): array
    {
        return [
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (Province $p) => $p->value, Province::cases()))],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_prefix' => ['nullable', 'string', 'max:12'],
            'invoice_next_number' => ['required', 'integer', 'min:1'],
            'quote_prefix' => ['nullable', 'string', 'max:12'],
            'quote_next_number' => ['required', 'integer', 'min:1'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function save(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);

        $data = $this->validate();
        $settings = CompanySetting::current();

        if ($this->logo) {
            $data['logo_path'] = $this->logo->store('logos/'.tenant('id'), 'public');
        }
        unset($data['logo']);

        $settings->update($data);

        $this->logo = null;
        $this->existingLogo = $settings->logo_path;
        $this->statusMessage = 'Company settings saved.';
    }

    public function with(): array
    {
        return ['provinceOptions' => Province::options()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <h1 class="text-xl font-semibold text-gray-800">Company settings</h1>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">

            <div class="flex items-center gap-4">
                <div class="shrink-0">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="logo preview" class="h-16 w-auto rounded border border-gray-200">
                    @elseif ($existingLogo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogo) }}" alt="logo" class="h-16 w-auto rounded border border-gray-200">
                    @else
                        <div class="h-16 w-16 rounded border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">No logo</div>
                    @endif
                </div>
                <div>
                    <x-input-label for="logo" value="Logo (shown on quotes & invoices)" />
                    <input id="logo" type="file" wire:model="logo" accept="image/*" class="mt-1 block text-sm text-gray-600" />
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    <div wire:loading wire:target="logo" class="text-xs text-gray-400 mt-1">Uploading…</div>
                </div>
            </div>

            <div>
                <x-input-label for="legal_name" value="Legal / business name" />
                <x-text-input id="legal_name" wire:model="legal_name" class="block mt-1 w-full" />
                <x-input-error :messages="$errors->get('legal_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Address" />
                <x-text-input wire:model="address_line1" class="block mt-1 w-full" placeholder="Street address" />
                <x-text-input wire:model="address_line2" class="block mt-2 w-full" placeholder="Suite, unit (optional)" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="city" value="City" />
                    <x-text-input id="city" wire:model="city" class="block mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="province" value="Province" />
                    <select id="province" wire:model="province"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">—</option>
                        @foreach ($provinceOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="postal_code" value="Postal code" />
                    <x-text-input id="postal_code" wire:model="postal_code" class="block mt-1 w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" wire:model="phone" class="block mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" wire:model="email" type="email" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="website" value="Website" />
                <x-text-input id="website" wire:model="website" class="block mt-1 w-full" placeholder="https://…" />
                <x-input-error :messages="$errors->get('website')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <x-input-label for="tax_number" value="GST/HST number" />
                    <x-text-input id="tax_number" wire:model="tax_number" class="block mt-1 w-full" />
                </div>
                <div class="sm:col-span-1">
                    <x-input-label for="payment_terms" value="Payment terms" />
                    <x-text-input id="payment_terms" wire:model="payment_terms" class="block mt-1 w-full" placeholder="Net 15" />
                </div>
                <div class="sm:col-span-1">
                    <x-input-label for="accent_color" value="Accent colour" />
                    <input id="accent_color" type="color" wire:model="accent_color" class="mt-1 h-10 w-full rounded-md border border-gray-300" />
                    <x-input-error :messages="$errors->get('accent_color')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="invoice_footer" value="Invoice / quote footer" />
                <textarea id="invoice_footer" wire:model="invoice_footer" rows="2"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="e.g. payment instructions, thank-you note"></textarea>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h2 class="text-sm font-semibold text-gray-700">Document numbering</h2>
                <p class="mt-1 text-xs text-gray-500">Prefix and the next number for new invoices and quotes. Each is a per-company sequence; changing "next number" only affects documents created from now on.</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="invoice_prefix" value="Invoice prefix" />
                        <x-text-input id="invoice_prefix" wire:model="invoice_prefix" class="block mt-1 w-full" placeholder="INV-" />
                        <x-input-error :messages="$errors->get('invoice_prefix')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="invoice_next_number" value="Next invoice number" />
                        <x-text-input id="invoice_next_number" type="number" min="1" wire:model="invoice_next_number" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('invoice_next_number')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quote_prefix" value="Quote prefix" />
                        <x-text-input id="quote_prefix" wire:model="quote_prefix" class="block mt-1 w-full" placeholder="Q-" />
                        <x-input-error :messages="$errors->get('quote_prefix')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quote_next_number" value="Next quote number" />
                        <x-text-input id="quote_next_number" type="number" min="1" wire:model="quote_next_number" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('quote_next_number')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <x-primary-button>Save settings</x-primary-button>
            </div>
        </form>
    </div>
</div>
