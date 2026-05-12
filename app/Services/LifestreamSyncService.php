<?php

namespace App\Services;

use App\Contracts\LifestreamClientInterface;
use App\Models\Account;
use App\Models\Device;
use App\Models\Offer;
use App\Models\OperationLog;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LifestreamSyncService
{
    public function __construct(
        private readonly LifestreamClientInterface $lifestreamClient,
        private readonly OperationLogger $logger
    ) {}

    public function syncRegion(string $region): array
    {
        $synced  = 0;
        $skipped = 0;
        $failed  = 0;

        Account::where('billing_source', $region)
            ->chunkById(100, function ($accounts) use ($region, &$synced, &$skipped, &$failed) {
                foreach ($accounts as $account) {
                    try {
                        $result = $this->syncAccount($account, $region);

                        if ($result === 'skipped') {
                            $skipped++;
                        } else {
                            $synced++;
                        }
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('LifestreamSyncService: failed to sync account', [
                            'account_uuid' => $account->uuid,
                            'region'     => $region,
                            'error'      => $e->getMessage(),
                        ]);

                        $this->logger->log(
                            operationType: OperationLog::TYPE_SYNC_LIFESTREAM,
                            result:        OperationLog::RESULT_FAILED,
                            accountId:     $account->uuid,
                            billingSource: $region,
                            errorMessage:  $e->getMessage()
                        );
                    }
                }
            });

        Log::info('LifestreamSyncService: region sync completed', compact('region', 'synced', 'skipped', 'failed'));

        return compact('synced', 'skipped', 'failed');
    }

    private function syncAccount(Account $account, string $region): string
    {
        $lifestreamId = $account->lifestream_id;

        if (empty($lifestreamId)) {
            $safeLogin = LoginSanitizer::sanitize($account->login);
            $payload   = ['username' => $safeLogin];

            if (!empty($account->email)) {
                $payload['email'] = $account->email;
            }

            if (!empty($account->mac)) {
                $payload['info'] = ['mac' => $account->mac];
            }

            $response = $this->lifestreamClient->createAccount($payload);

            $lifestreamId = $response['id'] ?? null;

            if (empty($lifestreamId)) {
                throw new \RuntimeException('Lifestream did not return an account ID');
            }
        }

        $targetOfferId = null;

        if (!empty($account->paket)) {
            $targetOfferId = Offer::findLifestreamOfferId($region, (string) $account->paket);
        }

        $activeSubscriptions = Subscription::where('account_uuid', $account->uuid)
            ->where('billing_source', $region)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->get();

        $deactivatedOfferIds = [];

        foreach ($activeSubscriptions as $sub) {
            if ($sub->lifestream_offer_id !== $targetOfferId) {
                $this->lifestreamClient->manageSubscription($lifestreamId, $sub->lifestream_offer_id, false);
                $deactivatedOfferIds[] = $sub->lifestream_offer_id;
            }
        }

        if ($targetOfferId !== null) {
            $alreadyActive = $activeSubscriptions
                ->where('lifestream_offer_id', $targetOfferId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->isNotEmpty();

            if (!$alreadyActive) {
                $this->lifestreamClient->manageSubscription($lifestreamId, $targetOfferId, true);
            }
        }

        DB::transaction(function () use ($account, $region, $lifestreamId, $targetOfferId, $deactivatedOfferIds): void {
            $account->lifestream_id = $lifestreamId;
            $account->save();

            if (!empty($deactivatedOfferIds)) {
                Subscription::where('account_uuid', $account->uuid)
                    ->where('billing_source', $region)
                    ->whereIn('lifestream_offer_id', $deactivatedOfferIds)
                    ->update(['status' => Subscription::STATUS_INACTIVE]);
            }

            if ($targetOfferId !== null) {
                Subscription::updateOrCreate(
                    [
                        'account_uuid'        => $account->uuid,
                        'billing_source'      => $region,
                        'lifestream_offer_id' => $targetOfferId,
                    ],
                    [
                        'status'       => Subscription::STATUS_ACTIVE,
                        'auto_renewal' => true,
                        'started_at'   => now(),
                    ]
                );
            }

            Device::where('account_uuid', $account->uuid)
                ->update(['synced_to_lifestream' => true]);
        });

        $logData = ['lifestream_id' => $lifestreamId];

        if ($targetOfferId !== null) {
            $logData['enabled_offer'] = $targetOfferId;
        }

        if (!empty($deactivatedOfferIds)) {
            $logData['disabled_offers'] = $deactivatedOfferIds;
        }

        if ($targetOfferId === null) {
            $logData['reason'] = empty($account->paket) ? 'no_paket' : 'no_offer_mapping';
            $logData['paket']  = $account->paket;

            $this->logger->log(
                operationType: OperationLog::TYPE_SYNC_LIFESTREAM,
                result:        empty($deactivatedOfferIds) ? OperationLog::RESULT_SKIPPED : OperationLog::RESULT_SUCCESS,
                accountId:     $account->uuid,
                billingSource: $region,
                data:          $logData
            );

            return empty($deactivatedOfferIds) ? 'skipped' : 'synced';
        }

        $this->logger->log(
            operationType: OperationLog::TYPE_SYNC_LIFESTREAM,
            result:        OperationLog::RESULT_SUCCESS,
            accountId:     $account->uuid,
            billingSource: $region,
            data:          $logData
        );

        return 'synced';
    }
}
