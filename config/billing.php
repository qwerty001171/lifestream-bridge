<?php

return [

    'sources' => [
        'source_a' => [
            'base_url' => env('BILLING_SOURCE_A_URL', 'http://billing-a.local/api'),
            'api_key'  => env('BILLING_SOURCE_A_KEY', ''),
            'timeout'  => 30,
        ],
        'source_b' => [
            'base_url' => env('BILLING_SOURCE_B_URL', 'http://billing-b.local/api'),
            'api_key'  => env('BILLING_SOURCE_B_KEY', ''),
            'timeout'  => 30,
        ],
    ],

    'page_limit' => (int) env('BILLING_PAGE_LIMIT', 1000),

    'sync_lock_ttl' => (int) env('BILLING_SYNC_LOCK_TTL', 300),

    'adapter' => env('BILLING_ADAPTER', \App\Adapters\HttpBillingAdapter::class),
];
