<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use App\Services\PasswordSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPasswordsAllCommand extends Command
{
    protected $signature = 'billing:sync-passwords-all';

    protected $description = 'Sync password changes from all billing regions to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly PasswordSyncService $passwordSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $regions  = $this->factory->availableRegions();
        $exitCode = self::SUCCESS;

        $this->info('Starting password sync for all regions: ' . implode(', ', $regions));

        foreach ($regions as $region) {
            $this->line("  Syncing region: {$region}");

            try {
                $result = $this->passwordSyncService->syncPasswords($region);

                $this->info(
                    "  [{$region}] Done: " .
                    "{$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed."
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
