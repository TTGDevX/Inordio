@extends('layouts.print')

@section('title', 'Quote '.$quote->number)

@section('content')
    <div class="flex items-start justify-between border-b border-gray-200 pb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ tenant('name') ?? config('app.name') }}</h1>
            <p class="text-sm text-gray-500">Quote</p>
        </div>
        <div class="text-right text-sm">
            <p class="text-lg font-semibold font-mono text-gray-900">{{ $quote->number }}</p>
            @if ($quote->valid_until)
                <p class="text-gray-500">Valid until {{ $quote->valid_until->format('M j, Y') }}</p>
            @endif
            <p class="mt-1 font-medium text-gray-700">{{ $quote->status->label() }}</p>
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs uppercase tracking-wide text-gray-400">Prepared for</p>
        <p class="font-medium text-gray-900">{{ $quote->customer->name }}</p>
        @if ($quote->customer->contact_name)
            <p class="text-sm text-gray-600">{{ $quote->customer->contact_name }}</p>
        @endif
        @if ($quote->customer->email)
            <p class="text-sm text-gray-600">{{ $quote->customer->email }}</p>
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
            @foreach ($quote->lines as $line)
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
            <tr class="border-t border-gray-300">
                <td class="py-2 font-semibold text-gray-900">Subtotal (pre-tax)</td>
                <td class="py-2 text-right font-semibold tabular-nums">${{ number_format($quote->subtotal(), 2) }}</td>
            </tr>
        </table>
    </div>

    <p class="mt-2 text-right text-xs text-gray-400">Applicable taxes calculated at invoicing.</p>

    @if ($quote->notes)
        <div class="mt-8 border-t border-gray-200 pt-4 text-sm">
            <p class="text-gray-500">Notes</p>
            <p class="text-gray-700 whitespace-pre-line">{{ $quote->notes }}</p>
        </div>
    @endif
@endsection
