<?php

return [
    'name' => 'Pancake',

    /*
    |--------------------------------------------------------------------------
    | Auto-fill order address
    |--------------------------------------------------------------------------
    |
    | New orders from shops with auto-fill on (Shops → Edit) get their address
    | read from the Messenger chat and written back to Pancake.
    |
    | dry_run: read, match and log what would change — but never call Pancake's
    | update. On by default until the update has been checked on real orders.
    |
    | min_confidence: below this AI confidence (0–1) an order is left for a
    | person ("needs review") even when every level matched.
    |
    */

    'auto_fill_address' => [
        'dry_run' => (bool) env('PANCAKE_AUTO_FILL_DRY_RUN', true),
        'min_confidence' => (float) env('PANCAKE_AUTO_FILL_MIN_CONFIDENCE', 0.7),
    ],
];
