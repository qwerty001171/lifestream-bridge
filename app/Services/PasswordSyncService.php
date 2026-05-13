<?php

namespace App\Services;

use App\Contracts\LifestreamClientInterface;
use App\Models\Account;
use App\Models\OperationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasswordSyncService
{
    public function __construct(
        private readonly LifestreamClientInterface $lifestreamClient,
        private readonly OperationLogger $logger,
        private readonly BillingAdapterFactory $adapterFactory
    ) {}

    public function syncPasswords(string $source): array
    {
        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        $adapter = $this->adapterFactory->make($source);
        $fetcher = new BillingFetcher($adapter, config('billing.page_limit', 1000));

        foreach ($fetcher->fetchAll() as $row) {
            $user        = $row['User'] ?? $row;
            $externalId  = $this->extractExternalId($row);
            $plainPassword = $user['decoded_password'] ?? $user['main_decoded_password'] ?? null;

            try {
                $account = Account::where('external_id', $externalId)
                    ->where('billing_source', $source)
                    ->first();

                if ($account === null || empty($account->lifestream_id)) {
                    $skipped++;
                    continue;
                }

                if (empty($plainPassword)) {
                    Log::debug('PasswordSyncService: no plain password available, skipping', [
                        'external_id' => $externalId,
                    ]);
                    $skipped++;
                    continue;
                }

                $newHash = hash('sha256', $plainPassword);

                if ($account->password_hash === $newHash) {
                    $skipped++;
                    continue;
                }

                $this->lifestreamClient->resetPassword($account->lifestream_id, $plainPassword);

                DB::transaction(function () use ($account, $newHash): void {
                    $account->password_hash = $newHash;
                    $account->save();
                });

                $this->logger->log(
                    operationType: OperationLog::TYPE_PASSWORD_SYNC,
                    result:        OperationLog::RESULT_SUCCESS,
                    accountId:     $account->uuid,
                    billingSource: $source,
                    data:          ['external_id' => $externalId]
                );

                $updated++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('PasswordSyncService: failed', [
                    'source'      => $source,
                    'external_id' => $externalId,
                    'error'       => $e->getMessage(),
                ]);

                $this->logger->log(
                    operationType: OperationLog::TYPE_PASSWORD_SYNC,
                    result:        OperationLog::RESULT_FAILED,
                    accountId:     isset($account) ? $account->uuid : null,
                    billingSource: $source,
                    data:          ['external_id' => $externalId],
                    errorMessage:  $e->getMessage()
                );
            }
        }

        Log::info('PasswordSyncService: completed', compact('source', 'updated', 'skipped', 'failed'));

        return compact('updated', 'skipped', 'failed');
    }

    private function extractExternalId(array $row): string
    {
        $user = $row['User'] ?? $row;
        $mid  = isset($user['mid']) ? (int) $user['mid'] : 0;
        if ($mid > 0) {
            return (string) $mid;
        }
        return (string) ($user['id'] ?? $user['external_id'] ?? '');
    }
}
