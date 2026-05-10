<?php

return [
    'name' => 'MetaAds',

    'app_id' => env('META_ADS_APP_ID'),
    'app_secret' => env('META_ADS_APP_SECRET'),
    'redirect_uri' => env('META_ADS_REDIRECT_URI'),
    'graph_version' => env('META_ADS_GRAPH_VERSION', 'v25.0'),
    'graph_base_url' => env('META_ADS_GRAPH_BASE_URL', 'https://graph.facebook.com'),

    'insights_backfill_days' => (int) env('META_ADS_INSIGHTS_BACKFILL_DAYS', 90),

    'page_size' => (int) env('META_ADS_PAGE_SIZE', 100),

    'oauth_scopes' => [
        'ads_read',
        'ads_management',
        'business_management',
        'public_profile',
        'email',
    ],
];
