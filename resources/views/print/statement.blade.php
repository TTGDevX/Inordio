@extends('layouts.print')

@section('title', 'Statement — '.$customer->name)

@section('content')
    @php
        $co = \App\Models\CompanySetting::current();
        $coAddr = array_filter([
            $co->address_line1,
            $co->address_line2,
            trim(($co->city ?? '').' '.($co->province ?? '').' '.($co->postal_code ?? '')),
            $co->phone,
            $co->email,
            $co->website,
        ]);
        $balance = \App\Support\Money::sum($invoices->map(fn ($i) => $i->balance()));
    @endphp

    <div class="flex items-start justify-between border-b border-gray-200 pb-6">
        <div>
            @if ($co->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($co->logo_path) }}" alt="logo" class="h-12 w-auto mb-2">
            @endif
            <h1 class="text-2xl font-bold text-gray-900">{{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}</h1>
            @foreach ($coAddr as $cl)
                <p class="text-xs text-gray-500">{{ $cl }}</p>
            @endforeach
            <p class="text-sm font-semibold mt-1" @if ($co->accent_color) style="color: {{ $co->accent_color }}" @endif>Account statement</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>{{ now()->format('M j, Y') }}</p>
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs uppercase tracking-wide text-gray-400">Account</p>
        <p class="font-medium text-gray-900">{{ $customer->name }}</p>
        @if ($customer->email)
            <p class="text-sm text-gray-600">{{ $customer->email }}</p>
        @endif
    </div>

    <table class="mt-8 w-full text-sm">
        <thead>
            <tr class="border-b border-gray-300 text-left text-xs uppercase tracking-wide text-gray-500">
                <th class="py-2">Invoice</th>
                <th class="py-2">Issued</th>
                <th class="py-2 text-right">Total</th>
                <th class="py-2 text-right">Paid</th>
                <th class="py-2 text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr class="border-b border-gray-100">
                    <td class="py-2 font-mono text-gray-900">{{ $invoice->number }}</td>
                    <td class="py-2 text-gray-600">{{ $invoice->issued_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="py-2 text-right tabular-nums">${{ number_format($invoice->total(), 2) }}</td>
                    <td class="py-2 text-right tabular-nums">${{ number_format($invoice->amountPaid(), 2) }}</td>
                    <td class="py-2 text-right tabular-nums">${{ number_format($invoice->balance(), 2) }}</td>
                </tr>
            @empty
                <tr><td class="py-3 text-gray-500" colspan="5">No invoices on file.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-300">
                <td class="py-2 font-semibold text-gray-900" colspan="4">Balance owing</td>
                <td class="py-2 text-right font-semibold tabular-nums text-gray-900">${{ number_format($balance, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($co->invoice_footer)
        <div class="mt-8 border-t border-gray-200 pt-4 text-sm text-gray-600 whitespace-pre-line">{{ $co->invoice_footer }}</div>
    @endif
@endsection
