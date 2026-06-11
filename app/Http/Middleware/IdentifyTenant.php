<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant, in order:
 *  1. TENANT_ID env pin (dedicated instance) — fails loudly if the tenant doesn't exist
 *  2. The authenticated user's tenant (shared instance)
 *  3. Subdomain identification (future — see PROJECT-BRIEF.md §3)
 *
 * The env pin scopes the instance but never replaces tenant_id checks:
 * a user from another tenant is rejected outright on a pinned instance.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $pinned = config('inordio.tenant_id');

        if ($pinned && $request->user() && $request->user()->tenant_id !== $pinned) {
            abort(403, 'This instance is dedicated to a different tenant.');
        }

        if (! tenancy()->initialized) {
            if ($pinned) {
                tenancy()->initialize(Tenant::query()->findOrFail($pinned));
            } elseif ($request->user()?->tenant_id) {
                tenancy()->initialize($request->user()->tenant);
            }
        }

        return $next($request);
    }
}
