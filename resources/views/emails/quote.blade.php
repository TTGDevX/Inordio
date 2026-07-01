<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">
    <h2 style="margin: 0 0 4px; color: {{ $co->accent_color ?: '#111827' }};">
        {{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}
    </h2>

    <div style="font-size: 14px; line-height: 1.5;">{!! nl2br(e($bodyMessage ?? '')) !!}</div>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
        <tr>
            <td style="padding: 6px 0; font-weight: bold;">Subtotal (pre-tax)</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold;">${{ number_format($quote->subtotal(), 2) }}</td>
        </tr>
    </table>

    @if ($quote->valid_until)
        <p style="font-size: 14px;">Valid until {{ $quote->valid_until->format('M j, Y') }}.</p>
    @endif

    @if ($co->invoice_footer)
        <p style="color: #6b7280; font-size: 13px; white-space: pre-line;">{{ $co->invoice_footer }}</p>
    @endif

    <p style="color: #9ca3af; font-size: 12px; margin-top: 24px;">We look forward to working with you.</p>
</div>
