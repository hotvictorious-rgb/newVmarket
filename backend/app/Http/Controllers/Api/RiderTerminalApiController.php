<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\ReturnCase;
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
     * Zero-trust guard for the centrally managed retailer delivery terminal.
     * Vendor-linked riders are legacy-only and cannot perform new VM deliveries.
     */
    protected function guardRiderWithLogistics(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isRider()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only registered delivery couriers can access this terminal.',
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

        if (!$rider->isEligibleForCentralDelivery()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: New Victorious MARKET delivery work requires an active rider linked exclusively to one active logistics company.',
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
        $vendorName = $rider->vendor?->business_name ?? 'Vendor Delivery';

        $activeOrders = Order::where('rider_id', $rider->id)
            ->where('logistics_company_id', $rider->logistics_company_id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->count();

        $completedOrders = Order::where('rider_id', $rider->id)
            ->where('logistics_company_id', $rider->logistics_company_id)
            ->where('order_status', 'delivered')
            ->count();

        $activeOrdersList = Order::where('rider_id', $rider->id)
            ->where('logistics_company_id', $rider->logistics_company_id)
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->with(['shop:id,name,address,phone,lga_id', 'customer:id,name', 'customerAddress.lga', 'customerAddress.state', 'items:id,order_id,product_name,quantity', 'destinationLga:id,name'])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Rider dashboard retrieved.',
            'data' => [
                'rider' => [
                    'id' => $rider->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'vendor_name' => $vendorName,
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
            ->where('logistics_company_id', $rider->logistics_company_id)
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
            ->where('logistics_company_id', $rider->logistics_company_id)
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
            'vendor_handover_ready_at' => $order->vendor_handover_ready_at ? $order->vendor_handover_ready_at->toIso8601String() : null,
            'pickup_confirmed_by_rider' => (bool)$order->pickup_confirmed_by_rider,
            'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->toIso8601String() : null,
            'rider_arrived_at' => $order->rider_arrived_at ? $order->rider_arrived_at->toIso8601String() : null,
            'received_at' => $order->received_at ? $order->received_at->toIso8601String() : null,
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'pickup_proof_image' => $order->pickup_proof_image ? url($order->pickup_proof_image) : null,
            'proof_of_delivery_image' => $order->proof_of_delivery_image ? url($order->proof_of_delivery_image) : null,
            'delivery_attempt_photo_path' => $order->delivery_attempt_photo_path ? url($order->delivery_attempt_photo_path) : null,
            'doorstep_rejection_photos' => $order->doorstep_rejection_photos,
            'shop' => $order->shop ? [
                'id' => $order->shop->id,
                'name' => $order->shop->name,
                'address' => $order->shop->address,
                'phone' => $order->shop->phone,
                'lga_id' => $order->shop->lga_id,
            ] : null,
            'customer' => $order->customer ? [
                'name' => $order->customerAddress?->contact_name ?: $order->customer->name,
                'delivery_address' => $this->formatCustomerDeliveryAddress($order),
            ] : null,
            'destination_lga' => $order->destinationLga ? [
                'id' => $order->destinationLga->id,
                'name' => $order->destinationLga->name,
            ] : null,
            'items' => $order->items ? $order->items->map(fn($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'accepted_quantity' => $item->accepted_quantity,
                'rejected_quantity' => $item->rejected_quantity,
                'rejection_category' => $item->rejection_category,
                'defect_photo_path' => $item->defect_photo_path ? url($item->defect_photo_path) : null,
            ])->values()->all() : [],
        ];
    }

    /**
     * Build the selected delivery address from the address snapshot linked to this order.
     * Never include address phone/contact phone in a rider projection.
     */
    private function formatCustomerDeliveryAddress(Order $order): ?string
    {
        $address = $order->customerAddress;
        if (!$address) {
            return null;
        }

        $parts = [
            $address->address_line,
            $address->landmark,
            $address->lga?->name,
            $address->state?->name,
        ];

        $parts = array_values(array_filter(array_map(
            static fn ($part) => is_string($part) ? trim($part) : '',
            $parts
        ), static fn ($part) => $part !== ''));

        return $parts ? implode(', ', $parts) : null;
    }

    public function verifyPickupOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'proof_photo.required' => 'A live photo of the package at the merchant counter is strictly mandatory to verify pickup.',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        if ($order->pickup_confirmed_by_rider) {
            return response()->json(['status' => false, 'message' => 'Pickup has already been confirmed and sealed. Photo proof is immutable.'], 422);
        }

        if ((int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another dispatch courier.'], 403);
        }

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('proof_photo'), 'pickup_proofs');
            $photoPath = $opt['path'];
        }

        $result = $this->settlementService->verifyRiderPickupOtp($order, $validated['otp'], $rider, $photoPath);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'remaining_supplier_packages' => $result['remaining_supplier_packages'] ?? 0,
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

        if ((int)$order->rider_id !== (int)$rider->id) {
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
            'proof_photo' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'proof_photo.required' => 'A live photo proof of the premises / locked gate is strictly mandatory when recording a failed delivery attempt.',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $photo = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('proof_photo'), 'delivery_attempts');
        $photoPath = $photo['path'];

        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $rider, $request, $photoPath): JsonResponse {
            $order = Order::with(['items', 'customer'])
                ->whereKey($validated['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $order->rider_id !== (int) $rider->id
                || !$rider->isEligibleForCentralOrder($order)) {
                return response()->json(['status' => false, 'message' => 'Unauthorized: This order is not assigned to this active rider through the same active logistics company.'], 403);
            }

            if ($order->payment_status !== 'paid') {
                return response()->json(['status' => false, 'message' => 'Unpaid orders cannot record a delivery attempt or enter return-to-vendor.'], 422);
            }

            if ($order->fulfillment_type !== 'doorstep_delivery' || $order->order_status !== 'out_for_delivery') {
                return response()->json([
                    'status' => false,
                    'message' => 'A failed doorstep delivery attempt can only be recorded for a paid order currently out for delivery.',
                ], 422);
            }

            $oldAttemptCount = (int) $order->delivery_attempt_count;
            $order->delivery_attempt_count = $oldAttemptCount + 1;
            $order->delivery_failure_reason = $validated['reason'];
            $order->delivery_attempt_notes = $validated['notes'] ?? null;
            $order->delivery_attempt_photo_path = $photoPath;
            $order->last_delivery_attempt_at = Carbon::now();

            $maxAttemptsReached = $order->delivery_attempt_count >= 2;
            $returnCase = null;
            $requiresSupplierAssignment = false;

            if ($maxAttemptsReached) {
                $vendorReturnOtp = sprintf('%06d', random_int(100000, 999999));
                $caseNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                $supplierIds = \App\Models\OrderItem::where('order_id', $order->id)
                    ->pluck('fulfillment_supplier_vendor_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
                $requiresSupplierAssignment = $order->retailer_model_order && $supplierIds->count() !== 1;

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
                    'returned_by_rider_id' => $rider->user_id,
                    'status' => $requiresSupplierAssignment ? 'pending_supplier_assignment' : 'in_transit_back_to_vendor',
                    'return_reason' => 'other',
                    'customer_description' => "Customer unreachable after {$order->delivery_attempt_count} delivery attempts ({$validated['reason']}). Parcel returning to vendor shop.",
                    'total_refundable_amount' => $order->merchandise_subtotal,
                ]);

                $order->order_status = 'returned';
            }

            $order->save();

            if ($returnCase) {
                NotificationDispatchService::dispatchDoorstepReturnInitiated($returnCase);
                NotificationDispatchService::dispatchVendorReturnOtpIssued($returnCase);
            }

            AdminAuditLog::create([
                'user_id' => $rider->user_id,
                'action' => 'delivery_attempt_failed_recorded',
                'target_type' => Order::class,
                'target_id' => $order->id,
                'old_values' => ['delivery_attempt_count' => $oldAttemptCount, 'order_status' => $maxAttemptsReached ? 'out_for_delivery' : $order->order_status],
                'new_values' => [
                    'delivery_attempt_count' => $order->delivery_attempt_count,
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'max_attempts_reached' => $maxAttemptsReached,
                    'return_case_id' => $returnCase?->id,
                    'order_status' => $order->order_status,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
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
                        'return_destination_requires_admin_assignment' => $returnCase->status === 'pending_supplier_assignment',
                        'status' => $returnCase->status,
                    ] : null,
                ],
            ]);
        });
    }

    public function verifyDeliveryOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'proof_photo.required' => 'A live photo of the package at the customer doorstep is strictly mandatory to complete delivery.',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        if ($order->order_status === 'delivered') {
            return response()->json(['status' => false, 'message' => 'Delivery has already been confirmed and sealed. Photo proof is immutable.'], 422);
        }

        if ((int)$order->rider_id !== (int)$rider->id) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This order is assigned to another delivery courier.'], 403);
        }

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('proof_photo'), 'doorstep_deliveries');
            $photoPath = $opt['path'];
        }

        $result = $this->settlementService->verifyCustomerDeliveryOtp($order, $validated['otp'], $rider, $photoPath);

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
        if ($request->has('manifest_items') && is_string($request->input('manifest_items'))) {
            $decoded = json_decode($request->input('manifest_items'), true);
            if (is_array($decoded)) {
                $request->merge(['manifest_items' => $decoded]);
            }
        }

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6'],
            'manifest_items' => ['required', 'array', 'min:1'],
            'manifest_items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'manifest_items.*.accepted_quantity' => 'required|integer|min:0',
            'manifest_items.*.rejected_quantity' => 'required|integer|min:0',
            'manifest_items.*.rejection_category' => 'nullable|string|in:vendor_issue,transit_damage,customer_change_of_mind',
            'manifest_items.*.rejection_notes' => 'nullable|string|max:500',
            'manifest_items.*.defect_photo_path' => 'nullable|string',
            'proof_photo' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'proof_photo.required' => 'A live photo of the parcel condition at the customer doorstep is strictly mandatory.',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $order = Order::findOrFail($validated['order_id']);

        if (in_array($order->order_status, ['delivered', 'returned'])) {
            return response()->json(['status' => false, 'message' => 'Doorstep handover has already been confirmed and sealed. Photo proof is immutable.'], 422);
        }

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('proof_photo'), 'doorstep_rejections');
            $photoPath = $opt['path'];
        }

        $manifestItems = $validated['manifest_items'];
        foreach ($manifestItems as &$itemInput) {
            $itemId = $itemInput['order_item_id'] ?? 0;
            if ($request->hasFile("item_photo_{$itemId}")) {
                $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file("item_photo_{$itemId}"), 'item_defects');
                $itemInput['defect_photo_path'] = $opt['path'];
            } elseif (($itemInput['rejected_quantity'] ?? 0) > 0 && empty($itemInput['defect_photo_path'])) {
                return response()->json([
                    'status' => false,
                    'message' => "A live defect photo is strictly mandatory for rejected item #{$itemId}.",
                    'errors' => [
                        "item_photo_{$itemId}" => ["A live defect photo is strictly mandatory for rejected item #{$itemId}."],
                    ],
                ], 422);
            }
        }

        $result = $handshakeService->processDoorstepHandover(
            $order,
            $rider,
            $manifestItems,
            $validated['otp'],
            $photoPath
        );

        $returnCase = $result['return_case'] ?? null;
        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] ? [
                'return_case' => $returnCase instanceof ReturnCase ? [
                    'id' => $returnCase->id,
                    'case_number' => $returnCase->case_number,
                    'status' => $returnCase->status,
                    'return_destination_requires_admin_assignment' => $returnCase->status === 'pending_supplier_assignment',
                ] : null,
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
            'proof_photo' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'proof_photo.required' => 'A live photo of the returned parcel at the merchant shop counter is strictly mandatory.',
        ]);

        if ($error = $this->guardRiderWithLogistics($request)) {
            return $error;
        }

        $rider = $request->user()->riderProfile;
        $case = \App\Models\ReturnCase::findOrFail($validated['return_case_id']);

        if ((int) $case->returned_by_rider_id !== (int) $rider->user_id) {
            return response()->json(['status' => false, 'message' => 'This return case is not assigned to the authenticated rider.'], 403);
        }
        if ($case->status === 'pending_supplier_assignment') {
            return response()->json(['status' => false, 'message' => 'This return contains items from multiple or unresolved suppliers. Admin assignment is required before vendor handover verification.'], 422);
        }
        if ($case->status === 'returned_to_vendor') {
            return response()->json(['status' => false, 'message' => 'Vendor return has already been confirmed and sealed. Photo proof is immutable.'], 422);
        }

        $photoPath = null;
        if ($request->hasFile('proof_photo')) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('proof_photo'), 'vendor_returns');
            $photoPath = $opt['path'];
        }

        $result = $handshakeService->verifyVendorReturnOtp($case, $rider, $validated['otp'], $photoPath);

        $updatedCase = $result['case'] ?? null;
        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] && $updatedCase instanceof ReturnCase ? [
                'id' => $updatedCase->id,
                'case_number' => $updatedCase->case_number,
                'status' => $updatedCase->status,
                'returned_to_vendor_at' => $updatedCase->returned_to_vendor_at?->toIso8601String(),
            ] : null,
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
                $q->where('returned_by_rider_id', $rider->user_id)
                  ->orWhereHas('order', fn ($oq) => $oq->where('rider_id', $rider->id));
            })
            ->whereIn('status', ['in_transit_back_to_vendor', 'pending_supplier_assignment'])
            ->with([
                'order.shop',
                'order.customer',
                'order.customerAddress.lga',
                'order.customerAddress.state',
                'order.items',
                'items.orderItem',
                'items.product',
            ])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Active return cases retrieved.',
            'data' => $returnCases->map(function ($case) {
                $order = $case->order;
                $shopName = $order && $order->shop ? $order->shop->name : 'Merchant Store';
                $shopAddress = $order && $order->shop ? $order->shop->address : 'Vendor Location';
                $shopPhone = $order && $order->shop ? $order->shop->phone : null;
                $requiresAdminAssignment = $case->status === 'pending_supplier_assignment';

                if ($order && $order->retailer_model_order) {
                    $supplierIds = $case->items
                        ->map(fn ($item) => (int) ($item->orderItem?->fulfillment_supplier_vendor_id ?? 0))
                        ->filter(fn ($id) => $id > 0)
                        ->unique()
                        ->values();
                    if ($supplierIds->isEmpty()) {
                        $supplierIds = $order->items
                            ->pluck('fulfillment_supplier_vendor_id')
                            ->map(fn ($id) => (int) $id)
                            ->filter(fn ($id) => $id > 0)
                            ->unique()
                            ->values();
                    }

                    if ($supplierIds->count() !== 1) {
                        $requiresAdminAssignment = true;
                    } else {
                        $supplier = \App\Models\VendorProfile::with('shops')->find($supplierIds->first());
                        $supplierShop = $supplier?->shops()->where('is_active', true)->first();
                        if (!$supplier || $supplier->marketplace_status !== 'active' || !$supplierShop) {
                            $requiresAdminAssignment = true;
                        } else {
                            $shopName = $supplierShop->name;
                            $shopAddress = $supplierShop->address;
                            $shopPhone = $supplier->business_phone ?: $supplier->user?->phone;
                        }
                    }

                    if ($requiresAdminAssignment) {
                        $shopName = 'Victorious MARKET — supplier assignment pending';
                        $shopAddress = null;
                        $shopPhone = null;
                    }
                }

                $itemNames = $case->items->isNotEmpty()
                    ? $case->items->map(fn ($item) => $item->orderItem?->product_name ?? $item->product?->name)->filter()->join(', ')
                    : ($order && $order->items ? $order->items->pluck('product_name')->join(', ') : 'Returned item(s)');

                return [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'order_id' => $case->order_id,
                    'order_number' => $order?->order_number,
                    'shop_name' => $shopName,
                    'shop_address' => $shopAddress,
                    'shop_phone' => $shopPhone,
                    'return_destination_requires_admin_assignment' => $requiresAdminAssignment,
                    'customer_name' => $order && $order->customer ? ($order->customerAddress?->contact_name ?: $order->customer->name) : 'Customer',
                    'delivery_address' => $order ? $this->formatCustomerDeliveryAddress($order) : null,
                    'rejection_category' => $case->rejection_category,
                    'status' => $case->status,
                    'doorstep_photo_path' => $case->doorstep_photo_path,
                    'items_summary' => $itemNames ?: 'Returned item(s)',
                    'created_at' => $case->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}