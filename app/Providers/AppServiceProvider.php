<?php

namespace App\Providers;

use App\Contracts\BillingAdapterInterface;
use App\Contracts\LifestreamClientInterface;
use App\Adapters\HttpBillingAdapter;
use App\Http\Clients\HttpLifestreamClient;
use App\Services\BillingAdapterFactory;
use App\Services\OperationLogger;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LifestreamClientInterface::class, function ($app) {
            $config = config('lifestream');

            return new HttpLifestreamClient(
                baseUrl:    $config['base_url'],
                timeout:    $config['timeout'] ?? 30,
                retries:    $config['retries'] ?? 3,
                retryDelay: $config['retry_delay'] ?? 500,
                rateLimit:  $config['rate_limit'] ?? 10,
            );
        });

        $this->app->singleton(BillingAdapterFactory::class, function ($app) {
            return new BillingAdapterFactory(
                regionsConfig: config('billing.regions', [])
            );
        });

        $this->app->bind(BillingAdapterInterface::class, function ($app) {
            $factory = $app->make(BillingAdapterFactory::class);

            $regions = $factory->availableRegions();

            if (empty($regions)) {
                throw new \RuntimeException('No billing regions are configured.');
            }

            return $factory->make($regions[0]);
        });

        $this->app->singleton(OperationLogger::class);
    }

    public function boot(): void
    {
    }
}
