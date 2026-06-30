<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Email payment reminders for overdue, unpaid invoices (per tenant).';

    public function handle(): int
    {
        $sent = 0;

        Tenant::all()->each(function (Tenant $tenant) use (&$sent) {
            tenancy()->initialize($tenant);

            try {
                $company = CompanySetting::current();

                Invoice::query()
                    ->where('status', InvoiceStatus::Sent)
                    ->whereNotNull('due_at')
                    ->whereDate('due_at', '<', now()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('reminder_sent_at')
                            ->orWhere('reminder_sent_at', '<', now()->subDays(3));
                    })
                    ->with(['customer', 'lines', 'payments'])
                    ->get()
                    ->each(function (Invoice $invoice) use ($company, &$sent) {
                        if ($invoice->balance() <= 0 || ! $invoice->customer->email) {
                            return;
                        }

                        Mail::to($invoice->customer->email)
                            ->send(new InvoiceMail($invoice, $company, reminder: true));

                        $invoice->reminder_sent_at = now();
                        $invoice->save();
                        $sent++;
                    });
            } finally {
                tenancy()->end();
            }
        });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
