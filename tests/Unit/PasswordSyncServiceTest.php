<?php

namespace Tests\Unit;

use App\Adapters\FakeBillingAdapter;
use App\Contracts\LifestreamClientInterface;
use App\Models\Account;
use App\Services\BillingAdapterFactory;
use App\Services\OperationLogger;
use App\Services\PasswordSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PasswordSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordSyncService $service;
    private LifestreamClientInterface $lifestream;
    private OperationLogger $logger;
    private BillingAdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifestream = Mockery::mock(LifestreamClientInterface::class);
        $this->logger     = Mockery::mock(OperationLogger::class);
        $this->logger->shouldReceive('log')->zeroOrMoreTimes();
    }

    private function makeService(array $pages): PasswordSyncService
    {
        $adapter       = new FakeBillingAdapter('src_a', $pages);
        $this->factory = Mockery::mock(BillingAdapterFactory::class);
        $this->factory->shouldReceive('make')->with('src_a')->andReturn($adapter);

        return new PasswordSyncService($this->lifestream, $this->logger, $this->factory);
    }

    private function account(string $externalId, array $overrides = []): Account
    {
        return Account::create(array_merge([
            'external_id'    => $externalId,
            'billing_source' => 'src_a',
            'login'          => $externalId,
            'lifestream_id'  => 'ls-' . $externalId,
        ], $overrides));
    }

    private function row(string $externalId, ?string $password): array
    {
        return ['id' => $externalId, 'decoded_password' => $password];
    }

    public function test_password_pushed_when_changed(): void
    {
        $account = $this->account('001');
        $service = $this->makeService([[$this->row('001', 'newpass')]]);

        $this->lifestream
            ->shouldReceive('resetPassword')
            ->once()
            ->with('ls-001', 'newpass');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('accounts', [
            'uuid'          => $account->uuid,
            'password_hash' => hash('sha256', 'newpass'),
        ]);
    }

    public function test_password_skipped_when_hash_unchanged(): void
    {
        $account          = $this->account('001', ['password_hash' => hash('sha256', 'samepass')]);
        $service          = $this->makeService([[$this->row('001', 'samepass')]]);

        $this->lifestream->shouldNotReceive('resetPassword');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_skipped_when_no_plain_password(): void
    {
        $this->account('001');
        $service = $this->makeService([[$this->row('001', null)]]);

        $this->lifestream->shouldNotReceive('resetPassword');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['skipped']);
    }

    public function test_skipped_when_account_has_no_lifestream_id(): void
    {
        $this->account('001', ['lifestream_id' => null]);
        $service = $this->makeService([[$this->row('001', 'pass')]]);

        $this->lifestream->shouldNotReceive('resetPassword');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['skipped']);
    }

    public function test_skipped_when_account_not_in_db(): void
    {
        $service = $this->makeService([[$this->row('ghost', 'pass')]]);

        $this->lifestream->shouldNotReceive('resetPassword');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['skipped']);
    }

    public function test_failed_counted_when_lifestream_throws(): void
    {
        $this->account('001');
        $service = $this->makeService([[$this->row('001', 'newpass')]]);

        $this->lifestream
            ->shouldReceive('resetPassword')
            ->andThrow(new \RuntimeException('API error'));

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['failed']);
        $this->assertDatabaseMissing('accounts', ['password_hash' => hash('sha256', 'newpass')]);
    }

    public function test_multiple_accounts_mixed_results(): void
    {
        $this->account('001');
        $this->account('002', ['password_hash' => hash('sha256', 'unchanged')]);
        $this->account('003', ['lifestream_id' => null]);

        $service = $this->makeService([[
            $this->row('001', 'newpass'),
            $this->row('002', 'unchanged'),
            $this->row('003', 'anypass'),
        ]]);

        $this->lifestream
            ->shouldReceive('resetPassword')
            ->once()
            ->with('ls-001', 'newpass');

        $result = $service->syncPasswords('src_a');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $result['failed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
