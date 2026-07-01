<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote, public CompanySetting $company) {}

    /**
     * @return array<string, string>
     */
    private function vars(): array
    {
        $company = $this->company->legal_name ?: (tenant('name') ?? config('app.name'));

        return [
            'company_name' => $company,
            'customer_name' => $this->quote->customer->contact_name ?: $this->quote->customer->name,
            'quote_number' => (string) $this->quote->number,
            'quote_total' => '$'.number_format($this->quote->subtotal(), 2),
            'quote_valid_until' => $this->quote->valid_until?->format('M j, Y') ?? '',
        ];
    }

    public function envelope(): Envelope
    {
        $fromName = $this->company->mail_from_name
            ?: ($this->company->legal_name ?: (tenant('name') ?? config('app.name')));
        $fromAddress = $this->company->mail_from_address ?: config('mail.from.address');

        $template = \App\Models\DocumentTemplate::resolve('quote_email');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: \App\Models\DocumentTemplate::render($template['subject'], $this->vars()),
        );
    }

    public function content(): Content
    {
        $template = \App\Models\DocumentTemplate::resolve('quote_email');

        return new Content(view: 'emails.quote', with: [
            'quote' => $this->quote,
            'co' => $this->company,
            'bodyMessage' => \App\Models\DocumentTemplate::render($template['body'], $this->vars()),
        ]);
    }
}
