<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
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
});

require __DIR__.'/auth.php';
