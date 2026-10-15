<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Lga;
use App\Models\State;
use Illuminate\Http\JsonResponse;

class GeographyApiController extends Controller
{
    public function countries(): JsonResponse
    {
        $countries = Country::where('is_active', true)->select('id', 'name', 'iso_code', 'phone_code', 'currency_code')->get();

        return response()->json([
            'status' => true,
            'message' => 'Countries retrieved successfully.',
            'data' => $countries,
        ]);
    }

    public function states(int $countryId): JsonResponse
    {
        $states = State::where('country_id', $countryId)->where('is_active', true)->select('id', 'country_id', 'name', 'state_code')->get();

        return response()->json([
            'status' => true,
            'message' => 'States retrieved successfully.',
            'country_id' => $countryId,
            'data' => $states,
        ]);
    }

    public function lgas(int $stateId): JsonResponse
    {
        $lgas = Lga::where('state_id', $stateId)->where('is_active', true)->select('id', 'state_id', 'name')->get();

        return response()->json([
            'status' => true,
            'message' => 'LGAs retrieved successfully.',
            'state_id' => $stateId,
            'data' => $lgas,
        ]);
    }
}
