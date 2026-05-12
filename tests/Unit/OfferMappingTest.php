<?php

namespace Tests\Unit;

use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_package_code_maps_to_different_offers_in_different_regions(): void
    {
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-100',
            'lifestream_offer_id'  => 'ls-offer-region-a-premium',
            'name'                 => 'Region A Premium',
            'price'                => 499.00,
            'auto_renewal'         => true,
            'is_active'            => true,
        ]);

        Offer::create([
            'billing_source'       => 'region_b',
            'billing_package_code' => 'pkg-100',
            'lifestream_offer_id'  => 'ls-offer-region-b-premium',
            'name'                 => 'Region B Premium',
            'price'                => 599.00,
            'auto_renewal'         => true,
            'is_active'            => true,
        ]);

        $offerIdA = Offer::findLifestreamOfferId('region_a', 'pkg-100');
        $offerIdB = Offer::findLifestreamOfferId('region_b', 'pkg-100');

        $this->assertSame('ls-offer-region-a-premium', $offerIdA);
        $this->assertSame('ls-offer-region-b-premium', $offerIdB);
        $this->assertNotSame($offerIdA, $offerIdB);
    }

    public function test_returns_null_when_no_mapping_exists(): void
    {
        $offerId = Offer::findLifestreamOfferId('region_a', 'non-existent-pkg');

        $this->assertNull($offerId);
    }

    public function test_inactive_offer_is_not_returned(): void
    {
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-inactive',
            'lifestream_offer_id'  => 'ls-offer-inactive',
            'is_active'            => false,
        ]);

        $offerId = Offer::findLifestreamOfferId('region_a', 'pkg-inactive');

        $this->assertNull($offerId);
    }

    public function test_unique_constraint_prevents_duplicate_mappings(): void
    {
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-200',
            'lifestream_offer_id'  => 'ls-offer-200',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-200',
            'lifestream_offer_id'  => 'ls-offer-200-duplicate',
        ]);
    }

    public function test_scope_for_region_filters_correctly(): void
    {
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-a1',
            'lifestream_offer_id'  => 'ls-a1',
            'is_active'            => true,
        ]);
        Offer::create([
            'billing_source'       => 'region_b',
            'billing_package_code' => 'pkg-b1',
            'lifestream_offer_id'  => 'ls-b1',
            'is_active'            => true,
        ]);

        $regionAOffers = Offer::forRegion('region_a')->get();
        $regionBOffers = Offer::forRegion('region_b')->get();

        $this->assertCount(1, $regionAOffers);
        $this->assertCount(1, $regionBOffers);
        $this->assertSame('ls-a1', $regionAOffers->first()->lifestream_offer_id);
        $this->assertSame('ls-b1', $regionBOffers->first()->lifestream_offer_id);
    }

    public function test_active_scope_filters_inactive_offers(): void
    {
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-active',
            'lifestream_offer_id'  => 'ls-active',
            'is_active'            => true,
        ]);
        Offer::create([
            'billing_source'       => 'region_a',
            'billing_package_code' => 'pkg-inactive',
            'lifestream_offer_id'  => 'ls-inactive',
            'is_active'            => false,
        ]);

        $activeOffers = Offer::active()->get();

        $this->assertCount(1, $activeOffers);
        $this->assertSame('ls-active', $activeOffers->first()->lifestream_offer_id);
    }
}
