<?php

namespace App\Adapters;

use App\Contracts\BillingAdapterInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpBillingAdapter implements BillingAdapterInterface
{
    private Client $client;

    public function __construct(
        private readonly string $region,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 30
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout'  => $this->timeout,
            'headers'  => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'X-API-Key'     => $this->apiKey,
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);
    }

    public function getUsers(int $page = 1, int $limit = 1000): array
    {
        try {
            $response = $this->client->get('users', [
                'query' => [
                    'page'  => $page,
                    'limit' => $limit,
                ],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new RuntimeException(
                    "Billing API [{$this->region}] returned non-array response on page {$page}"
                );
            }

            return $data;
        } catch (GuzzleException $e) {
            Log::error('BillingAdapter HTTP error', [
                'region' => $this->region,
                'page'   => $page,
                'error'  => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Billing API [{$this->region}] request failed: " . $e->getMessage(),
                0,
                $e
            );
        } catch (\JsonException $e) {
            Log::error('BillingAdapter JSON decode error', [
                'region' => $this->region,
                'page'   => $page,
                'error'  => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Billing API [{$this->region}] returned invalid JSON: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function getRegion(): string
    {
        return $this->region;
    }
}
