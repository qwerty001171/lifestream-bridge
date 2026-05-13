<?php

namespace Tests\Unit;

use App\Adapters\FakeBillingAdapter;
use App\Models\Account;
use App\Services\AccountSyncService;
use App\Services\BillingFetcher;
use App\Services\OperationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AccountSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountSyncService $service;
    private OperationLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger  = Mockery::mock(OperationLogger::class);
        $this->logger->shouldReceive('log')->zeroOrMoreTimes();

        $this->service = new AccountSyncService($this->logger);
    }

    public function test_creates_accounts_that_do_not_exist(): void
    {
        $adapter = new FakeBillingAdapter('region_a', [[
            ['id' => 'ext-1', 'login' => 'alice', 'email' => 'alice@example.com'],
            ['id' => 'ext-2', 'login' => 'bob',   'email' => 'bob@example.com'],
        ]]);

        $fetcher = new BillingFetcher($adapter);
        $result  = $this->service->sync($fetcher, 'region_a');

        $this->assertSame(2, $result['synced']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('accounts', ['external_id' => 'ext-1', 'billing_source' => 'region_a']);
        $this->assertDatabaseHas('accounts', ['external_id' => 'ext-2', 'billing_source' => 'region_a']);
    }

    public function test_updates_existing_accounts_idempotently(): void
    {
        Account::create([
            'external_id'    => 'ext-1',
            'billing_source' => 'region_a',
            'login'          => 'old_login',
        ]);

        $adapter = new FakeBillingAdapter('region_a', [[
            ['id' => 'ext-1', 'login' => 'new_login', 'email' => 'new@example.com'],
        ]]);

        $fetcher = new BillingFetcher($adapter);
        $this->service->sync($fetcher, 'region_a');

        $this->assertDatabaseHas('accounts', [
            'external_id' => 'ext-1',
            'login'       => 'new_login',
            'email'       => 'new@example.com',
        ]);
        $this->assertSame(1, Account::count());
    }

    public function test_sync_is_idempotent_on_multiple_runs(): void
    {
        $users = [
            ['id' => 'u1', 'login' => 'user1'],
            ['id' => 'u2', 'login' => 'user2'],
        ];

        $adapter1 = new FakeBillingAdapter('region_a', [$users]);
        $fetcher1 = new BillingFetcher($adapter1);
        $this->service->sync($fetcher1, 'region_a');

        $adapter2 = new FakeBillingAdapter('region_a', [$users]);
        $fetcher2 = new BillingFetcher($adapter2);
        $this->service->sync($fetcher2, 'region_a');

        $this->assertSame(2, Account::count());
    }

    public function test_creates_device_when_mac_is_present(): void
    {
        $adapter = new FakeBillingAdapter('region_a', [[
            ['id' => 'ext-1', 'login' => 'alice', 'mac' => 'aabbccddeeff'],
        ]]);

        $fetcher = new BillingFetcher($adapter);
        $this->service->sync($fetcher, 'region_a');

        $account = Account::where('external_id', 'ext-1')->first();
        $this->assertNotNull($account);
        $this->assertSame(1, $account->devices()->count());
    }

    public function test_syncs_accounts_across_multiple_pages(): void
    {
        $adapter = new FakeBillingAdapter('region_a', [
            [
                ['id' => 'p1u1', 'login' => 'user1'],
                ['id' => 'p1u2', 'login' => 'user2'],
            ],
            [
                ['id' => 'p2u1', 'login' => 'user3'],
            ],
        ]);

        $fetcher = new BillingFetcher($adapter);
        $result  = $this->service->sync($fetcher, 'region_a');

        $this->assertSame(3, $result['synced']);
        $this->assertSame(3, Account::count());
    }

    public function test_syncs_from_billing_api_nested_user_main_structure(): void
    {
        $adapter = new FakeBillingAdapter('region_a', [[
            [
                'User' => [
                    'id'                    => '10411',
                    'mid'                   => '10406',
                    'name'                  => 'jn03ekud_iptv',
                    'mac'                   => '1c:a0:ef:d8:0e:c1',
                    'paket'                 => '5',
                    'email'                 => null,
                    'decoded_password'      => 'QRb49nC',
                ],
                'Main' => [
                    'id'    => '10406',
                    'name'  => 'jn03ekud',
                    'paket' => '6',
                    'email' => 'user@example.com',
                ],
            ],
        ]]);

        $fetcher = new BillingFetcher($adapter);
        $result  = $this->service->sync($fetcher, 'region_a');

        $this->assertSame(1, $result['synced']);

        $this->assertDatabaseHas('accounts', [
            'external_id'    => '10406',
            'billing_source' => 'region_a',
            'login'          => 'jn03ekud_iptv',
            'email'          => 'user@example.com',
            'paket'          => '6',
        ]);

        $account = Account::where('external_id', '10406')->first();
        $this->assertSame(1, $account->devices()->count());
    }

    public function test_source_isolation(): void
    {
        $adapterA = new FakeBillingAdapter('region_a', [[
            ['id' => 'ext-1', 'login' => 'user_a'],
        ]]);
        $adapterB = new FakeBillingAdapter('region_b', [[
            ['id' => 'ext-1', 'login' => 'user_b'],
        ]]);

        $this->service->sync(new BillingFetcher($adapterA), 'region_a');
        $this->service->sync(new BillingFetcher($adapterB), 'region_b');

        $this->assertSame(2, Account::count());
        $this->assertDatabaseHas('accounts', ['external_id' => 'ext-1', 'billing_source' => 'region_a', 'login' => 'user_a']);
        $this->assertDatabaseHas('accounts', ['external_id' => 'ext-1', 'billing_source' => 'region_b', 'login' => 'user_b']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
