<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\CustomerWalletCredit;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Services\BcMathService;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    /**
     * Delivery Attempt Failure Logger (e.g. customer unreachable, gate locked, customer requested reschedule)
     */
    public function recordDeliveryAttemptFailed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|in:customer_unreachable,gate_locked,customer_rescheduled,wrong_address,recipient_unavailable',
            'notes' => 'nullable|string|max:500',
            'proof_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::with(['items', 'customer'])->findOrFail($validated['order_id']);

        if ($order->rider_id && (int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another delivery courier.'], 403);
        }

        if (!in_array($order->order_status, ['out_for_delivery', 'confirmed', 'processing', 'ready_for_pickup'])) {
            return response()->json([
                'status' => false,
                'message' => "Cannot record delivery attempt failure for order in '{$order->order_status}' status.",
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $path = $request->file('proof_photo')->store('delivery_attempts', 'public');
            $photoPath = 'storage/' . $path;
        }

        $order->delivery_attempt_count = ((int)$order->delivery_attempt_count) + 1;
        $order->delivery_failure_reason = $validated['reason'];
        $order->delivery_attempt_notes = $validated['notes'] ?? null;
        $order->last_delivery_attempt_at = Carbon::now();

        // If max attempts reached (>= 2 attempts), initiate return-to-vendor protocol!
        $maxAttemptsReached = $order->delivery_attempt_count >= 2;
        $returnCase = null;

        if ($maxAttemptsReached) {
            // Customer was repeatedly unreachable: parcel must return to vendor shop
            $order->order_status = 'returned';

            // Generate Vendor Return OTP and create ReturnCase
            $vendorReturnOtp = sprintf('%06d', mt_rand(100000, 999999));
            $caseNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $returnCase = ReturnCase::create([
                'case_number' => $caseNumber,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'vendor_id' => $order->vendor_id,
                'collection_point_id' => $order->collection_point_id,
                'source' => 'delivery_failed_unreachable',
                'rejection_category' => 'customer_unreachable',
                'vendor_return_otp' => $vendorReturnOtp,
                'doorstep_photo_path' => $photoPath,
                'returned_by_rider_id' => $rider->id,
                'status' => 'in_transit_back_to_vendor',
                'return_reason' => 'other',
                'customer_description' => "Customer unreachable after {$order->delivery_attempt_count} delivery attempts ({$validated['reason']}). Parcel returning to vendor shop.",
                'total_refundable_amount' => $order->merchandise_subtotal,
            ]);

            // Customer refund credit: full merchandise refunded to customer wallet credit
            // Shipping fee is retained and paid to courier/logistics fleet
            CustomerWalletCredit::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'return_case_id' => $returnCase->id,
                'amount' => $order->merchandise_subtotal,
                'credit_type' => 'doorstep_refund_credit',
                'status' => 'available',
                'notes' => "Refund credit for Order #{$order->order_number} (Customer unreachable after {$order->delivery_attempt_count} attempts).",
            ]);

            // Release logistics courier delivery fee to fleet
            $logisticsFee = (string)$order->delivery_fee;
            $logisticsCompanyId = $order->logistics_company_id ?: $rider->logistics_company_id;
            if (bccomp($logisticsFee, '0.00', 2) > 0 && $logisticsCompanyId) {
                $logisticsNet90 = $order->logistics_fee_retained ?? BcMathService::mul($logisticsFee, '0.90');

                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $order->payment_reference ?? $order->order_number,
                    'event_id' => 'EVT-LOG-ATTEMPT-' . $order->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $logisticsCompanyId,
                    'account_name' => 'logistics_payable',
                    'entry_type' => 'credit',
                    'amount' => $logisticsNet90,
                    'currency' => 'NGN',
                    'description' => "Logistics payout for dispatch attempts on Order #{$order->order_number}",
                ]);
            }
        }

        $order->save();

        AdminAuditLog::create([
            'user_id' => $rider->user_id,
            'action' => 'delivery_attempt_failed_recorded',
            'target_type' => Order::class,
            'target_id' => $order->id,
            'old_values' => ['delivery_attempt_count' => $order->delivery_attempt_count - 1],
            'new_values' => [
                'delivery_attempt_count' => $order->delivery_attempt_count,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'max_attempts_reached' => $maxAttemptsReached,
                'return_case_id' => $returnCase?->id,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'status' => true,
            'message' => $maxAttemptsReached
                ? "Maximum delivery attempts reached ({$order->delivery_attempt_count}). Order converted to return-to-vendor. Please return parcel to merchant shop."
                : "Delivery attempt #{$order->delivery_attempt_count} failure recorded. Dispatch can be re-attempted.",
            'data' => [
                'order_id' => $order->id,
                'attempt_count' => $order->delivery_attempt_count,
                'reason' => $order->delivery_failure_reason,
                'max_attempts_reached' => $maxAttemptsReached,
                'order_status' => $order->order_status,
                'return_case' => $returnCase ? [
                    'id' => $returnCase->id,
                    'case_number' => $returnCase->case_number,
                    'vendor_return_otp' => $returnCase->vendor_return_otp,
                    'status' => $returnCase->status,
                ] : null,
            ],
        ]);
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

    /**
     * Handshake 2: Itemized Doorstep Handover Manifest (Partial Acceptance & 3-Option Rejection)
     */
    public function doorstepHandover(Request $request, \App\Services\DoorstepHandshakeService $handshakeService): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6'],
            'manifest_items' => ['required', 'array', 'min:1'],
            'manifest_items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'manifest_items.*.accepted_quantity' => 'required|integer|min:0',
            'manifest_items.*.rejected_quantity' => 'required|integer|min:0',
            'manifest_items.*.rejection_category' => 'nullable|string|in:vendor_issue,transit_damage,customer_change_of_mind',
            'manifest_items.*.rejection_notes' => 'nullable|string|max:500',
            'proof_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $path = $request->file('proof_photo')->store('doorstep_rejections', 'public');
            $photoPath = 'storage/' . $path;
        }

        $result = $handshakeService->processDoorstepHandover(
            $order,
            $rider,
            $validated['manifest_items'],
            $validated['otp'],
            $photoPath
        );

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] ? [
                'accepted_amount' => $result['accepted_amount'],
                'rejected_amount' => $result['rejected_amount'],
                'wallet_credited' => $result['wallet_credited'],
                'return_case' => $result['return_case'],
            ] : null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Handshake 3: Rider returns rejected parcel to Vendor Shop with Vendor Return OTP
     */
    public function verifyVendorReturnOtp(Request $request, \App\Services\DoorstepHandshakeService $handshakeService): JsonResponse
    {
        $validated = $request->validate([
            'return_case_id' => 'required|integer|exists:return_cases,id',
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $case = \App\Models\ReturnCase::findOrFail($validated['return_case_id']);

        $result = $handshakeService->verifyVendorReturnOtp($case, $rider, $validated['otp']);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] ? $result['case'] : null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Active return cases assigned to this rider awaiting dropoff at vendor shop.
     */
    public function activeReturns(Request $request): JsonResponse
    {
        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $returnCases = \App\Models\ReturnCase::where(function ($q) use ($rider) {
                $q->where('returned_by_rider_id', $rider->id)
                  ->orWhereHas('order', fn($oq) => $oq->where('rider_id', $rider->id));
            })
            ->where('status', 'in_transit_back_to_vendor')
            ->with(['order.shop', 'order.customer', 'order.items'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active return cases retrieved.',
            'data' => $returnCases->map(function ($case) {
                return [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'order_id' => $case->order_id,
                    'order_number' => $case->order ? $case->order->order_number : null,
                    'shop_name' => $case->order && $case->order->shop ? $case->order->shop->name : 'Merchant Store',
                    'shop_address' => $case->order && $case->order->shop ? $case->order->shop->address : 'Vendor Location',
                    'shop_phone' => $case->order && $case->order->shop ? $case->order->shop->phone : null,
                    'customer_name' => $case->order && $case->order->customer ? $case->order->customer->name : 'Customer',
                    'customer_phone' => $case->order && $case->order->customer ? $case->order->customer->phone : null,
                    'rejection_category' => $case->rejection_category,
                    'status' => $case->status,
                    'doorstep_photo_path' => $case->doorstep_photo_path,
                    'total_refundable_amount' => $case->total_refundable_amount,
                    'items_summary' => $case->order && $case->order->items ? $case->order->items->where('rejected_quantity', '>', 0)->pluck('product_name')->join(', ') : 'Rejected item(s)',
                    'created_at' => $case->created_at ? $case->created_at->toIso8601String() : null,
                ];
            }),
        ]);
    }
}