<?php

namespace App\Enums;

/**
 * Canadian-first payment methods (brief §7). These record how a payment was
 * received — gateway integrations (Stripe, Square, Rotessa) come later.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case ETransfer = 'etransfer';
    case Eft = 'eft';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::ETransfer => 'Interac e-Transfer',
            self::Eft => 'EFT / direct deposit',
            self::Card => 'Credit/Debit card',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $carry, self $case) {
            $carry[$case->value] = $case->label();

            return $carry;
        }, []);
    }
}
