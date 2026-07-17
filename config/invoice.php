<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seller ("From") details
    |--------------------------------------------------------------------------
    | These appear at the top of every generated invoice PDF. Replace the
    | placeholder values below with your real business details, or set the
    | matching env vars. Bryan — paste your legal business name, address, TIN,
    | and contact here.
    */
    'seller' => [
        'name' => env('INVOICE_SELLER_NAME', 'Artemis.ph'),
        // No street address on file yet — add one here when you have it.
        'address' => env('INVOICE_SELLER_ADDRESS', null),
        'tin' => env('INVOICE_SELLER_TIN', null),
        'email' => env('INVOICE_SELLER_EMAIL', 'hello@artemis.ph'),
        'phone' => env('INVOICE_SELLER_PHONE', null),
        // Absolute path or public-relative path to a logo image, or null.
        // e.g. public_path('images/logo.png')
        'logo_path' => env('INVOICE_SELLER_LOGO', null),
    ],

    // Default payment terms in days (issue_date + this = due_date).
    'due_days' => (int) env('INVOICE_DUE_DAYS', 7),

    // Default tax rate as a percentage (PH VAT is 12). Set 0 for none.
    'tax_rate' => (float) env('INVOICE_TAX_RATE', 0),

    // Bank / payment instructions printed in the footer notes area.
    'payment_instructions' => env(
        'INVOICE_PAYMENT_INSTRUCTIONS',
        "Please settle this invoice on or before the due date via bank transfer:\n\n".
        "Bank: Security Bank\n".
        "Account Name: Meta Digitrading Enterprise Co\n".
        "Account Number: 0000071593190"
    ),

    'currency' => env('INVOICE_CURRENCY', 'PHP'),
    'currency_symbol' => env('INVOICE_CURRENCY_SYMBOL', '₱'),
];
