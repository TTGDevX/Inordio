<?php

namespace App\Enums;

/**
 * Canadian provinces and territories. Stored by code on the customer; this is
 * the customer-side tax identity. The actual tax type/rate tables (brief §7)
 * are deliberately deferred to the Phase 5 tax work, where they'll live as
 * verified data rather than hardcoded constants (rates change — e.g. NS).
 */
enum Province: string
{
    case AB = 'AB';
    case BC = 'BC';
    case MB = 'MB';
    case NB = 'NB';
    case NL = 'NL';
    case NS = 'NS';
    case NT = 'NT';
    case NU = 'NU';
    case ON = 'ON';
    case PE = 'PE';
    case QC = 'QC';
    case SK = 'SK';
    case YT = 'YT';

    public function label(): string
    {
        return match ($this) {
            self::AB => 'Alberta',
            self::BC => 'British Columbia',
            self::MB => 'Manitoba',
            self::NB => 'New Brunswick',
            self::NL => 'Newfoundland and Labrador',
            self::NS => 'Nova Scotia',
            self::NT => 'Northwest Territories',
            self::NU => 'Nunavut',
            self::ON => 'Ontario',
            self::PE => 'Prince Edward Island',
            self::QC => 'Quebec',
            self::SK => 'Saskatchewan',
            self::YT => 'Yukon',
        };
    }

    /**
     * Options for a select control, ordered by label.
     *
     * @return array<string, string>  code => label
     */
    public static function options(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $a->label() <=> $b->label());

        return array_reduce($cases, function (array $carry, self $case) {
            $carry[$case->value] = $case->label();

            return $carry;
        }, []);
    }
}
