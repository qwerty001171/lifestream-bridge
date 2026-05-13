<?php

namespace App\Providers;

use App\Contracts\BillingAdapterInterface;
use App\Contracts\LifestreamClientInterface;
use App\Http\Clients\LifestreamClientAdapter;
use App\Services\BillingAdapterFactory;
use App\Services\OperationLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LifestreamClientInterface::class, function ($app) {
            $config = config('lifestream');

            return new LifestreamClientAdapter(
                baseUrl:  $config['base_url'],
                timeout:  $config['timeout'] ?? 30,
                retries:  $config['retries'] ?? 3,
            );
        });

        $this->app->singleton(BillingAdapterFactory::class, function ($app) {
            return new BillingAdapterFactory(
                sourcesConfig: config('billing.sources', [])
            );
        });

        $this->app->bind(BillingAdapterInterface::class, function ($app) {
            $factory = $app->make(BillingAdapterFactory::class);

            $sources = $factory->availableSources();

            if (empty($sources)) {
                throw new \RuntimeException('No billing sources are configured.');
            }

            return $factory->make($sources[0]);
        });

        $this->app->singleton(OperationLogger::class);

    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
