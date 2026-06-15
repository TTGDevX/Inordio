<?php

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'warehouse';

    public ?int $assigned_user_id = null;

    public bool $is_active = true;

    public bool $showForm = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:warehouse,truck,jobsite'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function newLocation(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $location = Location::findOrFail($id);
        $this->editingId = $location->id;
        $this->name = $location->name;
        $this->type = $location->type->value;
        $this->assigned_user_id = $location->assigned_user_id;
        $this->is_active = $location->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        // Only trucks carry an assigned technician.
        if ($data['type'] !== LocationType::Truck->value) {
            $data['assigned_user_id'] = null;
        }

        if ($this->editingId) {
            Location::findOrFail($this->editingId)->update($data);
        } else {
            Location::create($data);
        }

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'type', 'assigned_user_id', 'is_active', 'showForm']);
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'locations' => Location::orderBy('type')->orderBy('name')->get(),
            'technicians' => User::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Locations</h1>
            <x-primary-button wire:click="newLocation" type="button">Add location</x-primary-button>
        </div>

        @if ($showForm)
            <div class="bg-white rounded-lg shadow-sm p-5 space-y-4">
                <h2 class="font-medium text-gray-800">{{ $editingId ? 'Edit location' : 'New location' }}</h2>

                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" wire:model="name" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" wire:model.live="type"
                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="warehouse">Warehouse</option>
                            <option value="truck">Truck</option>
                            <option value="jobsite">Job Site</option>
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    @if ($type === 'truck')
                        <div>
                            <x-input-label for="assigned_user_id" value="Assigned technician" />
                            <select id="assigned_user_id" wire:model="assigned_user_id"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Unassigned —</option>
                                @foreach ($technicians as $tech)
                                    <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('assigned_user_id')" class="mt-2" />
                        </div>
                    @endif
                </div>

                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model="is_active"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ms-2 text-sm text-gray-600">Active</span>
                </label>

                <div class="flex items-center gap-3">
                    <x-primary-button wire:click="save" type="button">Save</x-primary-button>
                    <x-secondary-button wire:click="resetForm" type="button">Cancel</x-secondary-button>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            @if ($locations->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-500">No locations yet. Add a warehouse or truck to start tracking stock.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($locations as $location)
                        <li wire:key="loc-{{ $location->id }}" class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-gray-900">
                                    {{ $location->name }}
                                    @unless ($location->is_active)
                                        <span class="ms-1 text-xs text-gray-400">(inactive)</span>
                                    @endunless
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $location->type->label() }}
                                    @if ($location->assignedUser)
                                        · {{ $location->assignedUser->name }}
                                    @endif
                                </p>
                            </div>
                            <button wire:click="edit({{ $location->id }})" type="button"
                                class="text-sm text-indigo-600 hover:text-indigo-800">Edit</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
