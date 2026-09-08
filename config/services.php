<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
        'timeout' => env('BREVO_TIMEOUT', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'purchase_order_webhook_url' => env('N8N_PURCHASE_ORDER_WEBHOOK_URL'),
        'transaction_history_webhook_url' => env('N8N_TRANSACTION_HISTORY_WEBHOOK_URL'),
        'inventory_unit_code_webhook_url' => env('N8N_INVENTORY_UNIT_CODE_WEBHOOK_URL'),
        'gencys_inventory_items_webhook_url' => env('N8N_GENCYS_INVENTORY_ITEMS_WEBHOOK_URL'),
        'gencys_daily_sales_webhook_url' => env('N8N_GENCYS_DAILY_SALES_WEBHOOK_URL'),
        'gencys_interns_webhook_url' => env('N8N_GENCYS_INTERNS_WEBHOOK_URL'),
        'gencys_intern_daily_records_webhook_url' => env('N8N_GENCYS_INTERN_DAILY_RECORDS_WEBHOOK_URL'),
        'gencys_pages_webhook_url' => env('N8N_GENCYS_PAGES_WEBHOOK_URL'),
        'gencys_page_details_webhook_url' => env('N8N_GENCYS_PAGE_DETAILS_WEBHOOK_URL'),

        // Public base URL n8n posts callbacks back to (e.g. an ngrok/Herd tunnel
        // in local dev). Falls back to APP_URL when unset.
        'callback_base_url' => env('N8N_CALLBACK_BASE_URL'),

        // n8n's own REST API, used to look up how an execution actually went and
        // to link into the editor. Base URL of the n8n instance (no /api/v1) plus
        // a public API key from Settings → n8n API. Both optional: without them
        // the sync pages simply don't offer the lookup.
        'api_url' => env('N8N_API_URL'),
        'api_key' => env('N8N_API_KEY'),
    ],

    'discord' => [
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'inventory_webhook_url' => env('INVENTORY_DISCORD_WEBHOOK_URL'),
        // Meta Ads alerts (sync failures). One channel for the whole install —
        // falls back to the general webhook when unset.
        'meta_ads_webhook_url' => env('META_ADS_DISCORD_WEBHOOK_URL'),
    ],

    // SMS providers for parcel-journey notifications. Credentials are stored
    // per page; these are just the API base URLs so they can differ between
    // environments (e.g. SendGate's test host vs production).
    'infotxt' => [
        'base_url' => env('INFOTXT_BASE_URL', 'https://api.myinfotxt.com/v2'),
    ],

    'sendgate' => [
        'base_url' => env('SENDGATE_BASE_URL', 'https://sendgate-test.on-forge.com'),
    ],

];
