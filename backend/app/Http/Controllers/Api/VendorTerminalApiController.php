<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Services\OrderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorTerminalApiController extends Controller
{
    protected OrderSettlementService $settlementService;

    public function __construct(OrderSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function verifyInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_code' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user->isVendor() && !$user->isVendorEmployee()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only vendor merchants or staff can sign off inspections.'], 403);
        }

        $reservation = PickupReservation::where('reservation_code', strtoupper(trim($validated['reservation_code'])))->first();
        if (!$reservation) {
            return response()->json(['status' => false, 'message' => 'Reservation code not found.'], 404);
        }

        $result = $this->settlementService->approvePickupInspection($reservation, $user);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    public function verifyHandoverOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$user->isVendor() && !$user->isVendorEmployee()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
        }

        $order = Order::findOrFail($validated['order_id']);

        $result = $this->settlementService->verifyPickupHandoverOtp($order, $validated['otp'], $user);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }
}
