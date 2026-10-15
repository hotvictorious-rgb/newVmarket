<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderTerminalApiController extends Controller
{
    protected OrderSettlementService $settlementService;

    public function __construct(OrderSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    public function activeOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only delivery riders can access this terminal.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider) {
            return response()->json(['status' => false, 'message' => 'Rider profile not found.'], 404);
        }

        $orders = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['shop:id,name,address,phone,lga_id', 'customer:id,name,phone'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active assignments retrieved.',
            'data' => $orders,
        ]);
    }

    public function verifyPickupOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
        }

        $rider = $user->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        $result = $this->settlementService->verifyRiderPickupOtp($order, $validated['otp'], $rider);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    public function verifyDeliveryOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
        }

        $rider = $user->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        $result = $this->settlementService->verifyCustomerDeliveryOtp($order, $validated['otp'], $rider);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }
}
