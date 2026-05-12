<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Device;
use App\Models\OperationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountSyncService
{
    public function __construct(
        private readonly OperationLogger $logger
    ) {}

    public function sync(BillingFetcher $fetcher, string $region): array
    {
        $synced = 0;
        $failed = 0;

        foreach ($fetcher->fetchAll() as $row) {
            try {
                DB::transaction(function () use ($row, $region, &$synced) {
                    $this->upsertAccount($row, $region);
                    $synced++;
                });
            } catch (Throwable $e) {
                $failed++;

                $externalId = $this->extractExternalId($row);

                Log::error('AccountSyncService: failed to upsert account', [
                    'region'      => $region,
                    'external_id' => $externalId,
                    'error'       => $e->getMessage(),
                ]);

                $this->logger->log(
                    operationType: OperationLog::TYPE_SYNC_MAIN_BILLING,
                    result:        OperationLog::RESULT_FAILED,
                    billingSource: $region,
                    data:          ['external_id' => $externalId],
                    errorMessage:  $e->getMessage()
                );
            }
        }

        $this->logger->log(
            operationType: OperationLog::TYPE_SYNC_MAIN_BILLING,
            result:        OperationLog::RESULT_SUCCESS,
            billingSource: $region,
            data:          ['synced' => $synced, 'failed' => $failed]
        );

        Log::info('AccountSyncService: sync completed', [
            'region' => $region,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed');
    }

    private function upsertAccount(array $row, string $region): Account
    {
        $user = $row['User'] ?? $row;
        $main = $row['Main'] ?? [];

        $externalId = $this->extractExternalId($row);

        if ($externalId === '') {
            throw new \InvalidArgumentException('User record has no external_id/id field');
        }

        $login = $user['name'] ?? $user['login'] ?? $user['username'] ?? $externalId;
        $email = $user['email'] ?? $main['email'] ?? null;
        $mac   = $user['mac'] ?? $main['mac'] ?? null;

        $paket = $main['paket'] ?? $user['paket'] ?? $user['iptv_packet'] ?? null;
        $mid   = isset($user['mid']) && (int) $user['mid'] > 0 ? (string) $user['mid'] : null;

        $account = Account::updateOrCreate(
            [
                'external_id'    => $externalId,
                'billing_source' => $region,
            ],
            [
                'login'          => $login,
                'email'          => $email,
                'mid'            => $mid,
                'mac'            => $mac,
                'paket'          => $paket !== null ? (string) $paket : null,
                'last_synced_at' => now(),
            ]
        );

        if (!empty($mac)) {
            $normalizedMac = Device::normalizeMac($mac);
            Device::updateOrCreate(
                [
                    'account_uuid' => $account->uuid,
                    'mac'          => $normalizedMac,
                ],
                [
                    'synced_to_lifestream' => false,
                ]
            );
        }

        return $account;
    }

    private function extractExternalId(array $row): string
    {
        $user = $row['User'] ?? $row;

        $mid = isset($user['mid']) ? (int) $user['mid'] : 0;
        if ($mid > 0) {
            return (string) $mid;
        }

        return (string) ($user['id'] ?? $user['external_id'] ?? '');
    }
}
