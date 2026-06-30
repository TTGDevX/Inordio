<?php

namespace App\Services;

use App\Enums\Province;

/**
 * Computes Canadian sales tax for a pre-tax amount based on the customer's
 * province (PROJECT-BRIEF.md §7). Rates come from config/taxes.php so they can
 * be updated without touching logic. Components are non-compounded — each is
 * applied to the subtotal, matching current GST/HST/PST/QST rules.
 */
class TaxCalculator
{
    /**
     * @return array{lines: array<int, array{label: string, rate: float, amount: float}>, total: float}
     */
    public function calculate(?Province $province, float $amount, bool $exempt = false): array
    {
        if ($exempt || $province === null) {
            return ['lines' => [], 'total' => 0.0];
        }

        $components = config('taxes.rates.'.$province->value, []);

        $lines = [];

        foreach ($components as $component) {
            $rate = (float) $component['rate'];

            $lines[] = [
                'label' => $component['label'].' ('.rtrim(rtrim(number_format($rate, 3), '0'), '.').'%)',
                'rate' => $rate,
                'amount' => \App\Support\Money::round($amount * $rate / 100),
            ];
        }

        return ['lines' => $lines, 'total' => \App\Support\Money::sum(array_column($lines, 'amount'))];
    }
}
