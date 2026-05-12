<?php

namespace App\Console\Commands;

use App\Services\AccountSyncService;
use App\Services\BillingAdapterFactory;
use App\Services\BillingFetcher;
use Illuminate\Console\Command;
use Throwable;

class SyncBillingAllCommand extends Command
{
    protected $signature = 'billing:sync-all';

    protected $description = 'Sync accounts from all configured billing regions';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly AccountSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $regions    = $this->factory->availableRegions();
        $exitCode   = self::SUCCESS;

        $this->info('Starting billing sync for all regions: ' . implode(', ', $regions));

        foreach ($regions as $region) {
            $this->line("  Syncing region: {$region}");

            try {
                $adapter = $this->factory->make($region);
                $fetcher = new BillingFetcher($adapter, config('billing.page_limit', 1000));
                $result  = $this->syncService->sync($fetcher, $region);

                $this->info("  [{$region}] Done: {$result['synced']} synced, {$result['failed']} failed.");

                if ($result['failed'] > 0) {
                    $exitCode = self::FAILURE;
                }
            } catch (Throwable $e) {
                $this->error("  [{$region}] Failed: " . $e->getMessage());
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
