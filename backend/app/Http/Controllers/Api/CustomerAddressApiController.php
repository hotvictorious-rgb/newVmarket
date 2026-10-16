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
        $contactName = $request->input('contact_name') ?? $request->input('contact_person_name');
        $addressLine = $request->input('address_line') ?? $request->input('address');

        $request->merge([
            'contact_name' => $contactName,
            'address_line' => $addressLine,
        ]);

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

    public function update(Request $request, $id = null): JsonResponse
    {
        $addressId = $id ?? $request->input('id') ?? $request->input('address_id');
        if (!$addressId) {
            return response()->json(['status' => false, 'message' => 'Address ID is required.'], 422);
        }

        $address = CustomerAddress::where('user_id', $request->user()->id)->find($addressId);
        if (!$address) {
            return response()->json(['status' => false, 'message' => 'Address not found or unauthorized.'], 404);
        }

        $contactName = $request->input('contact_name') ?? $request->input('contact_person_name');
        $addressLine = $request->input('address_line') ?? $request->input('address');

        if ($contactName !== null) {
            $request->merge(['contact_name' => $contactName]);
        }
        if ($addressLine !== null) {
            $request->merge(['address_line' => $addressLine]);
        }

        $validated = $request->validate([
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string',
            'address_line' => 'nullable|string',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'lga_id' => 'nullable|integer|exists:lgas,id',
            'landmark' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $stateId = $validated['state_id'] ?? $address->state_id;
        $lgaId = $validated['lga_id'] ?? $address->lga_id;

        if ($stateId && $lgaId) {
            $validLga = Lga::where('id', $lgaId)->where('state_id', $stateId)->exists();
            if (!$validLga) {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected LGA does not belong to the selected State.',
                ], 422);
            }
        }

        if (!empty($validated['is_default'])) {
            CustomerAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
            $address->is_default = true;
        }

        if (isset($validated['contact_name'])) {
            $address->contact_name = strip_tags(trim($validated['contact_name']));
        }
        if (isset($validated['phone'])) {
            $address->phone = preg_replace('/[^0-9+]/', '', $validated['phone']);
        }
        if (isset($validated['address_line'])) {
            $address->address_line = strip_tags(trim($validated['address_line']));
        }
        if (isset($validated['country_id'])) {
            $address->country_id = $validated['country_id'];
        }
        if (isset($validated['state_id'])) {
            $address->state_id = $validated['state_id'];
        }
        if (isset($validated['lga_id'])) {
            $address->lga_id = $validated['lga_id'];
        }
        if (array_key_exists('landmark', $validated)) {
            $address->landmark = $validated['landmark'] ? strip_tags(trim($validated['landmark'])) : null;
        }

        $address->save();
        $address->load(['country:id,name,iso_code', 'state:id,name,state_code', 'lga:id,name']);

        return response()->json([
            'status' => true,
            'message' => 'Address updated successfully.',
            'data' => $address,
        ]);
    }

    public function destroy(Request $request, $id = null): JsonResponse
    {
        $addressId = $id ?? $request->input('id') ?? $request->input('address_id');
        if (!$addressId) {
            return response()->json(['status' => false, 'message' => 'Address ID is required.'], 422);
        }

        $address = CustomerAddress::where('user_id', $request->user()->id)->find($addressId);
        if (!$address) {
            return response()->json(['status' => false, 'message' => 'Address not found or unauthorized.'], 404);
        }

        $address->delete();

        return response()->json([
            'status' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }
}
