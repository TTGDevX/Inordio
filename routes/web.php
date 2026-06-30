<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    // Company settings (branding / identity)
    Volt::route('settings/company', 'settings.company')->name('settings.company');

    // Reports
    Volt::route('reports', 'reports.index')->name('reports.index');

    // Locations
    Volt::route('locations', 'locations.index')->name('locations.index');

    // Customers. Static "create" before the {customerId} wildcard; param is
    // {customerId} (not {customer}) to avoid Livewire route-model binding.
    Volt::route('customers', 'customers.index')->name('customers.index');
    Volt::route('customers/create', 'customers.form')->name('customers.create');
    Volt::route('customers/{customerId}/edit', 'customers.form')->name('customers.edit');
    Volt::route('customers/{customerId}', 'customers.show')->name('customers.show');

    // Inventory items. Static segments (create) must be registered before the
    // {itemId} wildcard so "inventory/create" isn't swallowed as an id.
    Volt::route('inventory', 'inventory.index')->name('inventory.index');
    // create + edit share one form component (mount receives an optional id).
    Volt::route('inventory/create', 'inventory.form')->name('inventory.create');
    Volt::route('inventory/{itemId}/edit', 'inventory.form')->name('inventory.edit');
    // Param name is deliberately NOT "item": a name that collides with the
    // component's InventoryItem property triggers Livewire route-model binding
    // (which would also bypass the tenant scope). Passing a plain {itemId}
    // lets the component resolve it under tenancy in mount(). See
    // resources/views/livewire/inventory/show.blade.php.
    Volt::route('inventory/{itemId}', 'inventory.show')->name('inventory.show');

    // Quotes. Static "create" before the {quoteId} wildcard; param is {quoteId}.
    Volt::route('quotes', 'quotes.index')->name('quotes.index');
    Volt::route('quotes/create', 'quotes.form')->name('quotes.create');
    Volt::route('quotes/{quoteId}/edit', 'quotes.form')->name('quotes.edit');
    Route::get('quotes/{quoteId}/print', function (string $quoteId) {
        $quote = \App\Models\Quote::with(['customer', 'lines.item'])->findOrFail($quoteId);

        return view('print.quote', ['quote' => $quote]);
    })->name('quotes.print');
    Volt::route('quotes/{quoteId}', 'quotes.show')->name('quotes.show');

    // Jobs. Static "create" before the {jobId} wildcard; param is {jobId}.
    Volt::route('jobs', 'jobs.index')->name('jobs.index');
    Volt::route('jobs/create', 'jobs.form')->name('jobs.create');
    Volt::route('jobs/{jobId}/edit', 'jobs.form')->name('jobs.edit');
    Volt::route('jobs/{jobId}', 'jobs.show')->name('jobs.show');
    Volt::route('pick-lists/{pickListId}', 'picklists.show')->name('picklists.show');

    // Invoices (created from jobs; no standalone builder yet).
    Volt::route('invoices', 'invoices.index')->name('invoices.index');
    Route::get('invoices/{invoiceId}/print', function (string $invoiceId) {
        $invoice = \App\Models\Invoice::with(['customer', 'job', 'lines.item', 'payments'])->findOrFail($invoiceId);

        return view('print.invoice', ['invoice' => $invoice]);
    })->name('invoices.print');
    Volt::route('invoices/{invoiceId}', 'invoices.show')->name('invoices.show');
});

require __DIR__.'/auth.php';
