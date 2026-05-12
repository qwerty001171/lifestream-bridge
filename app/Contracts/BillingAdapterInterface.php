<?php

namespace App\Contracts;

interface BillingAdapterInterface
{
    public function getUsers(int $page = 1, int $limit = 1000): array;

    public function getRegion(): string;
}
