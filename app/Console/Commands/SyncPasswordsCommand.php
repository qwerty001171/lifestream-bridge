<?php

namespace App\Console\Commands;

use App\Services\PasswordSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPasswordsCommand extends Command
{
    protected $signature = 'billing:sync-passwords {region : The billing region to sync passwords for}';

    protected $description = 'Detect password changes in billing and push them to Lifestream';

    public function __construct(
        private readonly PasswordSyncService $passwordSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $region = $this->argument('region');

        $this->info("Starting password sync for region: {$region}");

        try {
            $result = $this->passwordSyncService->syncPasswords($region);

            $this->info(
                "Password sync completed: " .
                "{$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed."
            );

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Password sync failed: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}
