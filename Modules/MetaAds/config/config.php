<?php

return [
    'name' => 'MetaAds',

    'app_id' => env('META_ADS_APP_ID'),
    'app_secret' => env('META_ADS_APP_SECRET'),
    'redirect_uri' => env('META_ADS_REDIRECT_URI'),
    'graph_version' => env('META_ADS_GRAPH_VERSION', 'v25.0'),
    'graph_base_url' => env('META_ADS_GRAPH_BASE_URL', 'https://graph.facebook.com'),

    // Long-lived Business Manager system-user token used for BM-owned ad accounts.
    // After a workspace user OAuths and syncs their ad accounts, run
    // `metaads:discover-system-accounts` to flag any of those accounts that are
    // also visible to the BM as uses_system_user=true — future per-account sync
    // jobs will then route through this token instead of the OAuth user token.
    'system_user_token' => env('META_ADS_SYSTEM_USER_TOKEN'),

    'insights_backfill_days' => (int) env('META_ADS_INSIGHTS_BACKFILL_DAYS', 90),

    'page_size' => (int) env('META_ADS_PAGE_SIZE', 100),

    'throttle' => [
        'soft_threshold' => (int) env('META_ADS_THROTTLE_SOFT', 75),
        'soft_sleep_seconds' => (int) env('META_ADS_THROTTLE_SOFT_SLEEP', 10),
        'hard_threshold' => (int) env('META_ADS_THROTTLE_HARD', 95),
        'hard_sleep_seconds' => (int) env('META_ADS_THROTTLE_HARD_SLEEP', 60),

        // Minimum seconds between successive requests on the same access token.
        // Dev / Limited tier caps you at 60 score / 300s ≈ 12 calls/min, so 6s
        // keeps you strictly under. Bump down once you're on Full Access.
        'min_interval_seconds' => (int) env('META_ADS_MIN_INTERVAL', 6),
    ],

    // Ad-account People list (metaads:sync-ad-account-people).
    'people' => [
        // Meta exposes no "restricted" flag on assigned_users, but profiles that
        // are restricted, deactivated, or otherwise unavailable come back with
        // no name (or a generic placeholder). Skipping those keeps the People
        // column to real, reachable humans. Set false to store them anyway —
        // each run records how many were skipped in its SyncRun meta either way.
        'skip_unnamed' => (bool) env('META_ADS_PEOPLE_SKIP_UNNAMED', true),

        // Names Graph hands back in place of an unavailable profile.
        'placeholder_names' => [
            'facebook user',
            'meta user',
            'private user',
            'unknown user',
        ],
    ],

    'oauth_scopes' => [
        'ads_read',
        'ads_management',
        'business_management',
        'public_profile',
        'email',
    ],
];
