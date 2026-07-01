<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A first-party API token (no Sanctum dependency). Only the SHA-256 hash of the
 * token is stored; the plaintext is shown once at creation and never again.
 */
#[Fillable(['user_id', 'name', 'token_hash', 'last_used_at'])]
class ApiToken extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashFor(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Issue a new token for a user. Returns the model plus the one-time plaintext
     * (prefixed for easy identification), which the caller must show immediately.
     *
     * @return array{token: ApiToken, plaintext: string}
     */
    public static function issue(User $user, string $name): array
    {
        $plaintext = 'ttg_'.Str::random(48);

        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => static::hashFor($plaintext),
        ]);

        return ['token' => $token, 'plaintext' => $plaintext];
    }
}
