<?php

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Quote;
use App\Models\StockLevel;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        $unpaid = Invoice::whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Sent])
            ->with(['lines', 'payments'])->get();

        $lowStock = StockLevel::whereNotNull('min_quantity')
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->with(['item', 'location'])->orderBy('quantity')->get();

        return [
            'quotesAwaiting' => Quote::where('status', QuoteStatus::Sent)->count(),
            'upcomingJobs' => Job::where('status', JobStatus::Scheduled)
                ->with('customer')->orderBy('scheduled_at')->take(5)->get(),
            'scheduledCount' => Job::where('status', JobStatus::Scheduled)->count(),
            'outstanding' => round($unpaid->sum(fn (Invoice $i) => $i->balance()), 2),
            'outstandingCount' => $unpaid->filter(fn (Invoice $i) => $i->balance() > 0)->count(),
            'lowStock' => $lowStock,
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('quotes.index') }}" wire:navigate class="block bg-white rounded-lg shadow-sm p-5 hover:shadow">
                <p class="text-sm text-gray-500">Quotes awaiting approval</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums">{{ $quotesAwaiting }}</p>
            </a>
            <a href="{{ route('jobs.schedule') }}" wire:navigate class="block bg-white rounded-lg shadow-sm p-5 hover:shadow">
                <p class="text-sm text-gray-500">Jobs scheduled</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums">{{ $scheduledCount }}</p>
            </a>
            <a href="{{ route('invoices.index') }}" wire:navigate class="block bg-white rounded-lg shadow-sm p-5 hover:shadow">
                <p class="text-sm text-gray-500">Outstanding</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 tabular-nums">${{ number_format($outstanding, 2) }}</p>
                <p class="text-xs text-gray-400">{{ $outstandingCount }} unpaid {{ $outstandingCount === 1 ? 'invoice' : 'invoices' }}</p>
            </a>
            <a href="{{ route('inventory.reorder') }}" wire:navigate class="block bg-white rounded-lg shadow-sm p-5 hover:shadow">
                <p class="text-sm text-gray-500">Low stock</p>
                <p class="mt-1 text-3xl font-semibold {{ $lowStock->isNotEmpty() ? 'text-red-600' : 'text-gray-900' }} tabular-nums">{{ $lowStock->count() }}</p>
                @if ($lowStock->isNotEmpty())
                    <p class="text-xs text-red-500">needs reorder</p>
                @endif
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Upcoming jobs</h2>
                    <a href="{{ route('jobs.schedule') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-800">Schedule</a>
                </div>
                @if ($upcomingJobs->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">Nothing scheduled.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($upcomingJobs as $job)
                            <li class="px-5 py-3">
                                <a href="{{ route('jobs.show', $job->id) }}" wire:navigate class="flex items-center justify-between hover:opacity-80">
                                    <div>
                                        <p class="text-gray-900">{{ $job->title }}</p>
                                        <p class="text-xs text-gray-500"><span class="font-mono">{{ $job->number }}</span> · {{ $job->customer->name }}</p>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ $job->scheduled_at?->format('M j') ?? '—' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Low stock</h2>
                    @can('manage-inventory')
                        <a href="{{ route('inventory.reorder') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-800">Reorder</a>
                    @endcan
                </div>
                @if ($lowStock->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">Everything's above its reorder point.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($lowStock as $level)
                            <li class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-gray-900">{{ $level->item?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ $level->location?->name }}</p>
                                </div>
                                <span class="text-sm text-red-600 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $level->quantity, 2), '0'), '.') }}
                                    / {{ rtrim(rtrim(number_format((float) $level->min_quantity, 2), '0'), '.') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
