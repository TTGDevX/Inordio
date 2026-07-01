<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">
    <h2 style="margin: 0 0 8px; color: {{ $co->accent_color ?: '#111827' }};">
        {{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}
    </h2>

    <div style="font-size: 14px; line-height: 1.5;">{!! nl2br(e($bodyMessage ?? '')) !!}</div>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
        <tr>
            <td style="padding: 4px 0; color: #6b7280;">Invoice</td>
            <td style="padding: 4px 0; text-align: right;">{{ $invoice->number }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #6b7280;">Payment received</td>
            <td style="padding: 4px 0; text-align: right;">${{ number_format((float) $payment->amount, 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-top: 1px solid #e5e7eb;">Remaining balance</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold; border-top: 1px solid #e5e7eb;">${{ number_format($invoice->balance(), 2) }}</td>
        </tr>
    </table>

    @if ($co->invoice_footer)
        <p style="color: #6b7280; font-size: 13px; white-space: pre-line;">{{ $co->invoice_footer }}</p>
    @endif

    <p style="color: #9ca3af; font-size: 12px; margin-top: 24px;">Thank you for your payment.</p>
</div>
