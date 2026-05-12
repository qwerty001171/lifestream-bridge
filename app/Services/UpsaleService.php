<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Offer;
use App\Models\OperationLog;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpsaleService
{
    public const RESULT_OPERATION_ENSURED     = 'operation_ensured';
    public const RESULT_OPERATION_COMMITED    = 'operation_commited';
    public const RESULT_NO_ACTION_REQUIRED    = 'no_action_required';
    public const RESULT_NO_SUFFICIENT_BALANCE = 'no_sufficient_balance';
    public const RESULT_NO_SUBSCRIPTION_RULES = 'no_subscription_rules';

    public function __construct(
        private readonly OperationLogger $logger
    ) {}

    public function ensure(
        string $operationType,
        string $accountId,
        string $accountLogin,
        string $newOfferId,
        ?string $oldOfferId,
        string $qsTransactionId,
        int $trialDays = 0,
        array $context = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        return DB::transaction(function () use (
            $operationType, $accountId, $accountLogin,
            $newOfferId, $oldOfferId, $qsTransactionId, $trialDays, $context, $ip, $userAgent
        ): array {
            $existing = Transaction::where('qs_transaction_id', $qsTransactionId)->first();
            if ($existing !== null) {
                if ($existing->isCommitted()) {
                    return [
                        'result'               => self::RESULT_OPERATION_COMMITED,
                        'billingTransactionId' => $existing->uuid,
                        'billingStartTimestamp' => $existing->committed_at?->toRfc3339String(),
                    ];
                }

                return [
                    'result'               => self::RESULT_OPERATION_ENSURED,
                    'billingTransactionId' => $existing->uuid,
                ];
            }

            $account = $this->findAccount($accountId, $accountLogin);

            if ($account === null) {
                $this->logger->log(
                    operationType: OperationLog::TYPE_UPSALE_ENSURE,
                    result:        OperationLog::RESULT_SKIPPED,
                    data:          ['account_uuid' => $accountId, 'login' => $accountLogin, 'reason' => 'account_not_found'],
                    ip:            $ip,
                    userAgent:     $userAgent
                );

                return ['result' => self::RESULT_NO_SUBSCRIPTION_RULES];
            }

            $activeSubscription = Subscription::where('account_uuid', $account->uuid)
                ->where('lifestream_offer_id', $newOfferId)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->first();

            if ($activeSubscription !== null && $operationType === Transaction::OPERATION_ADD_SUBSCRIPTION) {
                return ['result' => self::RESULT_NO_ACTION_REQUIRED];
            }

            $transaction = Transaction::create([
                'account_uuid'          => $account->uuid,
                'billing_source'      => $account->billing_source,
                'lifestream_offer_id' => $newOfferId,
                'old_offer_id'        => $oldOfferId,
                'operation_type'      => $operationType,
                'phase'               => Transaction::PHASE_ENSURE,
                'qs_transaction_id'   => $qsTransactionId,
                'trial_days'          => $trialDays,
                'ensure_payload'      => $context ?: null,
                'ensured_at'          => now(),
            ]);

            $this->logger->log(
                operationType: OperationLog::TYPE_UPSALE_ENSURE,
                result:        OperationLog::RESULT_SUCCESS,
                accountId:     $account->uuid,
                billingSource: $account->billing_source,
                data: [
                    'transaction_id'    => $transaction->uuid,
                    'new_offer_id'      => $newOfferId,
                    'old_offer_id'      => $oldOfferId,
                    'qs_transaction_id' => $qsTransactionId,
                    'operation_type'    => $operationType,
                ],
                ip:        $ip,
                userAgent: $userAgent
            );

            return [
                'result'               => self::RESULT_OPERATION_ENSURED,
                'billingTransactionId' => $transaction->uuid,
            ];
        });
    }

    public function commit(
        string $billingTransactionId,
        string $qsTransactionId,
        ?string $serviceStartTimestamp = null,
        array $context = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        return DB::transaction(function () use (
            $billingTransactionId, $qsTransactionId, $serviceStartTimestamp, $context, $ip, $userAgent
        ): array {
            $transaction = Transaction::whereKey($billingTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->isCommitted()) {
                return [
                    'result'                => self::RESULT_OPERATION_COMMITED,
                    'billingTransactionId'  => $transaction->uuid,
                    'billingStartTimestamp' => $transaction->committed_at?->toRfc3339String(),
                ];
            }

            if ($transaction->isFailed()) {
                throw new RuntimeException(
                    "Transaction {$billingTransactionId} is in failed state and cannot be committed."
                );
            }

            $serviceStart = $serviceStartTimestamp
                ? now()->parse($serviceStartTimestamp)
                : now();

            if ($transaction->operation_type === Transaction::OPERATION_REPLACE_SUBSCRIPTION
                && $transaction->old_offer_id !== null
            ) {
                Subscription::where('account_uuid', $transaction->account_uuid)
                    ->where('billing_source', $transaction->billing_source)
                    ->where('lifestream_offer_id', $transaction->old_offer_id)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->update(['status' => Subscription::STATUS_INACTIVE]);
            }

            Subscription::updateOrCreate(
                [
                    'account_uuid'          => $transaction->account_uuid,
                    'billing_source'      => $transaction->billing_source,
                    'lifestream_offer_id' => $transaction->lifestream_offer_id,
                ],
                [
                    'status'       => Subscription::STATUS_ACTIVE,
                    'auto_renewal' => true,
                    'started_at'   => $serviceStart,
                ]
            );

            $transaction->update([
                'phase'                   => Transaction::PHASE_COMMITTED,
                'service_start_timestamp' => $serviceStart,
                'commit_payload'          => $context ?: null,
                'committed_at'            => now(),
            ]);

            $this->logger->log(
                operationType: OperationLog::TYPE_UPSALE_COMMIT,
                result:        OperationLog::RESULT_SUCCESS,
                accountId:     $transaction->account_uuid,
                billingSource: $transaction->billing_source,
                data: [
                    'transaction_id'    => $billingTransactionId,
                    'qs_transaction_id' => $qsTransactionId,
                    'offer_id'          => $transaction->lifestream_offer_id,
                ],
                ip:        $ip,
                userAgent: $userAgent
            );

            return [
                'result'                => self::RESULT_OPERATION_COMMITED,
                'billingTransactionId'  => $transaction->uuid,
                'billingStartTimestamp' => $transaction->fresh()->committed_at?->toRfc3339String(),
            ];
        });
    }

    public function fail(string $billingTransactionId, string $reason): Transaction
    {
        $transaction = Transaction::findOrFail($billingTransactionId);

        $transaction->update(['phase' => Transaction::PHASE_FAILED]);

        $this->logger->log(
            operationType: OperationLog::TYPE_UPSALE_COMMIT,
            result:        OperationLog::RESULT_FAILED,
            accountId:     $transaction->account_uuid,
            billingSource: $transaction->billing_source,
            data:          ['transaction_id' => $billingTransactionId],
            errorMessage:  $reason
        );

        return $transaction->fresh();
    }

    private function findAccount(string $accountId, string $accountLogin): ?Account
    {
        $account = Account::where('lifestream_id', $accountId)->first();

        if ($account === null) {
            $account = Account::where('login', $accountLogin)->first();
        }

        return $account;
    }
}
