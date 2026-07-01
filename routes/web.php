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
    // Editable email templates (subject/body with {{ tokens }})
    Volt::route('settings/templates', 'settings.templates')->name('settings.templates');
    // Developer API tokens
    Volt::route('settings/api-tokens', 'settings.api-tokens')->name('settings.api-tokens');

    // Reports
    Volt::route('reports', 'reports.index')->name('reports.index');

    // Ops board (kiosk / wall screen — auto-refreshing jobs + picking).
    Volt::route('board', 'board')->name('board');

    // Global search (customers / jobs / invoices / inventory).
    Volt::route('search', 'search')->name('search');

    // In-app notifications.
    Volt::route('notifications', 'notifications')->name('notifications');

    // Help / documentation.
    Volt::route('help', 'help')->name('help');

    // Audit trail (Admin+)
    Volt::route('audit', 'audit.index')->name('audit.index');

    // Checklist / inspection templates (manage-jobs). Static create before wildcard.
    Volt::route('checklists', 'checklists.index')->name('checklists.index');
    Volt::route('checklists/create', 'checklists.form')->name('checklists.create');
    Volt::route('checklists/{checklistTemplateId}/edit', 'checklists.form')->name('checklists.edit');

    // Service agreements (recurring maintenance). Static create before wildcard.
    Volt::route('agreements', 'agreements.index')->name('agreements.index');
    Volt::route('agreements/create', 'agreements.form')->name('agreements.create');
    Volt::route('agreements/{serviceAgreementId}/edit', 'agreements.form')->name('agreements.edit');

    // Purchase orders. Static "create" before {poId}.
    Volt::route('purchase-orders', 'purchasing.index')->name('purchasing.index');
    Volt::route('purchase-orders/create', 'purchasing.form')->name('purchasing.create');
    Volt::route('purchase-orders/{poId}/edit', 'purchasing.form')->name('purchasing.edit');
    Volt::route('purchase-orders/{poId}', 'purchasing.show')->name('purchasing.show');

    // CSV exports (accountant / Sage handoff). Tenant-scoped by the middleware.
    Route::get('exports/customers.csv', function () {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-customers'), 403);

        $rows = \App\Models\Customer::orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Contact', 'Email', 'Phone', 'City', 'Province', 'Postal', 'Tax exempt']);
            foreach ($rows as $c) {
                fputcsv($out, [$c->name, $c->contact_name, $c->email, $c->phone, $c->city, $c->province?->value, $c->postal_code, $c->tax_exempt ? 'Yes' : 'No']);
            }
            fclose($out);
        }, 'customers.csv', ['Content-Type' => 'text/csv']);
    })->name('exports.customers');

    Route::get('exports/inventory.csv', function () {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $rows = \App\Models\InventoryItem::with('category')
            ->withSum('stockLevels as total_quantity', 'quantity')
            ->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Internal SKU', 'Vendor SKU', 'Category', 'Cost', 'Price', 'Unit', 'On hand']);
            foreach ($rows as $i) {
                fputcsv($out, [$i->name, $i->internal_sku, $i->vendor_sku, $i->category?->name, $i->cost, $i->price, $i->unit_of_measure, (float) ($i->total_quantity ?? 0)]);
            }
            fclose($out);
        }, 'inventory.csv', ['Content-Type' => 'text/csv']);
    })->name('exports.inventory');

    // Locations
    Volt::route('locations', 'locations.index')->name('locations.index');
    // Printable QR labels for bins/trucks/warehouses (scanned by the app).
    Route::get('locations/labels', function () {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $labels = \App\Models\Location::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($l) => ['payload' => 'LOC:'.$l->id, 'title' => $l->name, 'subtitle' => $l->type->label()])
            ->all();

        return view('print.labels', ['heading' => 'Location labels', 'labels' => $labels]);
    })->name('locations.labels');

    // Stock movement log (the ledger, surfaced)
    Volt::route('movements', 'movements.index')->name('movements.index');

    // Serialized assets (the nested-equipment "LEGO" model). Static "create"
    // before the {assetId} wildcard; param is {assetId} (not {asset}) so the
    // component resolves it under tenancy in mount().
    Volt::route('assets', 'assets.index')->name('assets.index');
    Volt::route('assets/create', 'assets.form')->name('assets.create');
    Volt::route('assets/{assetId}/edit', 'assets.form')->name('assets.edit');
    Route::get('assets/{assetId}/label', function (string $assetId) {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $asset = \App\Models\SerializedAsset::with('item')->findOrFail($assetId);

        return view('print.labels', ['heading' => 'Label — '.$asset->serial_number, 'labels' => [
            ['payload' => 'AST:'.$asset->serial_number, 'title' => $asset->serial_number, 'subtitle' => $asset->item?->name],
        ]]);
    })->name('assets.label');
    Volt::route('assets/{assetId}', 'assets.show')->name('assets.show');

    // Customers. Static "create" before the {customerId} wildcard; param is
    // {customerId} (not {customer}) to avoid Livewire route-model binding.
    Volt::route('customers', 'customers.index')->name('customers.index');
    Volt::route('customers/create', 'customers.form')->name('customers.create');
    Volt::route('customers/{customerId}/edit', 'customers.form')->name('customers.edit');
    Route::get('customers/{customerId}/statement', function (string $customerId) {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-customers'), 403);

        $customer = \App\Models\Customer::findOrFail($customerId);
        $invoices = $customer->invoices()
            ->where('status', '!=', 'void')
            ->with(['lines', 'payments'])
            ->orderBy('issued_at')->get();

        return view('print.statement', compact('customer', 'invoices'));
    })->name('customers.statement');
    Volt::route('customers/{customerId}', 'customers.show')->name('customers.show');

    // Inventory items. Static segments (create) must be registered before the
    // {itemId} wildcard so "inventory/create" isn't swallowed as an id.
    Volt::route('inventory', 'inventory.index')->name('inventory.index');
    // Low-stock reorder view (static — must precede the {itemId} wildcard).
    Volt::route('inventory/reorder', 'inventory.reorder')->name('inventory.reorder');
    // Printable QR label sheet for all active items (static — before {itemId}).
    Route::get('inventory/labels', function () {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $labels = \App\Models\InventoryItem::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($i) => ['payload' => 'INV:'.$i->internal_sku, 'title' => $i->name, 'subtitle' => $i->internal_sku])
            ->all();

        return view('print.labels', ['heading' => 'Item labels', 'labels' => $labels]);
    })->name('inventory.labels');
    // create + edit share one form component (mount receives an optional id).
    Volt::route('inventory/create', 'inventory.form')->name('inventory.create');
    Volt::route('inventory/{itemId}/edit', 'inventory.form')->name('inventory.edit');
    Route::get('inventory/{itemId}/label', function (string $itemId) {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-inventory'), 403);

        $item = \App\Models\InventoryItem::findOrFail($itemId);

        return view('print.labels', ['heading' => 'Label — '.$item->name, 'labels' => [
            ['payload' => 'INV:'.$item->internal_sku, 'title' => $item->name, 'subtitle' => $item->internal_sku],
        ]]);
    })->name('inventory.label');
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
    Volt::route('jobs/schedule', 'jobs.schedule')->name('jobs.schedule');
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
