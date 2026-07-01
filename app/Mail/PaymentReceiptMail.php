<?php

namespace App\Mail;

use App\Models\CompanySetting;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public Payment $payment, public CompanySetting $company) {}

    /**
     * @return array<string, string>
     */
    private function vars(): array
    {
        return [
            'company_name' => $this->company->legal_name ?: (tenant('name') ?? config('app.name')),
            'customer_name' => $this->invoice->customer->contact_name ?: $this->invoice->customer->name,
            'invoice_number' => (string) $this->invoice->number,
            'payment_amount' => '$'.number_format((float) $this->payment->amount, 2),
            'payment_method' => $this->payment->method?->label() ?? '',
            'payment_date' => $this->payment->paid_at?->format('M j, Y') ?? now()->format('M j, Y'),
            'invoice_balance' => '$'.number_format($this->invoice->balance(), 2),
        ];
    }

    public function envelope(): Envelope
    {
        $fromName = $this->company->mail_from_name
            ?: ($this->company->legal_name ?: (tenant('name') ?? config('app.name')));
        $fromAddress = $this->company->mail_from_address ?: config('mail.from.address');

        $template = DocumentTemplate::resolve('payment_receipt');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: DocumentTemplate::render($template['subject'], $this->vars()),
        );
    }

    public function content(): Content
    {
        $template = DocumentTemplate::resolve('payment_receipt');

        return new Content(view: 'emails.receipt', with: [
            'invoice' => $this->invoice,
            'payment' => $this->payment,
            'co' => $this->company,
            'bodyMessage' => DocumentTemplate::render($template['body'], $this->vars()),
        ]);
    }
}
