<?php

use App\Models\Location;
use App\Models\SerializedAsset;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public SerializedAsset $asset;

    public ?int $assembleId = null;
    public ?int $moveLocationId = null;
    public string $statusMessage = '';

    public function mount(string $assetId): void
    {
        $this->asset = SerializedAsset::with(['item', 'ownLocation', 'parent'])->findOrFail($assetId);
        $this->moveLocationId = $this->asset->root()->location_id;
    }

    private function reload(): void
    {
        $this->asset = SerializedAsset::with(['item', 'ownLocation', 'parent'])->findOrFail($this->asset->id);
    }

    /**
     * Assemble a chosen in-stock unit into this asset (it becomes a nested part).
     */
    public function assemble(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);
        $this->validate(['assembleId' => ['required', 'integer', 'exists:serialized_assets,id']]);

        $child = SerializedAsset::findOrFail($this->assembleId);

        try {
            $child->attachTo($this->asset);
        } catch (\InvalidArgumentException $e) {
            $this->addError('assembleId', $e->getMessage());

            return;
        }

        $this->reset('assembleId');
        $this->reload();
        $this->statusMessage = 'Part assembled.';
    }

    /**
     * Detach a unit (this asset or one of its descendants) from its parent.
     */
    public function detach(int $assetId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);

        $target = SerializedAsset::findOrFail($assetId);
        // Only allow detaching within the tree we're looking at.
        abort_unless($assetId === $this->asset->id || $this->asset->containsInSubtree($target), 403);

        $target->detach();
        $this->reload();
        $this->statusMessage = 'Part detached.';
    }

    public function move(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);
        $this->validate(['moveLocationId' => ['required', 'integer', 'exists:locations,id']]);

        $this->asset->moveTo($this->moveLocationId);
        $this->reload();
        $this->statusMessage = 'Asset moved.';
    }

    public function retire(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $this->asset->retire();
        $this->reload();
        $this->statusMessage = 'Asset retired.';
    }

    public function with(): array
    {
        $root = $this->asset->root();
        $effectiveLocationId = $root->location_id;

        return [
            'effectiveLocation' => $effectiveLocationId ? Location::find($effectiveLocationId) : null,
            'events' => $this->asset->events()->with(['parentAsset', 'location', 'user'])->latest()->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Assemble candidates: other top-level units that aren't retired and
            // aren't this tree's root (which would create a cycle).
            'candidates' => SerializedAsset::whereNull('parent_id')
                ->where('status', '!=', 'retired')
                ->where('id', '!=', $root->id)
                ->orderBy('serial_number')
                ->get(['id', 'serial_number']),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('assets.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to assets</a>
            @can('manage-inventory')
                <div class="flex items-center gap-4">
                    <a href="{{ route('assets.label', $asset->id) }}" target="_blank"
                       class="text-sm text-indigo-600 hover:text-indigo-800">Label</a>
                    <a href="{{ route('assets.edit', $asset->id) }}" wire:navigate
                       class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                    @if ($asset->status !== \App\Enums\AssetStatus::Retired)
                        <button type="button" wire:click="retire" wire:confirm="Retire this asset?"
                            class="text-sm text-gray-500 hover:text-gray-700">Retire</button>
                    @endif
                </div>
            @endcan
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ $asset->serial_number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $asset->item?->name ?? 'Uncatalogued unit' }}</p>
                    @if ($asset->parent)
                        <p class="mt-1 text-xs text-gray-500">Part of
                            <a href="{{ route('assets.show', $asset->parent->id) }}" wire:navigate class="font-mono text-indigo-600 hover:text-indigo-800">{{ $asset->parent->serial_number }}</a>
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $asset->status->badgeClasses() }}">{{ $asset->status->label() }}</span>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Effective location</dt>
                    <dd class="text-gray-900">{{ $effectiveLocation?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Nesting</dt>
                    <dd class="text-gray-900">{{ $asset->isRoot() ? 'Top-level unit' : 'Nested part' }}</dd>
                </div>
            </dl>

            @if ($asset->notes)
                <p class="mt-4 text-sm text-gray-700 whitespace-pre-line">{{ $asset->notes }}</p>
            @endif
        </div>

        {{-- Composition tree --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Composition</h2>
            <ul class="mt-3">
                @include('partials.asset-node', ['node' => $asset, 'asset' => $asset])
            </ul>

            @can('move-stock')
                @if ($asset->status !== \App\Enums\AssetStatus::Retired)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <label class="block text-sm font-medium text-gray-700">Assemble a part into this unit</label>
                        <div class="mt-2 flex flex-wrap items-end gap-3">
                            <select wire:model="assembleId"
                                class="block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Choose an in-stock unit —</option>
                                @foreach ($candidates as $c)
                                    <option value="{{ $c->id }}">{{ $c->serial_number }}</option>
                                @endforeach
                            </select>
                            <x-secondary-button wire:click="assemble" type="button">Assemble</x-secondary-button>
                        </div>
                        @error('assembleId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            @endcan
        </div>

        {{-- Move (whole tree moves with the root) --}}
        @can('move-stock')
            @if ($asset->status !== \App\Enums\AssetStatus::Retired)
                <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
                    <h2 class="font-medium text-gray-800">Move</h2>
                    <p class="mt-1 text-xs text-gray-500">Relocates the top-level unit — every nested part moves with it.</p>
                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <select wire:model="moveLocationId"
                            class="block w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Choose a location —</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                        <x-secondary-button wire:click="move" type="button">Move</x-secondary-button>
                    </div>
                    @error('moveLocationId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        @endcan

        {{-- Event timeline --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="font-medium text-gray-800">History</h2>
            </div>
            @if ($events->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-500">No events recorded.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($events as $event)
                        <li class="flex items-start justify-between gap-3 px-5 py-3" wire:key="ev-{{ $event->id }}">
                            <div>
                                <p class="text-sm text-gray-900">{{ $event->type->label() }}</p>
                                <p class="text-xs text-gray-500">
                                    @if ($event->parentAsset)
                                        {{ $event->parentAsset->serial_number }} ·
                                    @endif
                                    @if ($event->location)
                                        {{ $event->location->name }} ·
                                    @endif
                                    {{ $event->user?->name ?? 'System' }}
                                    @if ($event->note)
                                        — {{ $event->note }}
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ $event->created_at->format('M j, Y g:i A') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
