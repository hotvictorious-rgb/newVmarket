<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Lga;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class NigeriaGeographySeeder extends Seeder
{
    public function run(): void
    {
        $nigeria = Country::firstOrCreate([
            'iso_code' => 'NGA',
        ], [
            'name' => 'Nigeria',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $jsonPath = database_path('seeders/data/nigeria-states-lgas.json');
        if (!File::exists($jsonPath)) {
            $this->command->error("Geography data file not found at: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);
        if (!$data || !isset($data['states'])) {
            $this->command->error('Invalid geography JSON structure');
            return;
        }

        foreach ($data['states'] as $stateData) {
            $state = State::firstOrCreate([
                'country_id' => $nigeria->id,
                'name' => $stateData['name'],
            ], [
                'state_code' => $stateData['code'] ?? null,
                'is_active' => true,
            ]);

            foreach ($stateData['lgas'] as $lgaName) {
                Lga::firstOrCreate([
                    'state_id' => $state->id,
                    'name' => $lgaName,
                ], [
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('✅ Seeded canonical Nigeria geography (States & LGAs).');
    }
}
