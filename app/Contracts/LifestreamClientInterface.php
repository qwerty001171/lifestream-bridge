<?php

namespace App\Contracts;

interface LifestreamClientInterface
{
    public function createAccount(array $data): array;

    public function manageSubscription(string $lifestreamId, string $offerId, bool $valid): array;

    public function resetPassword(string $lifestreamId, string $password): array;
}
