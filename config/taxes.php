<?php

/*
|--------------------------------------------------------------------------
| Canadian sales tax rates (data, not constants) — PROJECT-BRIEF.md §7
|--------------------------------------------------------------------------
| Verified June 2026. Rates are percentages applied to the pre-tax subtotal
| (Canadian GST/QST/PST are NOT compounded on each other since 2013).
|
| Keep this table current — provincial rates change. Most recent change:
| Nova Scotia HST 15% -> 14% effective 2025-04-01.
|
| Edit a rate here and the whole app follows; nothing else hardcodes them.
*/

return [

    // Province code (App\Enums\Province) => ordered list of tax components.
    'rates' => [
        'AB' => [['label' => 'GST', 'rate' => 5.0]],
        'BC' => [['label' => 'GST', 'rate' => 5.0], ['label' => 'PST', 'rate' => 7.0]],
        'MB' => [['label' => 'GST', 'rate' => 5.0], ['label' => 'PST', 'rate' => 7.0]],
        'NB' => [['label' => 'HST', 'rate' => 15.0]],
        'NL' => [['label' => 'HST', 'rate' => 15.0]],
        'NS' => [['label' => 'HST', 'rate' => 14.0]],
        'NT' => [['label' => 'GST', 'rate' => 5.0]],
        'NU' => [['label' => 'GST', 'rate' => 5.0]],
        'ON' => [['label' => 'HST', 'rate' => 13.0]],
        'PE' => [['label' => 'HST', 'rate' => 15.0]],
        'QC' => [['label' => 'GST', 'rate' => 5.0], ['label' => 'QST', 'rate' => 9.975]],
        'SK' => [['label' => 'GST', 'rate' => 5.0], ['label' => 'PST', 'rate' => 6.0]],
        'YT' => [['label' => 'GST', 'rate' => 5.0]],
    ],

];
