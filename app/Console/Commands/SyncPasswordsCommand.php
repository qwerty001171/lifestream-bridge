<?php

namespace App\Console\Commands;

use App\Services\PasswordSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncPasswordsCommand extends Command
{
    protected $signature = 'billing:sync-passwords {source : The billing source to sync passwords for}';

    protected $description = 'Detect password changes in billing and push them to Lifestream';

    public function __construct(
        private readonly PasswordSyncService $passwordSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->argument('source');
        $ttl    = config('billing.sync_lock_ttl', 300);
        $lock   = Cache::lock("billing:sync-passwords:{$source}", $ttl);

        if (!$lock->get()) {
            $this->info("Password sync for {$source} is already running. Skipping.");
            return self::SUCCESS;
        }

        $this->info("Starting password sync for source: {$source}");

        try {
            $result = $this->passwordSyncService->syncPasswords($source);

            $this->info(
                "Password sync completed: " .
                "{$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed."
            );

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Password sync failed: " . $e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
