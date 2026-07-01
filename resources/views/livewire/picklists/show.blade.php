<?php

use App\Enums\PickListStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\PickList;
use App\Services\StockManager;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public PickList $pickList;
    public ?int $destinationId = null;

    /** @var array<int, int|string|null> pickListItemId => source location id */
    public array $sources = [];
    /** @var array<int, int|string|null> pickListItemId => quantity to pick */
    public array $pickQty = [];
    public string $statusMessage = '';

    public function mount(string $pickListId): void
    {
        $this->load($pickListId);
        $this->destinationId = $this->pickList->destination_location_id;
    }

    private function load(int|string $id): void
    {
        $this->pickList = PickList::with(['job.customer', 'items.item', 'destination'])->findOrFail($id);
    }

    public function pick(int $itemId, StockManager $stock): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);

        $this->validate([
            'destinationId' => ['required', 'integer', 'exists:locations,id'],
            'sources.'.$itemId => ['required', 'integer', 'exists:locations,id'],
        ], [], ['destinationId' => 'destination', 'sources.'.$itemId => 'source location']);

        $item = $this->pickList->items->firstWhere('id', $itemId);
        if (! $item || $item->picked || ! $item->item) {
            return;
        }

        // Quantity to pick — defaults to the full need; clamp to (0, needed].
        $needed = (float) $item->quantity;
        $qty = (isset($this->pickQty[$itemId]) && $this->pickQty[$itemId] !== '')
            ? (float) $this->pickQty[$itemId] : $needed;
        $qty = max(0.0, min($qty, $needed));

        if ($qty <= 0) {
            $this->addError('sources.'.$itemId, 'Enter a quantity, or use “none available” to back-order the line.');

            return;
        }

        $from = Location::findOrFail($this->sources[$itemId]);
        $to = Location::findOrFail($this->destinationId);

        try {
            $stock->transfer($item->item, $from, $to, $qty, auth()->user(), 'Pick for '.$this->pickList->job->number);
        } catch (InsufficientStockException) {
            $this->addError('sources.'.$itemId, 'Not enough stock at that location.');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->addError('sources.'.$itemId, $e->getMessage());

            return;
        }

        $item->markPicked($from->id, $qty);

        if (! $this->pickList->destination_location_id) {
            $this->pickList->update(['destination_location_id' => $to->id]);
        }

        $this->finalize();
        $this->statusMessage = $qty < $needed
            ? 'Picked '.rtrim(rtrim(number_format($qty, 2), '0'), '.').' of '.$item->description.' — remainder back-ordered.'
            : 'Picked: '.$item->description;
    }

    /**
     * Nothing available — resolve the line as fully back-ordered (no stock moves).
     */
    public function markShort(int $itemId): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('move-stock'), 403);

        $item = $this->pickList->items->firstWhere('id', $itemId);
        if (! $item || $item->picked) {
            return;
        }

        $item->markShort();
        $this->finalize();
        $this->statusMessage = 'Back-ordered: '.$item->description;
    }

    private function finalize(): void
    {
        $this->load($this->pickList->id);

        if ($this->pickList->isFullyPicked() && $this->pickList->status !== PickListStatus::Completed) {
            $this->pickList->markCompleted();
            $this->load($this->pickList->id);
        }
    }

    public function with(): array
    {
        return ['locations' => Location::where('is_active', true)->orderBy('name')->get()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('jobs.show', $pickList->job_id) }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to job</a>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Pick list</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $pickList->job->number }} · {{ $pickList->job->customer->name }}</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pickList->status->badgeClasses() }}">
                    {{ $pickList->status->label() }}
                </span>
            </div>

            <div class="mt-4">
                <x-input-label for="destinationId" value="Pick to (truck / destination)" />
                <select id="destinationId" wire:model="destinationId"
                    @disabled($pickList->status === \App\Enums\PickListStatus::Completed)
                    class="block mt-1 w-full sm:w-72 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Select destination —</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->type->label() }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('destinationId')" class="mt-1" />
            </div>
        </div>

        @if ($pickList->hasBackorders())
            <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $pickList->backorderItems()->count() }} line{{ $pickList->backorderItems()->count() === 1 ? '' : 's' }} short — parts are on back-order. Raise a purchase order to restock.
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
            @forelse ($pickList->items as $item)
                <div wire:key="pli-{{ $item->id }}" class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 {{ $item->picked ? 'text-gray-500' : '' }}">{{ $item->description }}</p>
                            <p class="text-xs text-gray-500">
                                Qty {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}
                                @if ($item->picked && $item->picked_quantity !== null)
                                    · picked {{ rtrim(rtrim(number_format((float) $item->picked_quantity, 2), '0'), '.') }}
                                @endif
                                @if ($item->picked && $item->fromLocation)
                                    from {{ $item->fromLocation->name }}
                                @endif
                            </p>
                            @if ($item->isShort())
                                <p class="text-xs font-medium text-amber-700">Short by {{ rtrim(rtrim(number_format((float) $item->short_quantity, 2), '0'), '.') }} — back-ordered</p>
                            @endif
                        </div>
                        @if ($item->picked)
                            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->isShort() ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700' }}">
                                {{ $item->isShort() ? 'Short' : 'Picked' }}
                            </span>
                        @endif
                    </div>

                    @if (! $item->picked)
                        @can('move-stock')
                            <div class="mt-3 flex flex-wrap items-end gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500">Pick from</label>
                                    <select wire:model="sources.{{ $item->id }}"
                                        class="mt-1 block w-56 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Source location —</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->type->label() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Qty</label>
                                    <input type="number" step="0.01" min="0" max="{{ (float) $item->quantity }}"
                                        wire:model="pickQty.{{ $item->id }}"
                                        placeholder="{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}"
                                        class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <x-primary-button wire:click="pick({{ $item->id }})" type="button">Pick</x-primary-button>
                                <button type="button" wire:click="markShort({{ $item->id }})"
                                    wire:confirm="Mark this line as none available (back-order the full quantity)?"
                                    class="text-xs text-amber-700 hover:text-amber-900">none available</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Leave qty blank to pick the full amount; enter less to record a short pick.</p>
                            <x-input-error :messages="$errors->get('sources.'.$item->id)" class="mt-1" />
                        @endcan
                    @endif
                </div>
            @empty
                <p class="p-5 text-sm text-gray-500">No stock items on this job to pick.</p>
            @endforelse
        </div>
    </div>
</div>
