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

        $from = Location::findOrFail($this->sources[$itemId]);
        $to = Location::findOrFail($this->destinationId);

        try {
            $stock->transfer($item->item, $from, $to, (float) $item->quantity, auth()->user(), 'Pick for '.$this->pickList->job->number);
        } catch (InsufficientStockException) {
            $this->addError('sources.'.$itemId, 'Not enough stock at that location.');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->addError('sources.'.$itemId, $e->getMessage());

            return;
        }

        $item->markPicked($from->id);

        if (! $this->pickList->destination_location_id) {
            $this->pickList->update(['destination_location_id' => $to->id]);
        }

        $this->load($this->pickList->id);

        if ($this->pickList->isFullyPicked() && $this->pickList->status !== PickListStatus::Completed) {
            $this->pickList->markCompleted();
            $this->load($this->pickList->id);
        }

        $this->statusMessage = 'Picked: '.$item->description;
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

        <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
            @forelse ($pickList->items as $item)
                <div wire:key="pli-{{ $item->id }}" class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 {{ $item->picked ? 'line-through text-gray-400' : '' }}">{{ $item->description }}</p>
                            <p class="text-xs text-gray-500">
                                Qty {{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}
                                @if ($item->picked && $item->fromLocation)
                                    · picked from {{ $item->fromLocation->name }}
                                @endif
                            </p>
                        </div>
                        @if ($item->picked)
                            <span class="shrink-0 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Picked</span>
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
                                <x-primary-button wire:click="pick({{ $item->id }})" type="button">Pick</x-primary-button>
                            </div>
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
