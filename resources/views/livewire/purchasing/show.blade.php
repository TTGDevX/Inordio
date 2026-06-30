<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Services\StockManager;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public PurchaseOrder $po;
    public ?int $receiveLocationId = null;
    public string $statusMessage = '';

    public function mount(string $poId): void
    {
        $this->po = PurchaseOrder::with(['supplier', 'lines.item'])->findOrFail($poId);
    }

    private function reload(): void
    {
        $this->po = PurchaseOrder::with(['supplier', 'lines.item'])->findOrFail($this->po->id);
    }

    public function markOrdered(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);
        $this->po->markOrdered();
        $this->reload();
        $this->statusMessage = 'Marked as ordered.';
    }

    public function cancel(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);
        $this->po->cancel();
        $this->reload();
        $this->statusMessage = 'Purchase order cancelled.';
    }

    /**
     * Receive everything outstanding into a location: adds stock and rolls the
     * weighted-average cost (via StockManager), recording supplier + unit cost.
     */
    public function receive(StockManager $stock): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-purchasing'), 403);

        $this->validate(['receiveLocationId' => ['required', 'integer', 'exists:locations,id']]);

        $location = Location::findOrFail($this->receiveLocationId);
        $supplier = $this->po->supplier;

        foreach ($this->po->lines as $line) {
            $remaining = (float) $line->quantity - (float) $line->received_quantity;
            if ($remaining > 0 && $line->item) {
                $stock->receive($line->item, $location, $remaining, auth()->user(), 'PO '.$this->po->number, $supplier, (float) $line->unit_cost);
                $line->update(['received_quantity' => $line->quantity]);
            }
        }

        $this->po->markReceived();
        $this->reload();
        $this->statusMessage = 'Received into stock at '.$location->name.'.';
    }

    public function with(): array
    {
        return ['locations' => Location::where('is_active', true)->orderBy('name')->get()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('purchasing.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to purchase orders</a>
            @if ($po->isDraft())
                <a href="{{ route('purchasing.edit', $po->id) }}" wire:navigate
                   class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
            @endif
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ $po->number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $po->supplier->name }}</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $po->status->badgeClasses() }}">
                    {{ $po->status->label() }}
                </span>
            </div>

            <div class="mt-4 overflow-hidden border border-gray-100 rounded-md">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Unit cost</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($po->lines as $line)
                            <tr wire:key="pol-{{ $line->id }}">
                                <td class="px-4 py-2 text-gray-900">{{ $line->description }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format((float) $line->unit_cost, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format($line->lineTotal(), 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->received_quantity, 2), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2 font-semibold text-gray-800" colspan="3">Total</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums">${{ number_format($po->total(), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($po->notes)
                <div class="mt-4 text-sm">
                    <p class="text-gray-500">Notes</p>
                    <p class="text-gray-700 whitespace-pre-line">{{ $po->notes }}</p>
                </div>
            @endif
        </div>

        @can('manage-purchasing')
            @if (in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true))
                <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex flex-wrap gap-3">
                        @if ($po->status === PurchaseOrderStatus::Draft)
                            <x-primary-button wire:click="markOrdered" type="button">Mark as ordered</x-primary-button>
                        @endif
                        <x-danger-button wire:click="cancel" type="button">Cancel PO</x-danger-button>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <h2 class="font-medium text-gray-800">Receive into stock</h2>
                        <div class="mt-2 flex flex-wrap items-end gap-3">
                            <div>
                                <x-input-label for="receiveLocationId" value="Location" />
                                <select id="receiveLocationId" wire:model="receiveLocationId"
                                    class="block mt-1 w-64 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Select —</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('receiveLocationId')" class="mt-1" />
                            </div>
                            <x-primary-button wire:click="receive" type="button">Receive all</x-primary-button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Adds stock and updates each item's average cost.</p>
                    </div>
                </div>
            @endif
        @endcan
    </div>
</div>
