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

    public function envelope(): Envelope
    {
        $fromName = $this->company->mail_from_name
            ?: ($this->company->legal_name ?: (tenant('name') ?? config('app.name')));
        $fromAddress = $this->company->mail_from_address ?: config('mail.from.address');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Quote '.$this->quote->number.' from '.$fromName,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quote', with: [
            'quote' => $this->quote,
            'co' => $this->company,
        ]);
    }
}
