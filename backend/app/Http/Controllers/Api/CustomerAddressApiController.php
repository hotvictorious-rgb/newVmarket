<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Lga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = CustomerAddress::where('user_id', $request->user()->id)
            ->with(['country:id,name,iso_code', 'state:id,name,state_code', 'lga:id,name'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Addresses retrieved successfully.',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string',
            'address_line' => 'required|string',
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'required|integer|exists:states,id',
            'lga_id' => 'required|integer|exists:lgas,id',
            'landmark' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        // Validate that LGA belongs to State
        $validLga = Lga::where('id', $validated['lga_id'])
            ->where('state_id', $validated['state_id'])
            ->exists();

        if (!$validLga) {
            return response()->json([
                'status' => false,
                'message' => 'Selected LGA does not belong to the selected State.',
            ], 422);
        }

        if (!empty($validated['is_default'])) {
            CustomerAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'user_id' => $request->user()->id,
            'contact_name' => strip_tags(trim($validated['contact_name'])),
            'phone' => preg_replace('/[^0-9+]/', '', $validated['phone']),
            'address_line' => strip_tags(trim($validated['address_line'])),
            'country_id' => $validated['country_id'],
            'state_id' => $validated['state_id'],
            'lga_id' => $validated['lga_id'],
            'landmark' => isset($validated['landmark']) ? strip_tags(trim($validated['landmark'])) : null,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        $address->load(['country:id,name,iso_code', 'state:id,name,state_code', 'lga:id,name']);

        return response()->json([
            'status' => true,
            'message' => 'Address added successfully.',
            'data' => $address,
        ], 201);
    }
}
