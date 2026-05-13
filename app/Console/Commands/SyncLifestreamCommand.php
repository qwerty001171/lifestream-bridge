<?php

namespace App\Console\Commands;

use App\Services\LifestreamSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncLifestreamCommand extends Command
{
    protected $signature = 'lifestream:sync {source : The billing source to sync to Lifestream}';

    protected $description = 'Sync local accounts to the Lifestream IPTV platform for a specific source';

    public function __construct(
        private readonly LifestreamSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->argument('source');
        $ttl    = config('billing.sync_lock_ttl', 300);
        $lock   = Cache::lock("lifestream:sync:{$source}", $ttl);

        if (!$lock->get()) {
            $this->info("Lifestream sync for {$source} is already running. Skipping.");
            return self::SUCCESS;
        }

        $this->info("Starting Lifestream sync for source: {$source}");

        try {
            $result = $this->syncService->syncSource($source);

            $this->info(
                "Lifestream sync completed: " .
                "{$result['synced']} synced, {$result['skipped']} skipped, {$result['failed']} failed."
            );

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Lifestream sync failed: " . $e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
