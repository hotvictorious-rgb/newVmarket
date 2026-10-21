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
        if (!$uyo) {
            $this->command->error('Uyo LGA not found.');
            return;
        }

        // The only route seeded for new transactions is Uyo-to-Uyo.
        DeliveryLane::updateOrCreate([
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $uyo->id,
        ], [
            'base_fee' => '500.00',
            'bulky_surcharge' => '1000.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        // Preserve historical lane rows for accounting/audit, but never let the seeder
        // reactivate an inter-LGA or inter-state route.
        DeliveryLane::where(function ($query) use ($uyo) {
            $query->where('origin_lga_id', '!=', $uyo->id)
                ->orWhere('destination_lga_id', '!=', $uyo->id);
        })->update(['is_active' => false]);

        $this->command->info('Seeded Uyo-only delivery lane and disabled all non-Uyo lanes.');
    }
}
