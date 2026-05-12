<?php

namespace App\Console\Commands;

use App\Services\AccountSyncService;
use App\Services\BillingAdapterFactory;
use App\Services\BillingFetcher;
use Illuminate\Console\Command;
use Throwable;

class SyncBillingCommand extends Command
{
    protected $signature = 'billing:sync {region : The billing region to sync (e.g. region_a)}';

    protected $description = 'Sync accounts from a billing region into the local database';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly AccountSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $region = $this->argument('region');

        $this->info("Starting billing sync for region: {$region}");

        try {
            $adapter = $this->factory->make($region);
            $fetcher = new BillingFetcher($adapter, config('billing.page_limit', 1000));
            $result  = $this->syncService->sync($fetcher, $region);

            $this->info("Sync completed: {$result['synced']} synced, {$result['failed']} failed.");

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error("Invalid region '{$region}': " . $e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Sync failed: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}
