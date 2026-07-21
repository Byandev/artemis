<?php

return [

    'name' => 'SimGateway',

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Controls which gateway implementation is used. Use "stub" during local
    | development and tests; swap to "yxgp" once the hardware endpoint is wired
    | up in Modules\SimGateway\Services\Gateway\YxGpGateway.
    |
    | Supported: "stub", "yxgp"
    */

    'driver' => env('SIMGATEWAY_DRIVER', 'stub'),

    /*
    |--------------------------------------------------------------------------
    | YX GP Gateway (YX International YX-Series GP)
    |--------------------------------------------------------------------------
    |
    | HTTP API base values for a YX GP hardware gateway. `host` may include a
    | port (e.g. "192.168.1.100:8080"); default port 80. `charset` must be one
    | of utf8, gb2312.
    */

    'yxgp' => [
        'host' => env('SIMGATEWAY_YXGP_HOST'),
        'username' => env('SIMGATEWAY_YXGP_USERNAME'),
        'password' => env('SIMGATEWAY_YXGP_PASSWORD'),
        'charset' => env('SIMGATEWAY_YXGP_CHARSET', 'utf8'),
        'timeout_seconds' => (int) env('SIMGATEWAY_YXGP_TIMEOUT', 10),
        'verify_tls' => (bool) env('SIMGATEWAY_YXGP_VERIFY_TLS', true),
        'auth_token' => env('SIMGATEWAY_YXGP_AUTH_TOKEN', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound callback authentication
    |--------------------------------------------------------------------------
    |
    | Shared token the hardware gateway sends in a URL query param (`token=…`)
    | on every callback it makes to us. Compared in constant time inside
    | VerifyGatewayCallback middleware. Rotate by changing both sides.
    */

    'callback' => [
        'token' => env('SIMGATEWAY_CALLBACK_TOKEN'),
    ],

];
