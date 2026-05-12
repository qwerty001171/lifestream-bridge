<?php

namespace App\Services;

use App\Adapters\HttpBillingAdapter;
use App\Contracts\BillingAdapterInterface;
use InvalidArgumentException;

class BillingAdapterFactory
{
    public function __construct(
        private readonly array $regionsConfig
    ) {}

    public function make(string $region): BillingAdapterInterface
    {
        if (!isset($this->regionsConfig[$region])) {
            throw new InvalidArgumentException(
                "Billing region '{$region}' is not configured. " .
                "Available regions: " . implode(', ', array_keys($this->regionsConfig))
            );
        }

        $config = $this->regionsConfig[$region];

        return new HttpBillingAdapter(
            region:  $region,
            baseUrl: $config['base_url'],
            apiKey:  $config['api_key'],
            timeout: $config['timeout'] ?? 30,
        );
    }

    public function availableRegions(): array
    {
        return array_keys($this->regionsConfig);
    }
}
