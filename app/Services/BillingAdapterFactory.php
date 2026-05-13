<?php

namespace App\Services;

use App\Adapters\HttpBillingAdapter;
use App\Contracts\BillingAdapterInterface;
use InvalidArgumentException;

class BillingAdapterFactory
{
    public function __construct(
        private readonly array $sourcesConfig
    ) {}

    public function make(string $source): BillingAdapterInterface
    {
        if (!isset($this->sourcesConfig[$source])) {
            throw new InvalidArgumentException(
                "Billing source '{$source}' is not configured. " .
                "Available sources: " . implode(', ', array_keys($this->sourcesConfig))
            );
        }

        $config = $this->sourcesConfig[$source];

        return new HttpBillingAdapter(
            source:  $source,
            baseUrl: $config['base_url'],
            apiKey:  $config['api_key'],
            timeout: $config['timeout'] ?? 30,
        );
    }

    public function availableSources(): array
    {
        return array_keys($this->sourcesConfig);
    }
}
