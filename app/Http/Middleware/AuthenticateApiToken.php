<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an API request from a first-party bearer token and initializes
 * that token's tenant. The token lookup is deliberately tenant-agnostic (raw
 * query, no global scope) because no tenant is initialized yet — the token
 * itself tells us which tenant to load. From there the normal BelongsToTenant
 * scoping isolates all data.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $row = DB::table('api_tokens')->where('token_hash', ApiToken::hashFor($bearer))->first();

        if (! $row) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        $tenant = Tenant::find($row->tenant_id);
        if (! $tenant) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        tenancy()->initialize($tenant);

        $user = User::find($row->user_id);
        if (! $user) {
            tenancy()->end();

            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        auth()->setUser($user);
        DB::table('api_tokens')->where('id', $row->id)->update(['last_used_at' => now()]);

        return $next($request);
    }
}
