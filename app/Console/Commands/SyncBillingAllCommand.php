<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use Illuminate\Console\Command;

class SyncBillingAllCommand extends Command
{
    protected $signature = 'billing:sync-all';

    protected $description = 'Sync accounts from all configured billing sources';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources  = $this->factory->availableSources();
        $exitCode = self::SUCCESS;

        $this->info('Starting billing sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            if ($this->call('billing:sync', ['source' => $source]) !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
