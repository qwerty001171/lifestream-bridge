<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use App\Services\PasswordSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPasswordsAllCommand extends Command
{
    protected $signature = 'billing:sync-passwords-all';

    protected $description = 'Sync password changes from all billing sources to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
        private readonly PasswordSyncService $passwordSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources  = $this->factory->availableSources();
        $exitCode = self::SUCCESS;

        $this->info('Starting password sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            $this->line("  Syncing source: {$source}");

            try {
                $result = $this->passwordSyncService->syncPasswords($source);

                $this->info(
                    "  [{$source}] Done: " .
                    "{$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed."
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
