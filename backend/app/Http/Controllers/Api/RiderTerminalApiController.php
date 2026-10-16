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

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only delivery couriers can access this terminal.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider) {
            return response()->json(['status' => false, 'message' => 'Rider profile not found.'], 404);
        }

        $fleetName = $rider->logisticsCompany ? $rider->logisticsCompany->company_name : 'Sovereign Dispatch Network';

        $activeOrders = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->count();

        $completedOrders = Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->count();

        $todayEarnings = (float) Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->whereDate('updated_at', now()->today())
            ->sum('delivery_charge');

        $totalEarnings = (float) Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->sum('delivery_charge');

        $activeOrdersList = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['shop:id,name,address,phone,lga_id', 'customer:id,name,phone'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Rider dashboard retrieved.',
            'data' => [
                'rider' => [
                    'id' => $rider->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '+234 800 000 0000',
                    'fleet_company' => $fleetName,
                    'vehicle_type' => $rider->vehicle_type ?? 'Motorbike (Speed Cargo)',
                    'license_plate' => $rider->license_plate ?? 'VM-RDR-042',
                    'is_active' => (bool)$rider->is_active,
                    'is_online' => (bool)$rider->is_online,
                    'rating' => 4.92,
                ],
                'fintech_metrics' => [
                    'wallet_balance' => (float)($rider->current_balance ?? '0.00'),
                    'today_earnings' => $todayEarnings,
                    'total_earnings' => $totalEarnings,
                    'active_assignments_count' => $activeOrders,
                    'completed_trips_count' => $completedOrders,
                ],
                'active_orders' => $activeOrdersList,
            ],
        ]);
    }

    public function toggleDuty(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only delivery couriers can access this terminal.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider) {
            return response()->json(['status' => false, 'message' => 'Rider profile not found.'], 404);
        }

        $rider->is_online = !$rider->is_online;
        $rider->save();

        return response()->json([
            'status' => true,
            'message' => 'Rider duty status updated.',
            'data' => [
                'is_online' => (bool)$rider->is_online,
            ],
        ]);
    }

    public function activeOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only delivery couriers can access this terminal.'], 403);
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

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isRider()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only delivery couriers can access this terminal.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider) {
            return response()->json(['status' => false, 'message' => 'Rider profile not found.'], 404);
        }

        $history = Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->with(['shop:id,name,address,phone', 'customer:id,name,phone'])
            ->orderBy('updated_at', 'desc')
            ->take(30)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Delivery history retrieved.',
            'data' => $history,
        ]);
    }

    public function verifyPickupOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();
        if (!$user->isRider() || $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Active delivery courier access required.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider || !$rider->is_active) {
            return response()->json(['status' => false, 'message' => 'Rider account is inactive or suspended.'], 403);
        }

        $order = Order::findOrFail($validated['order_id']);

        if ($order->rider_id && (int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another dispatch courier.'], 403);
        }

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
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();
        if (!$user->isRider() || $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Active delivery courier access required.'], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider || !$rider->is_active) {
            return response()->json(['status' => false, 'message' => 'Rider account is inactive or suspended.'], 403);
        }

        $order = Order::findOrFail($validated['order_id']);

        if ($order->rider_id && (int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another delivery courier.'], 403);
        }

        $result = $this->settlementService->verifyCustomerDeliveryOtp($order, $validated['otp'], $rider);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }
}
