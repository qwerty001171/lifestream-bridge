<?php

namespace App\Http\Clients;

use App\Contracts\LifestreamClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpLifestreamClient implements LifestreamClientInterface
{
    private Client $client;
    private float $lastRequestAt = 0.0;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
        private readonly int $retryDelay = 500,
        private readonly int $rateLimit = 10
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout'  => $this->timeout,
            'headers'  => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function createAccount(array $data): array
    {
        return $this->request('POST', 'v2/accounts', ['json' => $data]);
    }

    public function manageSubscription(string $lifestreamId, string $offerId, bool $valid): array
    {
        return $this->request('POST', "v2/accounts/{$lifestreamId}/subscriptions", [
            'form_params' => [
                'id'    => $offerId,
                'valid' => $valid ? 'true' : 'false',
            ],
        ]);
    }

    public function resetPassword(string $lifestreamId, string $password): array
    {
        return $this->request('POST', "v2/accounts/{$lifestreamId}/reset-password", [
            'json' => ['password' => $password],
        ]);
    }

    private function throttle(): void
    {
        if ($this->rateLimit <= 0) {
            return;
        }

        $minInterval = 1_000_000 / $this->rateLimit;
        $elapsed = (microtime(true) - $this->lastRequestAt) * 1_000_000;

        if ($elapsed < $minInterval) {
            usleep((int) ($minInterval - $elapsed));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $this->throttle();
                $response = $this->client->request($method, $uri, $options);
                $body     = (string) $response->getBody();

                if ($body === '') {
                    return [];
                }

                return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (GuzzleException $e) {
                if ($attempt >= $this->retries) {
                    Log::error('HttpLifestreamClient: request failed after retries', [
                        'method'   => $method,
                        'uri'      => $uri,
                        'attempts' => $attempt,
                        'error'    => $e->getMessage(),
                    ]);

                    throw new RuntimeException(
                        "Lifestream API request [{$method} {$uri}] failed: " . $e->getMessage(),
                        0,
                        $e
                    );
                }

                Log::warning('HttpLifestreamClient: retrying request', [
                    'method'  => $method,
                    'uri'     => $uri,
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);

                usleep($this->retryDelay * 1000 * $attempt);
            } catch (\JsonException $e) {
                throw new RuntimeException(
                    "Lifestream API [{$method} {$uri}] returned invalid JSON",
                    0,
                    $e
                );
            }
        }
    }
}
