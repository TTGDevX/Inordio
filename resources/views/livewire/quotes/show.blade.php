<?php

use App\Models\Job;
use App\Models\Quote;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Quote $quote;
    public string $statusMessage = '';

    public function mount(string $quoteId): void
    {
        $this->quote = Quote::with(['customer', 'lines.item', 'job'])->findOrFail($quoteId);
    }

    private function reload(): void
    {
        $this->quote = Quote::with(['customer', 'lines.item', 'job'])->findOrFail($this->quote->id);
    }

    /**
     * Convert this quote into a scheduled job (idempotent — one job per quote).
     */
    public function convertToJob()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        $job = $this->quote->job ?: Job::fromQuote($this->quote);

        return $this->redirect(route('jobs.show', $job->id), navigate: true);
    }

    public function send(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);
        $this->quote->markSent();
        $this->reload();
        $this->statusMessage = 'Quote marked as sent.';
    }

    public function approve(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);
        $this->quote->approve();
        $this->reload();
        $this->statusMessage = 'Quote approved.';
    }

    public function decline(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);
        $this->quote->decline();
        $this->reload();
        $this->statusMessage = 'Quote declined.';
    }

    public function emailToCustomer(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-quotes'), 403);

        if (! $this->quote->customer->email) {
            $this->addError('email', 'This customer has no email address on file.');

            return;
        }

        $company = \App\Models\CompanySetting::current();
        $mail = \App\Services\TenantMailer::resolve($company);

        \Illuminate\Support\Facades\Mail::mailer($mail['mailer'])
            ->to($this->quote->customer->email)
            ->send(new \App\Mail\QuoteMail($this->quote, $company));

        $this->statusMessage = 'Quote emailed to '.$this->quote->customer->email;
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <a href="{{ route('quotes.index') }}" wire:navigate
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to quotes</a>
            <div class="flex items-center gap-4">
                <a href="{{ route('quotes.print', $quote->id) }}" target="_blank"
                   class="text-sm text-indigo-600 hover:text-indigo-800">Print / PDF</a>
                @can('manage-quotes')
                    <button wire:click="emailToCustomer" type="button"
                        class="text-sm text-indigo-600 hover:text-indigo-800">Email to customer</button>
                @endcan
                @can('manage-quotes')
                    @if ($quote->isDraft())
                        <a href="{{ route('quotes.edit', $quote->id) }}" wire:navigate
                           class="text-sm text-indigo-600 hover:text-indigo-800">Edit quote</a>
                    @endif
                @endcan
            </div>
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ $quote->number }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $quote->customer->name }}
                        @if ($quote->valid_until)
                            · valid until {{ $quote->valid_until->format('M j, Y') }}
                        @endif
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $quote->status->badgeClasses() }}">
                    {{ $quote->status->label() }}
                </span>
            </div>

            <div class="mt-4 overflow-hidden border border-gray-100 rounded-md">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Unit</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quote->lines as $line)
                            <tr wire:key="qline-{{ $line->id }}">
                                <td class="px-4 py-2 text-gray-900">{{ $line->description }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">${{ number_format($line->lineTotal(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-700" colspan="3">Subtotal (pre-tax)</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-900 tabular-nums">${{ number_format($quote->subtotal(), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($quote->notes)
                <div class="mt-4 text-sm">
                    <p class="text-gray-500">Notes</p>
                    <p class="text-gray-900 whitespace-pre-line">{{ $quote->notes }}</p>
                </div>
            @endif
        </div>

        @can('manage-quotes')
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
                @if ($quote->status === \App\Enums\QuoteStatus::Draft)
                    <x-primary-button wire:click="send" type="button">Mark as sent</x-primary-button>
                @elseif ($quote->status === \App\Enums\QuoteStatus::Sent)
                    <x-primary-button wire:click="approve" type="button">Mark approved</x-primary-button>
                    <x-danger-button wire:click="decline" type="button">Mark declined</x-danger-button>
                @else
                    <p class="text-sm text-gray-500">This quote is {{ $quote->status->label() }}.</p>
                @endif
            </div>
        @endcan

        @if ($quote->status === \App\Enums\QuoteStatus::Approved)
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 flex items-center gap-3">
                @if ($quote->job)
                    <span class="text-sm text-gray-600">Converted to job</span>
                    <a href="{{ route('jobs.show', $quote->job->id) }}" wire:navigate
                       class="text-sm font-mono text-indigo-600 hover:text-indigo-800">{{ $quote->job->number }}</a>
                @else
                    @can('manage-jobs')
                        <x-primary-button wire:click="convertToJob" type="button">Convert to job</x-primary-button>
                    @endcan
                @endif
            </div>
        @endif
    </div>
</div>
