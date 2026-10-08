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
    | delay_seconds: how long after the order arrives the chat is first read.
    | Orders are often created before the customer has typed the address.
    |
    | retry_minutes / max_attempts: when there is still no address, read again
    | this much later, up to this many reads in all.
    |
    */

    'auto_fill_address' => [
        'dry_run' => (bool) env('PANCAKE_AUTO_FILL_DRY_RUN', true),
        'min_confidence' => (float) env('PANCAKE_AUTO_FILL_MIN_CONFIDENCE', 0.7),
        'delay_seconds' => (int) env('PANCAKE_AUTO_FILL_DELAY_SECONDS', 15),
        'retry_minutes' => (int) env('PANCAKE_AUTO_FILL_RETRY_MINUTES', 10),
        'max_attempts' => (int) env('PANCAKE_AUTO_FILL_MAX_ATTEMPTS', 2),
    ],
];
