<?php

use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\SerializedAsset;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public string $serial_number = '';
    public ?int $inventory_item_id = null;
    public ?int $location_id = null;
    public string $status = 'in_stock';
    public string $notes = '';

    public function mount(?string $assetId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        if ($assetId !== null) {
            $asset = SerializedAsset::findOrFail($assetId);
            $this->editingId = $asset->id;
            $this->serial_number = $asset->serial_number;
            $this->inventory_item_id = $asset->inventory_item_id;
            $this->location_id = $asset->location_id;
            $this->status = $asset->status->value;
            $this->notes = (string) $asset->notes;
        }
    }

    protected function rules(): array
    {
        return [
            'serial_number' => [
                'required', 'string', 'max:255',
                Rule::unique('serialized_assets', 'serial_number')
                    ->where('tenant_id', tenant('id'))
                    ->ignore($this->editingId),
            ],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_map(fn (AssetStatus $s) => $s->value, AssetStatus::cases()))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $data = $this->validate();

        if ($this->editingId) {
            $asset = tap(SerializedAsset::findOrFail($this->editingId))->update($data);
        } else {
            $asset = SerializedAsset::create($data);
            $asset->recordEvent(AssetEventType::Created, null, $asset->location_id, 'Registered');
        }

        session()->flash('status', $this->editingId ? 'Asset updated.' : 'Asset registered.');

        return $this->redirect(route('assets.show', $asset->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'items' => InventoryItem::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => AssetStatus::cases(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('assets.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to assets</a>

        <form wire:submit="save" class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit asset' : 'Register asset' }}</h1>

            <div>
                <x-input-label for="serial_number" value="Serial number" />
                <x-text-input id="serial_number" wire:model="serial_number" class="block mt-1 w-full font-mono" />
                <x-input-error :messages="$errors->get('serial_number')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="inventory_item_id" value="Product type (optional)" />
                <select id="inventory_item_id" wire:model="inventory_item_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Not catalogued —</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="location_id" value="Location (top-level only)" />
                    <select id="location_id" wire:model="location_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Nested parts inherit their location from the top-level unit.</p>
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" wire:model="status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" wire:model="notes" rows="2"
                    class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>

            <div class="pt-2 flex items-center gap-3">
                <x-primary-button>{{ $editingId ? 'Save asset' : 'Register asset' }}</x-primary-button>
                <a href="{{ route('assets.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
