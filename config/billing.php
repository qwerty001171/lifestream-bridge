<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Regions
    |--------------------------------------------------------------------------
    */

    'sources' => [
        'region_a' => [
            'base_url' => env('BILLING_SOURCE_A_URL', 'http://billing-a.local/api'),
            'api_key'  => env('BILLING_SOURCE_A_KEY', ''),
            'timeout'  => 30,
        ],
        'region_b' => [
            'base_url' => env('BILLING_SOURCE_B_URL', 'http://billing-b.local/api'),
            'api_key'  => env('BILLING_SOURCE_B_KEY', ''),
            'timeout'  => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Limit
    |--------------------------------------------------------------------------
    */

    'page_limit' => (int) env('BILLING_PAGE_LIMIT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Adapter Class
    |--------------------------------------------------------------------------
    */

    'adapter' => env('BILLING_ADAPTER', \App\Adapters\HttpBillingAdapter::class),

];
