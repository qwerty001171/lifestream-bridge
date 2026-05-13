<?php

return [
    'base_url' => env('LIFESTREAM_URL', ''),
    'timeout' => (int) env('LIFESTREAM_TIMEOUT', 30),
    'rate_limit' => (int) env('LIFESTREAM_RATE_LIMIT', 10),
    'retries'    => (int) env('LIFESTREAM_RETRIES', 3),
    'retry_delay' => (int) env('LIFESTREAM_RETRY_DELAY', 500),
];
