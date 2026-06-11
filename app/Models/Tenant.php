<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Single-database tenancy: tenants share one MySQL database and rows are
 * isolated by tenant_id columns (see PROJECT-BRIEF.md §3). Name and settings
 * are stored in the virtual `data` column provided by the base model.
 */
class Tenant extends BaseTenant
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
