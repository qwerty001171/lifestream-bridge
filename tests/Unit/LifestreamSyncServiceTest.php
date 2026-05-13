<?php

namespace Tests\Unit;

use App\Adapters\FakeBillingAdapter;
use App\Contracts\LifestreamClientInterface;
use App\Models\Account;
use App\Models\Device;
use App\Models\Offer;
use App\Models\Subscription;
use App\Services\LifestreamSyncService;
use App\Services\OperationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LifestreamSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private LifestreamSyncService $service;
    private LifestreamClientInterface $lifestream;
    private OperationLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifestream = Mockery::mock(LifestreamClientInterface::class);
        $this->logger     = Mockery::mock(OperationLogger::class);
        $this->logger->shouldReceive('log')->zeroOrMoreTimes();

        $this->service = new LifestreamSyncService($this->lifestream, $this->logger);
    }

    private function account(string $externalId, array $overrides = []): Account
    {
        return Account::create(array_merge([
            'external_id'    => $externalId,
            'billing_source' => 'src_a',
            'login'          => $externalId,
        ], $overrides));
    }

    private function offer(string $source, string $packageCode, string $offerId): Offer
    {
        return Offer::create([
            'billing_source'      => $source,
            'billing_package_code' => $packageCode,
            'lifestream_offer_id' => $offerId,
            'is_active'           => true,
        ]);
    }

    public function test_new_account_gets_created_in_lifestream(): void
    {
        $account = $this->account('001', ['login' => 'user001', 'email' => 'u@example.com']);

        $this->lifestream
            ->shouldReceive('createAccount')
            ->once()
            ->with(Mockery::subset(['username' => 'user001']))
            ->andReturn(['id' => 'ls-001', 'created' => true]);

        $result = $this->service->syncSource('src_a');

        // Account created in Lifestream, but counted as skipped — no paket to subscribe
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('accounts', ['uuid' => $account->uuid, 'lifestream_id' => 'ls-001']);
    }

    public function test_account_with_existing_lifestream_id_skips_create(): void
    {
        $this->account('001', ['lifestream_id' => 'ls-existing']);

        $this->lifestream->shouldNotReceive('createAccount');
        $this->lifestream->shouldNotReceive('manageSubscription');

        $result = $this->service->syncSource('src_a');

        $this->assertSame(1, $result['skipped']);
    }

    public function test_subscription_activated_when_offer_mapped(): void
    {
        $account = $this->account('002', ['lifestream_id' => 'ls-002', 'paket' => 'pkg-100']);
        $this->offer('src_a', 'pkg-100', 'ls-offer-100');

        $this->lifestream
            ->shouldReceive('manageSubscription')
            ->once()
            ->with('ls-002', 'ls-offer-100', true);

        $result = $this->service->syncSource('src_a');

        $this->assertSame(1, $result['synced']);
        $this->assertDatabaseHas('lifestream_subscriptions', [
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'ls-offer-100',
            'status'              => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_old_subscription_deactivated_when_paket_changed(): void
    {
        $account = $this->account('003', ['lifestream_id' => 'ls-003', 'paket' => 'pkg-200']);
        $this->offer('src_a', 'pkg-200', 'ls-offer-200');

        Subscription::create([
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'ls-offer-100',
            'status'              => Subscription::STATUS_ACTIVE,
            'started_at'          => now()->subMonth(),
        ]);

        $this->lifestream->shouldReceive('manageSubscription')->with('ls-003', 'ls-offer-100', false)->once();
        $this->lifestream->shouldReceive('manageSubscription')->with('ls-003', 'ls-offer-200', true)->once();

        $this->service->syncSource('src_a');

        $this->assertDatabaseHas('lifestream_subscriptions', [
            'lifestream_offer_id' => 'ls-offer-100',
            'status'              => Subscription::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('lifestream_subscriptions', [
            'lifestream_offer_id' => 'ls-offer-200',
            'status'              => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_already_active_subscription_not_reactivated(): void
    {
        $account = $this->account('001', ['lifestream_id' => 'ls-001', 'paket' => 'pkg-100']);
        $this->offer('src_a', 'pkg-100', 'ls-offer-100');

        Subscription::create([
            'account_uuid'        => $account->uuid,
            'lifestream_offer_id' => 'ls-offer-100',
            'status'              => Subscription::STATUS_ACTIVE,
            'started_at'          => now()->subDay(),
        ]);

        $this->lifestream->shouldNotReceive('manageSubscription');

        $result = $this->service->syncSource('src_a');

        $this->assertSame(1, $result['synced']);
    }

    public function test_device_marked_synced_after_account_sync(): void
    {
        $account = $this->account('001', ['lifestream_id' => 'ls-001', 'mac' => 'aa:bb:cc:dd:ee:ff']);

        Device::create([
            'account_uuid'        => $account->uuid,
            'mac'                 => 'aa:bb:cc:dd:ee:ff',
            'synced_to_lifestream' => false,
        ]);

        $this->lifestream->shouldNotReceive('manageSubscription');

        $this->service->syncSource('src_a');

        $this->assertDatabaseHas('devices', [
            'account_uuid'        => $account->uuid,
            'synced_to_lifestream' => true,
        ]);
    }

    public function test_failed_account_counted_but_others_continue(): void
    {
        $this->account('001', ['login' => 'ok_user', 'lifestream_id' => 'ls-001']);
        $this->account('002', ['login' => 'bad-user-[invalid]']);
        $this->account('003', ['login' => 'ok_user3', 'lifestream_id' => 'ls-003']);

        $this->lifestream
            ->shouldReceive('createAccount')
            ->once()
            ->andThrow(new \RuntimeException('API down'));

        $result = $this->service->syncSource('src_a');

        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, $result['failed']);
    }

    public function test_only_accounts_from_given_source_are_synced(): void
    {
        $this->account('001', ['billing_source' => 'src_a', 'lifestream_id' => 'ls-001']);
        $this->account('002', ['billing_source' => 'src_b', 'lifestream_id' => 'ls-002']);

        $this->lifestream->shouldNotReceive('createAccount');
        $this->lifestream->shouldNotReceive('manageSubscription');

        $result = $this->service->syncSource('src_a');

        $this->assertSame(1, $result['skipped']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
