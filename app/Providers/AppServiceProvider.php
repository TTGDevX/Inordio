<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineRoleGates();
    }

    /**
     * Role-based abilities, keyed to the UserRole hierarchy (see the enum's
     * rank()). Owner(5) > Admin(4) > Office(3) > Technician(2) > Viewer(1).
     *
     *  - move-stock        Technician+ — techs pick/transfer/consume in the field
     *  - manage-inventory  Office+      — office staff maintain the item catalogue
     *  - manage-locations  Office+      — and the warehouse/truck/site list
     *
     * Viewers (accountants, partners) get read-only access — no ability granted.
     */
    private function defineRoleGates(): void
    {
        $atLeast = fn (UserRole $min) => fn (?User $user) => $user?->role?->isAtLeast($min) ?? false;

        Gate::define('move-stock', $atLeast(UserRole::Technician));
        Gate::define('manage-inventory', $atLeast(UserRole::Office));
        Gate::define('manage-locations', $atLeast(UserRole::Office));
        Gate::define('manage-customers', $atLeast(UserRole::Office));
    }
}
