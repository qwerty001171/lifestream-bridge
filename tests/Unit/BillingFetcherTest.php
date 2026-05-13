<?php

namespace Tests\Unit;

use App\Adapters\FakeBillingAdapter;
use App\Services\BillingFetcher;
use Tests\TestCase;

class BillingFetcherTest extends TestCase
{
    public function test_fetches_all_users_from_single_page(): void
    {
        $users = [
            ['external_id' => '1', 'login' => 'alice'],
            ['external_id' => '2', 'login' => 'bob'],
        ];

        $adapter = new FakeBillingAdapter('region_a', [$users]);
        $fetcher = new BillingFetcher($adapter);

        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(2, $fetched);
        $this->assertSame('alice', $fetched[0]['login']);
        $this->assertSame('bob', $fetched[1]['login']);
    }

    public function test_fetches_all_users_across_multiple_pages(): void
    {
        $page1 = [
            ['external_id' => '1', 'login' => 'user1'],
            ['external_id' => '2', 'login' => 'user2'],
        ];
        $page2 = [
            ['external_id' => '3', 'login' => 'user3'],
        ];
        $page3 = [
            ['external_id' => '4', 'login' => 'user4'],
            ['external_id' => '5', 'login' => 'user5'],
        ];

        $adapter = new FakeBillingAdapter('region_a', [$page1, $page2, $page3]);
        $fetcher = new BillingFetcher($adapter);

        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(5, $fetched);
        $this->assertSame('user1', $fetched[0]['login']);
        $this->assertSame('user5', $fetched[4]['login']);
    }

    public function test_stops_pagination_when_nextPage_is_false(): void
    {
        // Only one page (nextPage will be false for page 1 when no page 2 exists)
        $page1 = [
            ['external_id' => '1', 'login' => 'only_user'],
        ];

        $adapter = new FakeBillingAdapter('region_a', [$page1]);
        $fetcher = new BillingFetcher($adapter);

        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(1, $fetched);
    }

    public function test_handles_empty_response(): void
    {
        $adapter = new FakeBillingAdapter('region_a', [[]]);
        $fetcher = new BillingFetcher($adapter);

        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(0, $fetched);
    }

    public function test_handles_no_pages_configured(): void
    {
        $adapter = new FakeBillingAdapter('region_a', []);
        $fetcher = new BillingFetcher($adapter);

        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(0, $fetched);
    }

    public function test_stops_at_max_pages_to_prevent_infinite_loop(): void
    {
        $infiniteAdapter = new class('region_a') extends FakeBillingAdapter {
            public function getUsers(int $page = 1, int $limit = 1000): array
            {
                return [
                    'data'       => ['users' => [['external_id' => $page, 'login' => "user{$page}"]]],
                    'pagination' => ['nextPage' => true],
                ];
            }
        };

        $fetcher = new BillingFetcher($infiniteAdapter, 1000, 3);
        $fetched = iterator_to_array($fetcher->fetchAll(), false);

        $this->assertCount(3, $fetched);
        $this->assertSame('user1', $fetched[0]['login']);
        $this->assertSame('user3', $fetched[2]['login']);
    }

    public function test_fetcher_returns_correct_source(): void
    {
        $adapter = new FakeBillingAdapter('region_b', []);
        $fetcher = new BillingFetcher($adapter);

        $this->assertSame('region_b', $fetcher->getAdapter()->getSource());
    }

    public function test_pagination_calls_adapter_correct_number_of_times(): void
    {
        $callCount = 0;

        $adapter = new class('region_a') extends FakeBillingAdapter {
            public int $calls = 0;

            public function __construct(string $region)
            {
                parent::__construct($region, [
                    [['external_id' => '1', 'login' => 'u1']],
                    [['external_id' => '2', 'login' => 'u2']],
                    [['external_id' => '3', 'login' => 'u3']],
                ]);
            }

            public function getUsers(int $page = 1, int $limit = 1000): array
            {
                $this->calls++;

                return parent::getUsers($page, $limit);
            }
        };

        $fetcher = new BillingFetcher($adapter);
        iterator_to_array($fetcher->fetchAll(), false);

        // Should have called getUsers 3 times (once per page)
        $this->assertSame(3, $adapter->calls);
    }
}
