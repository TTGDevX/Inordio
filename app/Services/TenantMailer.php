<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Config;

/**
 * Resolves which Laravel mailer to send through for the current tenant.
 *
 * If the tenant has configured its own SMTP (Company Settings → Email), we
 * register a runtime "tenant" mailer from those credentials and send through
 * it; otherwise we fall back to the app's default mailer. Either way we return
 * the from-address/name to stamp on the message.
 */
class TenantMailer
{
    /**
     * @return array{mailer: string, from_address: string, from_name: string}
     */
    public static function resolve(?CompanySetting $company = null): array
    {
        $company ??= CompanySetting::current();

        $fromAddress = $company->mail_from_address ?: (string) Config::get('mail.from.address');
        $fromName = $company->mail_from_name
            ?: ($company->legal_name ?: (string) Config::get('mail.from.name'));

        if ($company->hasCustomMailer()) {
            Config::set('mail.mailers.tenant', [
                'transport' => 'smtp',
                'host' => $company->mail_host,
                'port' => $company->mail_port ?: 587,
                'encryption' => $company->mail_encryption ?: null,
                'username' => $company->mail_username,
                'password' => $company->mail_password, // decrypted by the model cast
                'timeout' => 10,
            ]);

            return ['mailer' => 'tenant', 'from_address' => $fromAddress, 'from_name' => $fromName];
        }

        return [
            'mailer' => (string) Config::get('mail.default'),
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ];
    }
}
