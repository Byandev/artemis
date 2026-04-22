<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Use rollup tables for metrics
    |--------------------------------------------------------------------------
    |
    | Per-metric feature flags gating the read path between the pre-aggregated
    | workspace_daily_metrics rollup and the live pancake_orders aggregates.
    | Default false (live) until parity is verified in each environment.
    |
    */
    'use_rollup' => [
        'total_sales' => env('ROLLUP_TOTAL_SALES', false),
        'total_orders' => env('ROLLUP_TOTAL_ORDERS', false),
        'delivered_amount' => env('ROLLUP_DELIVERED_AMOUNT', false),
        'returning_amount' => env('ROLLUP_RETURNING_AMOUNT', false),
        'returned_amount' => env('ROLLUP_RETURNED_AMOUNT', false),
    ],
];
