<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">
    <h2 style="margin: 0 0 8px; color: {{ $co->accent_color ?: '#111827' }};">
        {{ $co->legal_name ?: (tenant('name') ?? config('app.name')) }}
    </h2>
    <p>Your Inordio email is configured correctly — this test message was delivered through your outgoing mail settings.</p>
    <p style="color: #9ca3af; font-size: 12px; margin-top: 24px;">Sent from Inordio.</p>
</div>
