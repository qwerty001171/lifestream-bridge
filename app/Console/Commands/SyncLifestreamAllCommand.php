<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use App\Services\LifestreamSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncLifestreamAllCommand extends Command
{
    protected $signature = 'lifestream:sync-all';

    protected $description = 'Sync all billing regions to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly LifestreamSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $regions  = $this->factory->availableRegions();
        $exitCode = self::SUCCESS;

        $this->info('Starting Lifestream sync for all regions: ' . implode(', ', $regions));

        foreach ($regions as $region) {
            $this->line("  Syncing region: {$region}");

            try {
                $result = $this->syncService->syncRegion($region);

                $this->info(
                    "  [{$region}] Done: " .
                    "{$result['synced']} synced, {$result['skipped']} skipped, {$result['failed']} failed."
                );

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
