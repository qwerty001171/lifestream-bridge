<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Regions
    |--------------------------------------------------------------------------
    */

    'regions' => [
        'region_a' => [
            'base_url' => env('BILLING_REGION_A_URL', 'http://billing-a.local/api'),
            'api_key'  => env('BILLING_REGION_A_KEY', ''),
            'timeout'  => 30,
        ],
        'region_b' => [
            'base_url' => env('BILLING_REGION_B_URL', 'http://billing-b.local/api'),
            'api_key'  => env('BILLING_REGION_B_KEY', ''),
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
