<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use App\Services\LifestreamSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncLifestreamAllCommand extends Command
{
    protected $signature = 'lifestream:sync-all';

    protected $description = 'Sync all billing sources to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly LifestreamSyncService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources  = $this->factory->availableSources();
        $exitCode = self::SUCCESS;

        $this->info('Starting Lifestream sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            $this->line("  Syncing source: {$source}");

            try {
                $result = $this->syncService->syncSource($source);

                $this->info(
                    "  [{$source}] Done: " .
                    "{$result['synced']} synced, {$result['skipped']} skipped, {$result['failed']} failed."
                );

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
