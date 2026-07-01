<?php

namespace App\Mail;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CompanySetting $company) {}

    public function envelope(): Envelope
    {
        $fromName = $this->company->mail_from_name
            ?: ($this->company->legal_name ?: (tenant('name') ?? config('app.name')));
        $fromAddress = $this->company->mail_from_address ?: config('mail.from.address');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Inordio test email',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test', with: ['co' => $this->company]);
    }
}
