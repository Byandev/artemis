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
        'gencys_daily_sales_webhook_url' => env('N8N_GENCYS_DAILY_SALES_WEBHOOK_URL'),
    ],

    'discord' => [
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'inventory_webhook_url' => env('INVENTORY_DISCORD_WEBHOOK_URL'),
    ],

];
