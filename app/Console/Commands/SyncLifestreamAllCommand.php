<?php

namespace App\Console\Commands;

use App\Services\BillingAdapterFactory;
use Illuminate\Console\Command;

class SyncLifestreamAllCommand extends Command
{
    protected $signature = 'lifestream:sync-all';

    protected $description = 'Sync all billing sources to Lifestream';

    public function __construct(
        private readonly BillingAdapterFactory $factory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources  = $this->factory->availableSources();
        $exitCode = self::SUCCESS;

        $this->info('Starting Lifestream sync for all sources: ' . implode(', ', $sources));

        foreach ($sources as $source) {
            if ($this->call('lifestream:sync', ['source' => $source]) !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
