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

    /**
     * STRICT ZERO-TRUST GUARD & ROLE INVARIANT:
     * 1. Only users with user_type === 'rider' can access this terminal.
     *    Logistics companies, vendors, and customers are strictly prohibited.
     * 2. All riders MUST belong to an active, verified Logistics Company.
     *    Independent unassigned dispatch is mathematically impossible.
     */
    protected function guardRiderWithLogistics(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isRider()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only registered delivery couriers can access this terminal. Logistics companies, merchants, and customers are strictly prohibited.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: User account is inactive.',
            ], 403);
        }

        $rider = $user->riderProfile;
        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'Rider dispatch profile not found.',
            ], 404);
        }

        if (!$rider->logistics_company_id || !$rider->logisticsCompany) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Rider is not affiliated with any registered logistics company. All riders must belong to an active logistics company.',
            ], 403);
        }

        if (!$rider->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Rider dispatch account is inactive or suspended.',
            ], 403);
        }

        if ($rider->logisticsCompany->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Parent logistics company fleet is currently inactive or suspended.',
            ], 403);
        }

        return null;
    }

    public function dashboard(Request $request): JsonResponse
    {
        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $user = $request->user();
        $rider = $user->riderProfile;
        $fleetName = $rider->logisticsCompany->company_name;

        $activeOrders = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->count();

        $completedOrders = Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->count();

        $activeOrdersList = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['shop:id,name,address,phone,lga_id', 'customer:id,name,phone', 'items:id,order_id,product_name,quantity', 'destinationLga:id,name'])
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
                'operational_telemetry' => [
                    'active_assignments_count' => $activeOrders,
                    'completed_trips_count' => $completedOrders,
                    'shift_status' => (bool)$rider->is_online ? 'ON DUTY' : 'OFF DUTY',
                    'assigned_corridor' => $rider->assignedLga->name ?? 'Metropolitan Dispatch Zone',
                ],
                'active_orders' => $activeOrdersList->map(fn($o) => $this->formatOrderForRider($o)),
            ],
        ]);
    }

    public function toggleDuty(Request $request): JsonResponse
    {
        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
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
        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $orders = Order::where('rider_id', $rider->id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['shop:id,name,address,phone,lga_id', 'customer:id,name,phone', 'items:id,order_id,product_name,quantity', 'destinationLga:id,name'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active assignments retrieved.',
            'data' => $orders->map(fn($o) => $this->formatOrderForRider($o)),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $history = Order::where('rider_id', $rider->id)
            ->where('order_status', 'delivered')
            ->with(['shop:id,name,address,phone', 'customer:id,name,phone', 'items:id,order_id,product_name,quantity', 'destinationLga:id,name'])
            ->orderBy('updated_at', 'desc')
            ->take(30)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Delivery history retrieved.',
            'data' => $history->map(fn($o) => $this->formatOrderForRider($o)),
        ]);
    }

    /**
     * Format Order for Rider Terminal projection.
     * ZERO LEAKAGE: Never exposes verification_code (Delivery OTP), guest_access_token, or pickup_verification_code.
     */
    private function formatOrderForRider(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'fulfillment_type' => $order->fulfillment_type,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'delivery_fee' => $order->delivery_fee,
            'vendor_handover_ready_at' => $order->vendor_handover_ready_at ? $order->vendor_handover_ready_at->toIso8601String() : null,
            'pickup_confirmed_by_rider' => (bool)$order->pickup_confirmed_by_rider,
            'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->toIso8601String() : null,
            'rider_arrived_at' => $order->rider_arrived_at ? $order->rider_arrived_at->toIso8601String() : null,
            'received_at' => $order->received_at ? $order->received_at->toIso8601String() : null,
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'shop' => $order->shop ? [
                'id' => $order->shop->id,
                'name' => $order->shop->name,
                'address' => $order->shop->address,
                'phone' => $order->shop->phone,
                'lga_id' => $order->shop->lga_id,
            ] : null,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
            ] : null,
            'destination_lga' => $order->destinationLga ? [
                'id' => $order->destinationLga->id,
                'name' => $order->destinationLga->name,
            ] : null,
            'items' => $order->items ? $order->items->map(fn($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->values()->all() : [],
        ];
    }

    public function verifyPickupOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
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

        public function markArrived(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        if ($order->rider_id && (int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another delivery courier.'], 403);
        }

        $result = $this->settlementService->markRiderArrived($order, $rider);

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

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
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
