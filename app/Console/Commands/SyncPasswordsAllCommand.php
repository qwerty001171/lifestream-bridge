<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use Illuminate\Console\Command;

class SyncPasswordsAllCommand extends Command
{
    protected $signature = 'billing:sync-passwords-all';

    protected $description = 'Sync password changes from all billing sources to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources  = $this->factory->availableSources();
        $exitCode = self::SUCCESS;

        $this->info('Starting password sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            if ($this->call('billing:sync-passwords', ['source' => $source]) !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
