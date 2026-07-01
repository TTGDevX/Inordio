@php($accent = $co->accent_color ?: '#111827')
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
    body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
    h1 { margin: 0; font-size: 20px; color: {{ $accent }}; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b7280; }
    .right { text-align: right; }
    .items th { text-align: left; border-bottom: 1px solid #d1d5db; padding: 6px 4px; font-size: 11px; color: #6b7280; text-transform: uppercase; }
    .items td { padding: 6px 4px; border-bottom: 1px solid #f0f0f0; }
    .totals td { padding: 4px 4px; }
    .totals .grand { font-weight: bold; border-top: 1px solid #d1d5db; }
</style></head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}</h1>
                <div class="muted">
                    @foreach (array_filter([$co->address_line1, trim(($co->city ?? '').' '.($co->province ?? '').' '.($co->postal_code ?? '')), $co->phone, $co->email, $co->website]) as $l)
                        {{ $l }}<br>
                    @endforeach
                    @if ($co->show_tax_number && $co->tax_number)GST/HST: {{ $co->tax_number }}@endif
                </div>
            </td>
            <td class="right">
                <div style="font-size:16px; font-weight:bold;">INVOICE</div>
                <div class="muted">{{ $invoice->number }}</div>
                <div class="muted">Issued {{ $invoice->issued_at?->format('M j, Y') }}</div>
                @if ($invoice->due_at)<div class="muted">Due {{ $invoice->due_at->format('M j, Y') }}</div>@endif
            </td>
        </tr>
    </table>

    <p style="margin-top:16px;"><span class="muted">Bill to</span><br><strong>{{ $invoice->customer->name }}</strong></p>

    <table class="items" style="margin-top:8px;">
        <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                    <td class="right">${{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="right">${{ number_format($line->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:12px; width:40%; margin-left:60%;">
        <tr><td class="muted">Subtotal</td><td class="right">${{ number_format($invoice->subtotal(), 2) }}</td></tr>
        @foreach (($invoice->tax_breakdown ?? []) as $tax)
            <tr><td class="muted">{{ $tax['label'] }}</td><td class="right">${{ number_format((float) $tax['amount'], 2) }}</td></tr>
        @endforeach
        <tr class="grand"><td>Total</td><td class="right">${{ number_format($invoice->total(), 2) }}</td></tr>
        <tr><td class="muted">Paid</td><td class="right">${{ number_format($invoice->amountPaid(), 2) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="right">${{ number_format($invoice->balance(), 2) }}</td></tr>
    </table>

    @if ($co->invoice_footer)
        <p class="muted" style="margin-top:24px; white-space:pre-line;">{{ $co->invoice_footer }}</p>
    @endif

    @if ($co->document_terms)
        <p style="margin-top:16px; font-size:11px;"><strong>Terms &amp; conditions</strong><br><span class="muted" style="white-space:pre-line;">{{ $co->document_terms }}</span></p>
    @endif
</body>
</html>
