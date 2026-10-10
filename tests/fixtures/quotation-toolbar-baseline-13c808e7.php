<?php

/**
 * Toolbar strings that existed on the pre-#72 editors at 13c808e7
 * (Fix admin date pickers in dark mode on phones, #77).
 *
 * Live main after #72 moved these into shared partials, so inventory
 * tests must not `git show` origin/main.
 *
 * @return array<string, list<string>>
 */
return [
    'basement/create.blade.php' => [
        'Email quotation to client',
    ],
    'standard/create.blade.php' => [
        'Email quotation to client',
        'SMS quotation link to client',
        'Quotation types',
    ],
    'fence/create.blade.php' => [
        'Save &amp; create project',
        'after_save',
        'create_project',
    ],
    'low_deck/create.blade.php' => [
        'Print Layout Diagrams',
        'low-deck.layout-diagrams',
    ],
    'millwork/create.blade.php' => [
        'Print cut list',
    ],
];
