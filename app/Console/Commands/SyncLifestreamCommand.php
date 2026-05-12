<?php

namespace App\Console\Commands;

use App\Services\LifestreamSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncLifestreamCommand extends Command
{
    protected $signature = 'lifestream:sync {region : The billing region to sync to Lifestream}';

    protected $description = 'Sync local accounts to the Lifestream IPTV platform for a specific region';

    public function __construct(
        private readonly LifestreamSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $region = $this->argument('region');

        $this->info("Starting Lifestream sync for region: {$region}");

        try {
            $result = $this->syncService->syncRegion($region);

            $this->info(
                "Lifestream sync completed: " .
                "{$result['synced']} synced, {$result['skipped']} skipped, {$result['failed']} failed."
            );

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Lifestream sync failed: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}
