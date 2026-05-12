<?php

namespace Database\Seeders;

use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $offers = [
            // region_a — реальные коды пакетов от провайдера
            // TODO: уточнить реальные lifestream_offer_id для каждого пакета
            ['billing_source' => 'region_a', 'billing_package_code' => '6',    'lifestream_offer_id' => '5010', 'name' => 'iTV Unlim 1 Family (до 60 Мбит/с)',  'is_active' => true],
            ['billing_source' => 'region_a', 'billing_package_code' => '7',    'lifestream_offer_id' => '5020', 'name' => 'iTV Unlim 2 Family (до 80 Мбит/с)',  'is_active' => true],
            ['billing_source' => 'region_a', 'billing_package_code' => '22',   'lifestream_offer_id' => '5030', 'name' => 'iTV Unlim 3 Family (до 100 Мбит/с)', 'is_active' => true],
            ['billing_source' => 'region_a', 'billing_package_code' => '180',  'lifestream_offer_id' => '5040', 'name' => 'Пакет 180',                          'is_active' => true],
            ['billing_source' => 'region_a', 'billing_package_code' => '237',  'lifestream_offer_id' => '5050', 'name' => 'Пакет 237',                          'is_active' => true],
        ];

        foreach ($offers as $offer) {
            Offer::updateOrCreate(
                ['billing_source' => $offer['billing_source'], 'billing_package_code' => $offer['billing_package_code']],
                $offer
            );
        }

        $this->command->info('Seeded ' . count($offers) . ' offer mappings.');
    }
}
