<?php

namespace App\Services;

use App\Contracts\BillingAdapterInterface;
use Generator;
use Illuminate\Support\Facades\Log;

class BillingFetcher
{
    public function __construct(
        private readonly BillingAdapterInterface $adapter,
        private readonly int $limit = 1000
    ) {}

    public function fetchAll(): Generator
    {
        $page  = 1;
        $total = 0;

        do {
            Log::debug('BillingFetcher: fetching page', [
                'region' => $this->adapter->getRegion(),
                'page'   => $page,
                'limit'  => $this->limit,
            ]);

            $response = $this->adapter->getUsers($page, $this->limit);

            $users = $response['data']['users'] ?? [];

            foreach ($users as $user) {
                $total++;
                yield $user;
            }

            $pagination = $response['pagination'] ?? [];
            $hasNext    = (bool) ($pagination['nextPage'] ?? $pagination['next_page'] ?? false);
            $page++;
        } while ($hasNext);

        Log::debug('BillingFetcher: completed', [
            'region' => $this->adapter->getRegion(),
            'total'  => $total,
        ]);
    }

    public function getAdapter(): BillingAdapterInterface
    {
        return $this->adapter;
    }
}
