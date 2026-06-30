<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public CompanySetting $company) {}

    public function envelope(): Envelope
    {
        $from = $this->company->legal_name ?: (tenant('name') ?? config('app.name'));

        return new Envelope(subject: 'Invoice '.$this->invoice->number.' from '.$from);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice', with: [
            'invoice' => $this->invoice,
            'co' => $this->company,
        ]);
    }
}
