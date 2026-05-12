<?php

namespace App\Adapters;

use App\Contracts\BillingAdapterInterface;

class FakeBillingAdapter implements BillingAdapterInterface
{
    public function __construct(
        private readonly string $region,
        private readonly array $pages = []
    ) {}

    public function getUsers(int $page = 1, int $limit = 1000): array
    {
        $index = $page - 1;
        $users = $this->pages[$index] ?? [];

        $hasNext = isset($this->pages[$index + 1]) && count($this->pages[$index + 1]) > 0;

        return [
            'data' => [
                'users' => $users,
            ],
            'pagination' => [
                'page'     => $page,
                'limit'    => $limit,
                'total'    => array_sum(array_map('count', $this->pages)),
                'nextPage' => $hasNext,
            ],
        ];
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function totalUsers(): int
    {
        return array_sum(array_map('count', $this->pages));
    }
}
