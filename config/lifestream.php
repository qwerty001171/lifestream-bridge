<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lifestream API Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('LIFESTREAM_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Note on Authentication
    |--------------------------------------------------------------------------
    |
    | Lifestream uses IP-based authentication (whitelist of source IPs).
    | No API key header is required for outbound requests to Lifestream.
    | Ensure the billing service IP is whitelisted with your Lifestream manager.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('LIFESTREAM_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit
    |--------------------------------------------------------------------------
    */

    'rate_limit' => (int) env('LIFESTREAM_RATE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */

    'retries'    => (int) env('LIFESTREAM_RETRIES', 3),
    'retry_delay' => (int) env('LIFESTREAM_RETRY_DELAY', 500),
];
