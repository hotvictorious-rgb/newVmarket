<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\State;
use Illuminate\Database\Seeder;

class InitialDeliveryLanesSeeder extends Seeder
{
    public function run(): void
    {
        $nigeria = Country::where('iso_code', 'NGA')->first();
        if (!$nigeria) {
            $this->command->error('Run NigeriaGeographySeeder first.');
            return;
        }

        $akwaIbom = State::where('country_id', $nigeria->id)->where('name', 'Akwa Ibom')->first();
        if (!$akwaIbom) {
            $this->command->error('Akwa Ibom state not found.');
            return;
        }

        $uyo = Lga::where('state_id', $akwaIbom->id)->where('name', 'Uyo')->first();
        $eket = Lga::where('state_id', $akwaIbom->id)->where('name', 'Eket')->first();
        $ikotEkpene = Lga::where('state_id', $akwaIbom->id)->where('name', 'Ikot Ekpene')->first();
        $oron = Lga::where('state_id', $akwaIbom->id)->where('name', 'Oron')->first();

        if (!$uyo || !$eket || !$ikotEkpene || !$oron) {
            $this->command->error('Core Akwa Ibom LGAs not found.');
            return;
        }

        $lanes = [
            // Intra-LGA dispatch
            ['origin' => $uyo->id, 'dest' => $uyo->id, 'fee' => '500.00', 'min_h' => 2, 'max_h' => 6, 'company_base' => '450.00', 'company_bulky' => '800.00'],
            ['origin' => $eket->id, 'dest' => $eket->id, 'fee' => '500.00', 'min_h' => 2, 'max_h' => 6, 'company_base' => '450.00', 'company_bulky' => '800.00'],
            ['origin' => $ikotEkpene->id, 'dest' => $ikotEkpene->id, 'fee' => '500.00', 'min_h' => 2, 'max_h' => 6, 'company_base' => '450.00', 'company_bulky' => '800.00'],
            ['origin' => $oron->id, 'dest' => $oron->id, 'fee' => '500.00', 'min_h' => 2, 'max_h' => 6, 'company_base' => '450.00', 'company_bulky' => '800.00'],

            // Directional Inter-LGA transit
            ['origin' => $uyo->id, 'dest' => $eket->id, 'fee' => '1500.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1200.00', 'company_bulky' => '800.00'],
            ['origin' => $eket->id, 'dest' => $uyo->id, 'fee' => '1500.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1200.00', 'company_bulky' => '800.00'],
            ['origin' => $uyo->id, 'dest' => $ikotEkpene->id, 'fee' => '1200.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1000.00', 'company_bulky' => '800.00'],
            ['origin' => $ikotEkpene->id, 'dest' => $uyo->id, 'fee' => '1200.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1000.00', 'company_bulky' => '800.00'],
            ['origin' => $uyo->id, 'dest' => $oron->id, 'fee' => '1500.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1200.00', 'company_bulky' => '800.00'],
            ['origin' => $oron->id, 'dest' => $uyo->id, 'fee' => '1500.00', 'min_h' => 12, 'max_h' => 24, 'company_base' => '1200.00', 'company_bulky' => '800.00'],
        ];

        foreach ($lanes as $l) {
            DeliveryLane::updateOrCreate([
                'origin_lga_id' => $l['origin'],
                'destination_lga_id' => $l['dest'],
            ], [
                'base_fee' => $l['fee'],
                'bulky_surcharge' => '1000.00',
                'logistics_company_base_payable' => $l['company_base'],
                'logistics_company_bulky_surcharge' => $l['company_bulky'],
                'estimated_delivery_hours_min' => $l['min_h'],
                'estimated_delivery_hours_max' => $l['max_h'],
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ Seeded directional delivery lanes.');
    }
}
