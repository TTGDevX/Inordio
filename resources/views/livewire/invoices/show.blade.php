<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Invoice $invoice;

    public string $payAmount = '';
    public string $payMethod = 'etransfer';
    public string $payReference = '';
    public string $payNote = '';
    public string $statusMessage = '';

    public function mount(string $invoiceId): void
    {
        $this->invoice = Invoice::with(['customer', 'job', 'lines.item', 'payments'])->findOrFail($invoiceId);
        $this->payAmount = number_format($this->invoice->balance(), 2, '.', '');
    }

    private function reload(): void
    {
        $this->invoice = Invoice::with(['customer', 'job', 'lines.item', 'payments'])->findOrFail($this->invoice->id);
        $this->payAmount = number_format($this->invoice->balance(), 2, '.', '');
    }

    public function markSent(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);
        $this->invoice->markSent();
        $this->reload();
        $this->statusMessage = 'Invoice marked as sent.';
    }

    public function voidInvoice(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-invoices'), 403);
        $this->invoice->voidInvoice();
        $this->reload();
        $this->statusMessage = 'Invoice voided.';
    }

    public function recordPayment(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('record-payments'), 403);

        $validated = $this->validate([
            'payAmount' => ['required', 'numeric', 'gt:0'],
            'payMethod' => ['required', 'in:'.implode(',', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'payReference' => ['nullable', 'string', 'max:255'],
            'payNote' => ['nullable', 'string', 'max:255'],
        ]);

        $this->invoice->recordPayment(
            (float) $validated['payAmount'],
            PaymentMethod::from($validated['payMethod']),
            $this->payReference ?: null,
            $this->payNote ?: null,
        );

        $this->reset(['payReference', 'payNote']);
        $this->reload();
        $this->statusMessage = 'Payment recorded.';
    }

    public function with(): array
    {
        return ['methods' => PaymentMethod::options()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('invoices.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to invoices</a>
            <a href="{{ route('invoices.print', $invoice->id) }}" target="_blank"
               class="text-sm text-indigo-600 hover:text-indigo-800">Print / PDF</a>
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ $invoice->number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $invoice->customer->name }}
                        @if ($invoice->job)
                            · from job <a href="{{ route('jobs.show', $invoice->job->id) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 font-mono">{{ $invoice->job->number }}</a>
                        @endif
                    </p>
                    <p class="text-xs text-gray-400">
                        Issued {{ $invoice->issued_at?->format('M j, Y') ?? '—' }}
                        @if ($invoice->due_at) · due {{ $invoice->due_at->format('M j, Y') }} @endif
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invoice->status->badgeClasses() }}">
                    {{ $invoice->status->label() }}
                </span>
            </div>

            <div class="mt-4 overflow-hidden border border-gray-100 rounded-md">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Unit</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($invoice->lines as $line)
                            <tr wire:key="il-{{ $line->id }}">
                                <td class="px-4 py-2 text-gray-900">{{ $line->description }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format($line->lineTotal(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 text-sm">
                        <tr>
                            <td class="px-4 py-1 text-gray-600" colspan="3">Subtotal</td>
                            <td class="px-4 py-1 text-right tabular-nums">${{ number_format($invoice->subtotal(), 2) }}</td>
                        </tr>
                        @forelse ($invoice->tax_breakdown ?? [] as $tax)
                            <tr>
                                <td class="px-4 py-1 text-gray-600" colspan="3">{{ $tax['label'] }}</td>
                                <td class="px-4 py-1 text-right tabular-nums">${{ number_format((float) $tax['amount'], 2) }}</td>
                            </tr>
                        @empty
                            @if ($invoice->tax_exempt)
                                <tr><td class="px-4 py-1 text-gray-400" colspan="4">Tax exempt</td></tr>
                            @endif
                        @endforelse
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-2 font-semibold text-gray-800" colspan="3">Total</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-900 tabular-nums">${{ number_format($invoice->total(), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-1 text-gray-600" colspan="3">Paid</td>
                            <td class="px-4 py-1 text-right tabular-nums">${{ number_format($invoice->amountPaid(), 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-1 font-medium text-gray-800" colspan="3">Balance</td>
                            <td class="px-4 py-1 text-right font-semibold tabular-nums {{ $invoice->balance() > 0 ? 'text-gray-900' : 'text-green-600' }}">${{ number_format($invoice->balance(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @can('manage-invoices')
            @if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true))
                <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
                    @if ($invoice->status === InvoiceStatus::Draft)
                        <x-primary-button wire:click="markSent" type="button">Mark as sent</x-primary-button>
                    @endif
                    <x-danger-button wire:click="voidInvoice" type="button">Void</x-danger-button>
                </div>
            @endif
        @endcan

        {{-- Payments --}}
        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="font-medium text-gray-800">Payments</h2>

            @if ($invoice->payments->isEmpty())
                <p class="text-sm text-gray-500">No payments recorded.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($invoice->payments as $payment)
                        <li wire:key="pay-{{ $payment->id }}" class="flex items-center justify-between py-2">
                            <div>
                                <span class="text-gray-900 tabular-nums">${{ number_format((float) $payment->amount, 2) }}</span>
                                <span class="text-gray-500">· {{ $payment->method->label() }}</span>
                                @if ($payment->reference)
                                    <span class="text-gray-400">· {{ $payment->reference }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $payment->paid_at?->format('M j, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @can('record-payments')
                @if ($invoice->balance() > 0 && $invoice->status !== InvoiceStatus::Void)
                    <form wire:submit="recordPayment" class="border-t border-gray-100 pt-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <div>
                            <x-input-label for="payAmount" value="Amount" />
                            <x-text-input id="payAmount" wire:model="payAmount" type="number" step="0.01" min="0" class="block mt-1 w-full text-sm" />
                            <x-input-error :messages="$errors->get('payAmount')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="payMethod" value="Method" />
                            <select id="payMethod" wire:model="payMethod"
                                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($methods as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="payReference" value="Reference" />
                            <x-text-input id="payReference" wire:model="payReference" class="block mt-1 w-full text-sm" placeholder="Cheque #, ref…" />
                        </div>
                        <div>
                            <x-primary-button>Record payment</x-primary-button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</div>
