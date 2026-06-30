<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\StockLevel;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('view-reports'), 403);
    }

    public function with(): array
    {
        // --- Accounts receivable aging (unpaid sent invoices, by days overdue) ---
        $buckets = ['Current' => [], '1–30 days' => [], '31–60 days' => [], '61+ days' => []];

        Invoice::where('status', InvoiceStatus::Sent)
            ->with(['lines', 'payments', 'customer'])
            ->get()
            ->each(function (Invoice $invoice) use (&$buckets) {
                if ($invoice->balance() <= 0) {
                    return;
                }
                $days = ($invoice->due_at && $invoice->due_at->isPast())
                    ? (int) abs($invoice->due_at->diffInDays(now()))
                    : 0;
                $key = $days <= 0 ? 'Current' : ($days <= 30 ? '1–30 days' : ($days <= 60 ? '31–60 days' : '61+ days'));
                $buckets[$key][] = $invoice->balance();
            });

        $arRows = [];
        foreach ($buckets as $label => $vals) {
            $arRows[$label] = Money::sum($vals);
        }

        // --- Inventory valuation (quantity on hand × cost / price) ---
        $levels = StockLevel::with('item')->get();
        $costValue = Money::sum($levels->map(fn (StockLevel $l) => Money::round((float) $l->quantity * (float) ($l->item->cost ?? 0))));
        $retailValue = Money::sum($levels->map(fn (StockLevel $l) => Money::round((float) $l->quantity * (float) ($l->item->price ?? 0))));

        return [
            'arRows' => $arRows,
            'arTotal' => Money::sum(array_values($arRows)),
            'costValue' => $costValue,
            'retailValue' => $retailValue,
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <h1 class="text-xl font-semibold text-gray-800">Reports</h1>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="font-medium text-gray-800">Accounts receivable (outstanding)</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($arRows as $label => $amount)
                        <tr>
                            <td class="px-5 py-3 text-gray-700">{{ $label }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-gray-900">${{ number_format($amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50">
                        <td class="px-5 py-3 font-semibold text-gray-800">Total outstanding</td>
                        <td class="px-5 py-3 text-right font-semibold tabular-nums text-gray-900">${{ number_format($arTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-5">
                <p class="text-sm text-gray-500">Inventory at cost</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">${{ number_format($costValue, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-5">
                <p class="text-sm text-gray-500">Inventory at retail</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">${{ number_format($retailValue, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-5">
                <p class="text-sm text-gray-500">Potential margin</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">${{ number_format(\App\Support\Money::round($retailValue - $costValue), 2) }}</p>
            </div>
        </div>
    </div>
</div>
