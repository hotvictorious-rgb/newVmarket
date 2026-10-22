<?php

namespace Database\Seeders;

use App\Models\DeliveryCorridor;
use App\Models\Lga;
use Illuminate\Database\Seeder;

class InitialDeliveryCorridorsSeeder extends Seeder
{
    /**
     * Create the eight owner-defined Uyo road corridors. Company-specific
     * payables are intentionally NOT seeded: an administrator must negotiate
     * and configure each logistics company's tariff before that company can
     * serve a corridor.
     */
    public function run(): void
    {
        $uyo = Lga::whereRaw('LOWER(TRIM(name)) = ?', ['uyo'])
            ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(name)) = ?', ['akwa ibom']))
            ->first();

        if (!$uyo) {
            return;
        }

        $corridors = [
            [
                'code' => 'uyo-oron-road',
                'name' => 'Oron Road',
                'landmark_aliases' => ['Oron Road', 'Oron Rd'],
            ],
            [
                'code' => 'uyo-nwaniba-road',
                'name' => 'Nwaniba Road',
                'landmark_aliases' => ['Nwaniba Road', 'Nwaniba Rd', 'Ewet Housing'],
            ],
            [
                'code' => 'uyo-aka-road',
                'name' => 'Aka Road',
                'landmark_aliases' => ['Aka Road', 'Aka Rd', 'Shelter Afrique'],
            ],
            [
                'code' => 'uyo-abak-road',
                'name' => 'Abak Road',
                'landmark_aliases' => ['Abak Road', 'Abak Rd'],
            ],
            [
                'code' => 'uyo-ikot-ekpene-road',
                'name' => 'Ikot Ekpene Road',
                'landmark_aliases' => ['Ikot Ekpene Road', 'Ikot Ekpene Rd'],
            ],
            [
                'code' => 'uyo-itu-road',
                'name' => 'Itu Road',
                'landmark_aliases' => ['Itu Road', 'Itu Rd'],
            ],
            [
                'code' => 'uyo-ikpa-road',
                'name' => 'Ikpa Road',
                'landmark_aliases' => ['Ikpa Road', 'Ikpa Rd'],
            ],
            [
                'code' => 'uyo-plaza',
                'name' => 'Plaza',
                'landmark_aliases' => ['Plaza', 'Ibom Plaza', 'Plaza Axis'],
            ],
        ];

        foreach ($corridors as $corridor) {
            DeliveryCorridor::updateOrCreate(
                ['code' => $corridor['code']],
                [
                    'name' => $corridor['name'],
                    'lga_id' => $uyo->id,
                    'landmark_aliases' => $corridor['landmark_aliases'],
                    'estimated_delivery_hours_min' => 2,
                    'estimated_delivery_hours_max' => 4,
                    'is_active' => true,
                ]
            );
        }
    }
}
