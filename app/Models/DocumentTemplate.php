<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Per-tenant, per-document email templates (subject + message body). Bodies use
 * a SAFE {{ token }} syntax — tokens are looked up in a whitelisted variable map
 * and substituted with a plain string. User input is NEVER rendered as Blade or
 * PHP, so there is no code-execution surface.
 */
#[Fillable(['type', 'subject', 'body'])]
class DocumentTemplate extends Model
{
    use BelongsToTenant;

    public const TYPES = ['invoice_email', 'quote_email'];

    /**
     * Tokens available to each template type (for the editor legend).
     *
     * @return array<int, string>
     */
    public static function tokens(string $type): array
    {
        return match ($type) {
            'invoice_email' => ['company_name', 'customer_name', 'invoice_number', 'invoice_total', 'invoice_balance', 'invoice_due_date'],
            'quote_email' => ['company_name', 'customer_name', 'quote_number', 'quote_total', 'quote_valid_until'],
            default => ['company_name', 'customer_name'],
        };
    }

    /**
     * The built-in default template for a type.
     *
     * @return array{subject: string, body: string}
     */
    public static function defaults(string $type): array
    {
        return match ($type) {
            'invoice_email' => [
                'subject' => 'Invoice {{ invoice_number }} from {{ company_name }}',
                'body' => "Hi {{ customer_name }},\n\nPlease find invoice {{ invoice_number }} for {{ invoice_total }} summarized below. The balance due is {{ invoice_balance }}, due {{ invoice_due_date }}.\n\nThank you for your business.",
            ],
            'quote_email' => [
                'subject' => 'Quote {{ quote_number }} from {{ company_name }}',
                'body' => "Hi {{ customer_name }},\n\nHere is quote {{ quote_number }} for {{ quote_total }}, valid until {{ quote_valid_until }}.\n\nWe'd be glad to answer any questions.",
            ],
            default => ['subject' => '', 'body' => ''],
        };
    }

    /**
     * The tenant's effective template for a type (saved values or defaults).
     *
     * @return array{subject: string, body: string}
     */
    public static function resolve(string $type): array
    {
        $saved = static::where('type', $type)->first();
        $defaults = static::defaults($type);

        return [
            'subject' => $saved?->subject ?: $defaults['subject'],
            'body' => $saved?->body ?: $defaults['body'],
        ];
    }

    /**
     * Substitute whitelisted {{ token }}s. Unknown tokens resolve to ''. Nothing
     * is evaluated — this is a plain string replacement, safe for user input.
     *
     * @param  array<string, string>  $vars
     */
    public static function render(string $text, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/',
            fn (array $m) => (string) ($vars[$m[1]] ?? ''),
            $text
        );
    }
}
