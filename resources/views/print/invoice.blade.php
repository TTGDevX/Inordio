@extends('layouts.print')

@section('title', 'Invoice '.$invoice->number)

@section('content')
    @php($co = \App\Models\CompanySetting::current())
    @php($coAddr = array_filter([$co->address_line1, $co->address_line2, trim(($co->city ?? '').' '.($co->province ?? '').' '.($co->postal_code ?? '')), $co->phone, $co->email]))
    <div class="flex items-start justify-between border-b border-gray-200 pb-6">
        <div>
            @if ($co->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($co->logo_path) }}" alt="logo" class="h-12 w-auto mb-2">
            @endif
            <h1 class="text-2xl font-bold text-gray-900">{{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}</h1>
            @foreach ($coAddr as $cl)
                <p class="text-xs text-gray-500">{{ $cl }}</p>
            @endforeach
            @if ($co->tax_number)
                <p class="text-xs text-gray-500">GST/HST: {{ $co->tax_number }}</p>
            @endif
            <p class="text-sm font-semibold mt-1" @if ($co->accent_color) style="color: {{ $co->accent_color }}" @endif>Invoice</p>
        </div>
        <div class="text-right text-sm">
            <p class="text-lg font-semibold font-mono text-gray-900">{{ $invoice->number }}</p>
            <p class="text-gray-500">Issued {{ $invoice->issued_at?->format('M j, Y') ?? '—' }}</p>
            @if ($invoice->due_at)
                <p class="text-gray-500">Due {{ $invoice->due_at->format('M j, Y') }}</p>
            @endif
            <p class="mt-1 font-medium {{ $invoice->balance() <= 0 ? 'text-green-700' : 'text-gray-700' }}">
                {{ $invoice->status->label() }}
            </p>
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs uppercase tracking-wide text-gray-400">Bill to</p>
        <p class="font-medium text-gray-900">{{ $invoice->customer->name }}</p>
        @if ($invoice->customer->contact_name)
            <p class="text-sm text-gray-600">{{ $invoice->customer->contact_name }}</p>
        @endif
        @php
            $addr = array_filter([
                $invoice->customer->address_line1,
                $invoice->customer->address_line2,
                trim(($invoice->customer->city ?? '').' '.($invoice->customer->province?->value ?? '').' '.($invoice->customer->postal_code ?? '')),
            ]);
        @endphp
        @foreach ($addr as $line)
            <p class="text-sm text-gray-600">{{ $line }}</p>
        @endforeach
        @if ($invoice->customer->email)
            <p class="text-sm text-gray-600">{{ $invoice->customer->email }}</p>
        @endif
    </div>

    <table class="mt-8 w-full text-sm">
        <thead>
            <tr class="border-b border-gray-300 text-left text-xs uppercase tracking-wide text-gray-500">
                <th class="py-2">Description</th>
                <th class="py-2 text-right">Qty</th>
                <th class="py-2 text-right">Unit</th>
                <th class="py-2 text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr class="border-b border-gray-100">
                    <td class="py-2 text-gray-900">{{ $line->description }}</td>
                    <td class="py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                    <td class="py-2 text-right tabular-nums">${{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="py-2 text-right tabular-nums">${{ number_format($line->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 flex justify-end">
        <table class="text-sm w-64">
            <tr>
                <td class="py-1 text-gray-600">Subtotal</td>
                <td class="py-1 text-right tabular-nums">${{ number_format($invoice->subtotal(), 2) }}</td>
            </tr>
            @forelse ($invoice->tax_breakdown ?? [] as $tax)
                <tr>
                    <td class="py-1 text-gray-600">{{ $tax['label'] }}</td>
                    <td class="py-1 text-right tabular-nums">${{ number_format((float) $tax['amount'], 2) }}</td>
                </tr>
            @empty
                @if ($invoice->tax_exempt)
                    <tr><td class="py-1 text-gray-400" colspan="2">Tax exempt</td></tr>
                @endif
            @endforelse
            <tr class="border-t border-gray-300">
                <td class="py-2 font-semibold text-gray-900">Total</td>
                <td class="py-2 text-right font-semibold tabular-nums">${{ number_format($invoice->total(), 2) }}</td>
            </tr>
            <tr>
                <td class="py-1 text-gray-600">Paid</td>
                <td class="py-1 text-right tabular-nums">${{ number_format($invoice->amountPaid(), 2) }}</td>
            </tr>
            <tr>
                <td class="py-1 font-medium text-gray-900">Balance due</td>
                <td class="py-1 text-right font-semibold tabular-nums">${{ number_format($invoice->balance(), 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->notes)
        <div class="mt-8 border-t border-gray-200 pt-4 text-sm">
            <p class="text-gray-500">Notes</p>
            <p class="text-gray-700 whitespace-pre-line">{{ $invoice->notes }}</p>
        </div>
    @endif

    @if ($co->invoice_footer)
        <div class="mt-8 border-t border-gray-200 pt-4 text-sm text-gray-600 whitespace-pre-line">{{ $co->invoice_footer }}</div>
    @endif

    <p class="mt-10 text-center text-xs text-gray-400">Thank you for your business.</p>
@endsection
