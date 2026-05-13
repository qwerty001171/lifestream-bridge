<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\OperationLogger;
use App\Services\UpsaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UpsaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private UpsaleService $service;
    private OperationLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger  = Mockery::mock(OperationLogger::class);
        $this->logger->shouldReceive('log')->zeroOrMoreTimes();

        $this->service = new UpsaleService($this->logger);
    }

    private function createAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'external_id'    => 'ext-001',
            'billing_source' => 'region_a',
            'login'          => 'testuser',
            'lifestream_id'  => 'ls-abc123',
        ], $overrides));
    }

    public function test_ensure_creates_transaction_and_returns_ensured_result(): void
    {
        $account = $this->createAccount();

        $result = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-premium',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-001',
            trialDays:       7,
        );

        $this->assertSame(UpsaleService::RESULT_OPERATION_ENSURED, $result['result']);
        $this->assertNotEmpty($result['billingTransactionId']);

        $this->assertDatabaseHas('lifestream_transactions', [
            'uuid'              => $result['billingTransactionId'],
            'phase'             => Transaction::PHASE_ENSURE,
            'qs_transaction_id' => 'qs-tx-001',
            'lifestream_offer_id' => 'offer-premium',
            'operation_type'    => Transaction::OPERATION_ADD_SUBSCRIPTION,
        ]);
    }

    public function test_ensure_returns_no_action_required_when_subscription_already_active(): void
    {
        $account = $this->createAccount();

        Subscription::create([
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'offer-premium',
            'status'              => Subscription::STATUS_ACTIVE,
            'started_at'          => now(),
        ]);

        $result = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-premium',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-002',
        );

        $this->assertSame(UpsaleService::RESULT_NO_ACTION_REQUIRED, $result['result']);
    }

    public function test_ensure_returns_no_subscription_rules_for_unknown_account(): void
    {
        $result = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-unknown',
            accountLogin:    'nobody',
            newOfferId:      'offer-x',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-003',
        );

        $this->assertSame(UpsaleService::RESULT_NO_SUBSCRIPTION_RULES, $result['result']);
    }

    public function test_ensure_finds_account_by_login_when_lifestream_id_not_matched(): void
    {
        Account::create([
            'external_id'    => 'ext-002',
            'billing_source' => 'region_a',
            'login'          => 'fallback_user',
            'lifestream_id'  => null,
        ]);

        $result = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-id-that-does-not-exist',
            accountLogin:    'fallback_user',
            newOfferId:      'offer-x',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-fallback',
        );

        $this->assertSame(UpsaleService::RESULT_OPERATION_ENSURED, $result['result']);
    }

    public function test_ensure_is_idempotent_on_same_qs_transaction_id(): void
    {
        $account = $this->createAccount();

        $first = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-basic',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-idempotent',
        );

        $second = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-basic',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-idempotent',
        );

        $this->assertSame($first['billingTransactionId'], $second['billingTransactionId']);
        $this->assertDatabaseCount('lifestream_transactions', 1);
    }

    public function test_commit_transitions_transaction_to_committed(): void
    {
        $account = $this->createAccount();

        $ensureResult = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-basic',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-004',
        );

        $commitResult = $this->service->commit(
            billingTransactionId:  $ensureResult['billingTransactionId'],
            qsTransactionId:       'qs-tx-004',
            serviceStartTimestamp: now()->toRfc3339String(),
        );

        $this->assertSame(UpsaleService::RESULT_OPERATION_COMMITED, $commitResult['result']);
        $this->assertSame($ensureResult['billingTransactionId'], $commitResult['billingTransactionId']);
        $this->assertNotEmpty($commitResult['billingStartTimestamp']);

        $this->assertDatabaseHas('lifestream_transactions', [
            'uuid'  => $ensureResult['billingTransactionId'],
            'phase' => Transaction::PHASE_COMMITTED,
        ]);

        // Subscription should be active
        $this->assertDatabaseHas('lifestream_subscriptions', [
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'offer-basic',
            'status'              => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_commit_is_idempotent_when_already_committed(): void
    {
        $account = $this->createAccount();

        $ensureResult = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-x',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-005',
        );

        $this->service->commit($ensureResult['billingTransactionId'], 'qs-tx-005');
        $secondCommit = $this->service->commit($ensureResult['billingTransactionId'], 'qs-tx-005');

        $this->assertSame(UpsaleService::RESULT_OPERATION_COMMITED, $secondCommit['result']);
    }

    public function test_commit_throws_when_transaction_in_failed_state(): void
    {
        $account = $this->createAccount();

        $ensureResult = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-y',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-006',
        );

        $this->service->fail($ensureResult['billingTransactionId'], 'test failure');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed state/');

        $this->service->commit($ensureResult['billingTransactionId'], 'qs-tx-006');
    }

    public function test_replace_subscription_commit_deactivates_old_subscription(): void
    {
        $account = $this->createAccount();

        // Existing active subscription for old offer
        Subscription::create([
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'offer-old',
            'status'              => Subscription::STATUS_ACTIVE,
            'started_at'          => now()->subMonth(),
        ]);

        $ensureResult = $this->service->ensure(
            operationType:   Transaction::OPERATION_REPLACE_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-new',
            oldOfferId:      'offer-old',
            qsTransactionId: 'qs-tx-007',
        );

        $this->service->commit($ensureResult['billingTransactionId'], 'qs-tx-007');

        $this->assertDatabaseHas('lifestream_subscriptions', [
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'offer-old',
            'status'              => Subscription::STATUS_INACTIVE,
        ]);

        $this->assertDatabaseHas('lifestream_subscriptions', [
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'offer-new',
            'status'              => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_fail_marks_transaction_as_failed(): void
    {
        $account = $this->createAccount();

        $ensureResult = $this->service->ensure(
            operationType:   Transaction::OPERATION_ADD_SUBSCRIPTION,
            accountId:       'ls-abc123',
            accountLogin:    'testuser',
            newOfferId:      'offer-z',
            oldOfferId:      null,
            qsTransactionId: 'qs-tx-008',
        );

        $failed = $this->service->fail($ensureResult['billingTransactionId'], 'payment declined');

        $this->assertSame(Transaction::PHASE_FAILED, $failed->phase);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
