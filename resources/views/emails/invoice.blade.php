<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">
    <h2 style="margin: 0 0 4px; color: {{ $co->accent_color ?: '#111827' }};">
        {{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}
    </h2>

    <p>Hi {{ $invoice->customer->contact_name ?: $invoice->customer->name }},</p>
    <p>Please find your invoice <strong>{{ $invoice->number }}</strong> summarized below.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
        <tr>
            <td style="padding: 4px 0; color: #6b7280;">Total</td>
            <td style="padding: 4px 0; text-align: right;">${{ number_format($invoice->total(), 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #6b7280;">Paid</td>
            <td style="padding: 4px 0; text-align: right;">${{ number_format($invoice->amountPaid(), 2) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-top: 1px solid #e5e7eb;">Balance due</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold; border-top: 1px solid #e5e7eb;">${{ number_format($invoice->balance(), 2) }}</td>
        </tr>
    </table>

    @if ($invoice->due_at)
        <p style="font-size: 14px;">Due by {{ $invoice->due_at->format('M j, Y') }}.</p>
    @endif

    @if ($co->invoice_footer)
        <p style="color: #6b7280; font-size: 13px; white-space: pre-line;">{{ $co->invoice_footer }}</p>
    @endif

    <p style="color: #9ca3af; font-size: 12px; margin-top: 24px;">Thank you for your business.</p>
</div>
