<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsaleTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create([
            'external_id'    => 'ext-001',
            'billing_source' => 'region_a',
            'login'          => 'testuser',
            'lifestream_id'  => 'ls-user-abc',
        ]);
    }

    public function test_add_subscription_ensure_returns_200_with_billing_transaction_id(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => 'offer-premium',
                'trialDays'       => 7,
                'qsTransactionId' => 'qs-tx-feature-001',
            ])
        );

        $response->assertStatus(200)
            ->assertJsonPath('result', 'operation_ensured')
            ->assertJsonStructure(['result', 'billingTransactionId']);

        $this->assertNotEmpty($response->json('billingTransactionId'));
        $this->assertDatabaseHas('lifestream_transactions', [
            'uuid'              => $response->json('billingTransactionId'),
            'phase'             => Transaction::PHASE_ENSURE,
            'qs_transaction_id' => 'qs-tx-feature-001',
        ]);
    }

    public function test_add_subscription_ensure_validates_required_fields(): void
    {
        $response = $this->postJson('/api/upsale/v3/add-subscription/ensure');

        $response->assertStatus(422);
    }

    public function test_add_subscription_ensure_returns_no_action_required_for_active_subscription(): void
    {
        // Pre-create active subscription
        \App\Models\Subscription::create([
            'account_uuid'        => $this->account->uuid,
            'billing_source'      => 'region_a',
            'lifestream_offer_id' => 'offer-basic',
            'status'              => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at'          => now(),
        ]);

        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => 'offer-basic',
                'qsTransactionId' => 'qs-tx-feature-002',
            ])
        );

        $response->assertStatus(200)
            ->assertJsonPath('result', 'no_action_required');
    }

    public function test_add_subscription_ensure_returns_no_subscription_rules_for_unknown_account(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-unknown',
                'accountLogin'    => 'nobody',
                'newOfferId'      => 'offer-x',
                'qsTransactionId' => 'qs-tx-feature-003',
            ])
        );

        $response->assertStatus(200)
            ->assertJsonPath('result', 'no_subscription_rules');
    }

    public function test_add_subscription_commit_returns_operation_commited(): void
    {
        // First ensure
        $ensureResponse = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => 'offer-basic',
                'qsTransactionId' => 'qs-tx-feature-004',
            ])
        );
        $ensureResponse->assertStatus(200);
        $billingTxId = $ensureResponse->json('billingTransactionId');

        // Then commit
        $commitResponse = $this->postJson(
            '/api/upsale/v3/add-subscription/commit?' . http_build_query([
                'accountId'              => 'ls-user-abc',
                'accountLogin'           => 'testuser',
                'newOfferId'             => 'offer-basic',
                'qsTransactionId'        => 'qs-tx-feature-004',
                'billingTransactionId'   => $billingTxId,
                'serviceStartTimestamp'  => now()->toRfc3339String(),
            ])
        );

        $commitResponse->assertStatus(200)
            ->assertJsonPath('result', 'operation_commited')
            ->assertJsonStructure(['result', 'billingTransactionId', 'billingStartTimestamp']);
    }

    public function test_add_subscription_commit_returns_409_when_transaction_failed(): void
    {
        // Create a failed transaction directly
        $transaction = Transaction::create([
            'account_uuid'        => $this->account->uuid,
            'billing_source'      => 'region_a',
            'lifestream_offer_id' => 'offer-x',
            'operation_type'      => Transaction::OPERATION_ADD_SUBSCRIPTION,
            'phase'               => Transaction::PHASE_FAILED,
            'qs_transaction_id'   => 'qs-tx-feature-005',
            'ensured_at'          => now(),
        ]);

        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => 'offer-x',
                'qsTransactionId'      => 'qs-tx-feature-005',
                'billingTransactionId' => $transaction->uuid,
            ])
        );

        $response->assertStatus(409);
    }

    public function test_add_subscription_commit_returns_404_for_unknown_transaction(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => 'offer-x',
                'qsTransactionId'      => 'qs-tx-feature-006',
                'billingTransactionId' => '00000000-0000-0000-0000-000000000000',
            ])
        );

        $response->assertStatus(404);
    }

    public function test_add_subscription_commit_validates_uuid_format(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => 'offer-x',
                'qsTransactionId'      => 'qs-tx-feature-007',
                'billingTransactionId' => 'not-a-valid-uuid',
            ])
        );

        $response->assertStatus(422);
    }

    public function test_replace_subscription_ensure_requires_old_offer_id(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/replace-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => 'offer-new',
                // oldOfferId intentionally missing
                'qsTransactionId' => 'qs-tx-feature-008',
            ])
        );

        $response->assertStatus(422);
    }

    public function test_replace_subscription_full_flow(): void
    {
        // Ensure
        $ensureResponse = $this->postJson(
            '/api/upsale/v3/replace-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => 'offer-premium',
                'oldOfferId'      => 'offer-basic',
                'qsTransactionId' => 'qs-tx-feature-009',
            ])
        );
        $ensureResponse->assertStatus(200)
            ->assertJsonPath('result', 'operation_ensured');

        $billingTxId = $ensureResponse->json('billingTransactionId');

        // Commit
        $commitResponse = $this->postJson(
            '/api/upsale/v3/replace-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => 'offer-premium',
                'oldOfferId'           => 'offer-basic',
                'qsTransactionId'      => 'qs-tx-feature-009',
                'billingTransactionId' => $billingTxId,
            ])
        );

        $commitResponse->assertStatus(200)
            ->assertJsonPath('result', 'operation_commited');
    }

    public function test_ensure_with_numeric_lifestream_offer_id(): void
    {
        $response = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => '5010',
                'trialDays'       => 0,
                'qsTransactionId' => 'qs-real-001',
            ])
        );

        $response->assertStatus(200)
            ->assertJsonPath('result', 'operation_ensured');

        $this->assertDatabaseHas('lifestream_transactions', [
            'lifestream_offer_id' => '5010',
            'qs_transaction_id'   => 'qs-real-001',
        ]);
    }

    public function test_replace_subscription_with_real_offer_ids(): void
    {
        \App\Models\Subscription::create([
            'account_uuid'        => $this->account->uuid,
            'billing_source'      => 'region_a',
            'lifestream_offer_id' => '5010',
            'status'              => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at'          => now(),
        ]);

        $ensureResponse = $this->postJson(
            '/api/upsale/v3/replace-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => '5020',
                'oldOfferId'      => '5010',
                'qsTransactionId' => 'qs-real-002',
            ])
        );
        $ensureResponse->assertStatus(200)->assertJsonPath('result', 'operation_ensured');

        $billingTxId = $ensureResponse->json('billingTransactionId');

        $commitResponse = $this->postJson(
            '/api/upsale/v3/replace-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => '5020',
                'oldOfferId'           => '5010',
                'qsTransactionId'      => 'qs-real-002',
                'billingTransactionId' => $billingTxId,
            ])
        );
        $commitResponse->assertStatus(200)->assertJsonPath('result', 'operation_commited');

        $this->assertDatabaseHas('lifestream_subscriptions', [
            'lifestream_offer_id' => '5010',
            'status'              => \App\Models\Subscription::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('lifestream_subscriptions', [
            'lifestream_offer_id' => '5020',
            'status'              => \App\Models\Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_ensure_idempotent_after_commit(): void
    {
        $ensureResponse = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => '5010',
                'trialDays'       => 0,
                'qsTransactionId' => 'qs-real-003',
            ])
        );
        $ensureResponse->assertStatus(200);
        $billingTxId = $ensureResponse->json('billingTransactionId');

        $this->postJson(
            '/api/upsale/v3/add-subscription/commit?' . http_build_query([
                'accountId'            => 'ls-user-abc',
                'accountLogin'         => 'testuser',
                'newOfferId'           => '5010',
                'qsTransactionId'      => 'qs-real-003',
                'billingTransactionId' => $billingTxId,
            ])
        )->assertStatus(200);

        $repeat = $this->postJson(
            '/api/upsale/v3/add-subscription/ensure?' . http_build_query([
                'accountId'       => 'ls-user-abc',
                'accountLogin'    => 'testuser',
                'newOfferId'      => '5010',
                'trialDays'       => 0,
                'qsTransactionId' => 'qs-real-003',
            ])
        );
        $repeat->assertStatus(200);
        $this->assertSame($billingTxId, $repeat->json('billingTransactionId'));
    }
}
