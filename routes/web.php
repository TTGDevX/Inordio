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
    Volt::route('inventory', 'inventory.index')->name('inventory.index');
    // The {item} id is resolved inside the component (after the tenant
    // middleware has initialized tenancy) rather than via implicit route-model
    // binding, so the BelongsToTenant global scope always applies. See
    // resources/views/livewire/inventory/show.blade.php.
    Volt::route('inventory/{item}', 'inventory.show')->name('inventory.show');
});

require __DIR__.'/auth.php';
