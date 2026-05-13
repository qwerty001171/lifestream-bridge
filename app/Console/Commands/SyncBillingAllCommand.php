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

    protected $description = 'Sync accounts from all configured billing sources';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly AccountSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources    = $this->factory->availableSources();
        $exitCode   = self::SUCCESS;

        $this->info('Starting billing sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            $this->line("  Syncing source: {$source}");

            try {
                $adapter = $this->factory->make($source);
                $fetcher = new BillingFetcher($adapter, config('billing.page_limit', 1000));
                $result  = $this->syncService->sync($fetcher, $source);

                $this->info("  [{$source}] Done: {$result['synced']} synced, {$result['failed']} failed.");

                if ($result['failed'] > 0) {
                    $exitCode = self::FAILURE;
                }
            } catch (Throwable $e) {
                $this->error("  [{$source}] Failed: " . $e->getMessage());
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
