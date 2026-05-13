<?php

namespace App\Console\Commands;

use App\Services\AccountSyncService;
use App\Services\BillingAdapterFactory;
use App\Services\BillingFetcher;
use Illuminate\Console\Command;
use Throwable;

class SyncBillingCommand extends Command
{
    protected $signature = 'billing:sync {source : The billing source to sync (e.g. source_a)}';

    protected $description = 'Sync accounts from a billing source into the local database';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly AccountSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->argument('source');

        $this->info("Starting billing sync for source: {$source}");

        try {
            $adapter = $this->factory->make($source);
            $fetcher = new BillingFetcher($adapter, config('billing.page_limit', 1000));
            $result  = $this->syncService->sync($fetcher, $source);

            $this->info("Sync completed: {$result['synced']} synced, {$result['failed']} failed.");

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error("Invalid source '{$source}': " . $e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Sync failed: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}
