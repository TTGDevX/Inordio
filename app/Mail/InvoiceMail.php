<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public CompanySetting $company, public bool $reminder = false) {}

    /**
     * @return array<string, string>
     */
    private function vars(): array
    {
        $company = $this->company->legal_name ?: (tenant('name') ?? config('app.name'));

        return [
            'company_name' => $company,
            'customer_name' => $this->invoice->customer->contact_name ?: $this->invoice->customer->name,
            'invoice_number' => (string) $this->invoice->number,
            'invoice_total' => '$'.number_format($this->invoice->total(), 2),
            'invoice_balance' => '$'.number_format($this->invoice->balance(), 2),
            'invoice_due_date' => $this->invoice->due_at?->format('M j, Y') ?? '',
        ];
    }

    public function envelope(): Envelope
    {
        $fromName = $this->company->mail_from_name
            ?: ($this->company->legal_name ?: (tenant('name') ?? config('app.name')));
        $fromAddress = $this->company->mail_from_address ?: config('mail.from.address');

        $template = \App\Models\DocumentTemplate::resolve('invoice_email');
        $subject = $this->reminder
            ? 'Payment reminder: Invoice '.$this->invoice->number
            : \App\Models\DocumentTemplate::render($template['subject'], $this->vars());

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $template = \App\Models\DocumentTemplate::resolve('invoice_email');

        return new Content(view: 'emails.invoice', with: [
            'invoice' => $this->invoice,
            'co' => $this->company,
            'reminder' => $this->reminder,
            'bodyMessage' => \App\Models\DocumentTemplate::render($template['body'], $this->vars()),
        ]);
    }
}
