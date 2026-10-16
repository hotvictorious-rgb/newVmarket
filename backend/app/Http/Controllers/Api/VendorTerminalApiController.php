<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorTerminalApiController extends Controller
{
    protected OrderSettlementService $settlementService;

    public function __construct(OrderSettlementService $settlementService)
    {
        $this->settlementService = $settlementService;
    }

    /**
     * Resolve authorized Vendor & Shop context with strict multi-tenant isolation.
     */
    private function resolveVendorContext(Request $request): array
    {
        $user = $request->user();

        if (!$user || $user->status !== 'active') {
            abort(403, 'Unauthorized: User account is inactive.');
        }

        if ($user->isVendor()) {
            $vendor = VendorProfile::where('user_id', $user->id)->firstOrFail();
            if ($vendor->marketplace_status === 'suspended') {
                abort(403, 'Merchant marketplace account is suspended.');
            }
            $primaryShop = $vendor->primaryShop ?? Shop::where('vendor_id', $vendor->id)->first();
            return [
                'user' => $user,
                'vendor' => $vendor,
                'shop' => $primaryShop,
                'is_employee' => false,
                'employee' => null,
            ];
        }

        if ($user->isVendorEmployee()) {
            if ($request->header('X-Terminal-Client') === 'vendor_mobile_terminal' || $request->header('X-Required-Role') === 'vendor') {
                abort(403, 'Unauthorized: Access denied.');
            }
            $employee = VendorEmployeeProfile::where('user_id', $user->id)
                ->with(['vendor', 'shop'])
                ->firstOrFail();

            if (!$employee->is_active) {
                abort(403, 'Unauthorized: Employee profile is deactivated.');
            }

            if ($employee->vendor && $employee->vendor->marketplace_status === 'suspended') {
                abort(403, 'Merchant organization is suspended.');
            }

            return [
                'user' => $user,
                'vendor' => $employee->vendor,
                'shop' => $employee->shop,
                'is_employee' => true,
                'employee' => $employee,
            ];
        }

        abort(403, 'Unauthorized: Access denied.');
    }

    /**
     * GET /api/v1/vendor/dashboard
     * Fintech Sovereign Metrics, Active Orders, and 48-Hour Bank Cooldown HUD
     */
    public function dashboard(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        // Strict 90/5/5 Financial Ledger Sums
        $grossSales = Order::where('vendor_id', $vendor->id)->sum('grand_total') ?? '0.00';
        $netEarnings = Order::where('vendor_id', $vendor->id)->sum('vendor_net_90') ?? '0.00';
        $settledAmount = Order::where('vendor_id', $vendor->id)
            ->where('vendor_settlement_status', 'settled')
            ->sum('vendor_net_90') ?? '0.00';
        $pendingEscrow = Order::where('vendor_id', $vendor->id)
            ->whereIn('vendor_settlement_status', ['eligible', 'held'])
            ->sum('vendor_net_90') ?? '0.00';

        // Order Counters
        $orderQuery = Order::where('vendor_id', $vendor->id);
        if ($ctx['is_employee'] && $shop) {
            $orderQuery->where('shop_id', $shop->id);
        }

        $allOrdersCount = (clone $orderQuery)->count();
        $pendingCount = (clone $orderQuery)->whereIn('order_status', ['pending', 'confirmed'])->count();
        $processingCount = (clone $orderQuery)->where('order_status', 'processing')->count();
        $readyForHandoverCount = (clone $orderQuery)->where('order_status', 'ready_for_pickup')->count();
        $deliveredCount = (clone $orderQuery)->where('order_status', 'delivered')->count();

        // ₦0.00 Inspection Pass Counters
        $resQuery = PickupReservation::where('vendor_id', $vendor->id);
        if ($ctx['is_employee'] && $shop) {
            $resQuery->where('shop_id', $shop->id);
        }
        $activeReservations = (clone $resQuery)->where('status', 'pending')->count();
        $approvedReservations = (clone $resQuery)->where('status', 'inspected')->count();

        // Products and Low Stock
        $productsQuery = Product::where('vendor_id', $vendor->id);
        $totalProducts = (clone $productsQuery)->count();
        $lowStockCount = (clone $productsQuery)->where('inventory_quantity', '<=', 5)->count();

        // 48-Hour Bank Cooldown State
        $isCooldown = $vendor->isWithinBankCooldown();
        $cooldownExpiresAt = $vendor->bank_updated_at ? $vendor->bank_updated_at->copy()->addHours(48) : null;
        $hoursRemaining = $isCooldown && $cooldownExpiresAt ? max(1, Carbon::now()->diffInHours($cooldownExpiresAt, false)) : 0;

        $maskedAccount = $vendor->account_number
            ? '******' . substr($vendor->account_number, -4)
            : null;

        return response()->json([
            'status' => true,
            'data' => [
                'merchant' => [
                    'id' => $vendor->id,
                    'business_name' => $vendor->business_name,
                    'marketplace_status' => $vendor->marketplace_status,
                    'commission_rate' => $vendor->commission_rate ?? '10.00',
                    'is_employee' => $ctx['is_employee'],
                    'employee_role' => $ctx['employee']?->role,
                ],
                'shop' => $shop ? [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'phone' => $shop->phone,
                    'pickup_enabled' => (bool)$shop->pickup_enabled,
                    'lga_name' => $shop->lga?->name ?? 'Akwa Ibom',
                ] : null,
                'financial_metrics' => [
                    'gross_sales' => number_format((float)$grossSales, 2, '.', ''),
                    'net_90_earnings' => number_format((float)$netEarnings, 2, '.', ''),
                    'settled_amount' => number_format((float)$settledAmount, 2, '.', ''),
                    'pending_escrow' => number_format((float)$pendingEscrow, 2, '.', ''),
                    'currency' => 'NGN',
                    'currency_symbol' => '₦',
                ],
                'orders_breakdown' => [
                    'all' => $allOrdersCount,
                    'pending' => $pendingCount,
                    'processing' => $processingCount,
                    'ready_for_handover' => $readyForHandoverCount,
                    'delivered' => $deliveredCount,
                ],
                'reservations_breakdown' => [
                    'active_pending' => $activeReservations,
                    'approved_inspected' => $approvedReservations,
                ],
                'inventory_summary' => [
                    'total_products' => $totalProducts,
                    'low_stock' => $lowStockCount,
                ],
                'security_cooldown' => [
                    'is_active' => $isCooldown,
                    'bank_name' => $vendor->bank_name,
                    'account_name' => $vendor->account_name,
                    'account_number_masked' => $maskedAccount,
                    'bank_updated_at' => $vendor->bank_updated_at?->toIso8601String(),
                    'cooldown_expires_at' => $cooldownExpiresAt?->toIso8601String(),
                    'hours_remaining' => $hoursRemaining,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/vendor/orders
     * Scoped Orders with Status Filter & Search
     */
    public function orders(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = Order::where('vendor_id', $vendor->id)
            ->with([
                'customer:id,name,phone',
                'shop:id,name,address',
                'rider:id,user_id,plate_number,vehicle_type',
                'rider.user:id,name,phone',
                'items.product:id,name,unit_price,thumbnail',
            ])
            ->latest();

        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }

        if ($request->filled('status')) {
            $status = trim($request->query('status'));
            if ($status === 'ready_for_handover') {
                $query->where('order_status', 'ready_for_pickup');
            } else {
                $query->where('order_status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min(50, max(5, (int)$request->query('per_page', 20)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/vendor/orders/{id}
     */
    public function showOrder(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = Order::where('vendor_id', $vendor->id)
            ->with([
                'customer:id,name,phone,email',
                'shop:id,name,address,phone',
                'rider:id,user_id,plate_number,vehicle_type',
                'rider.user:id,name,phone',
                'items.product',
            ]);

        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }

        $order = $query->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $order,
        ]);
    }

    /**
     * POST /api/v1/vendor/orders/{id}/ready-for-handover
     * Advance order status to ready_for_pickup
     */
    public function markHandoverReady(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = Order::where('vendor_id', $vendor->id);
        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }

        $order = $query->findOrFail($id);

        if ($order->order_status === 'delivered' || $order->order_status === 'cancelled') {
            return response()->json([
                'status' => false,
                'message' => "Order cannot be marked ready because it is already {$order->order_status}.",
            ], 422);
        }

        $order->order_status = 'ready_for_pickup';
        $order->vendor_handover_ready_at = Carbon::now();
        $order->save();

        return response()->json([
            'status' => true,
            'message' => "Order #{$order->order_number} marked ready for courier dispatch handover.",
            'data' => $order,
        ]);
    }

    /**
     * GET /api/v1/vendor/pickup-reservations
     * ₦0.00 In-Shop Inspection Reservations Pass List
     */
    public function pickupReservations(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = PickupReservation::where('vendor_id', $vendor->id)
            ->with([
                'product:id,name,unit_price,thumbnail',
                'customer:id,name,phone',
                'shop:id,name,address',
            ])
            ->latest();

        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }

        if ($request->filled('status')) {
            $query->where('status', trim($request->query('status')));
        }

        $reservations = $query->take(50)->get();

        return response()->json([
            'status' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * POST /api/v1/vendor/pickup-reservations/verify-inspection
     * Approve in-shop inspection pass
     */
    public function verifyInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]{6,50}$/i'],
        ]);

        $user = $request->user();
        if ((!$user->isVendor() && !$user->isVendorEmployee()) || $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only active vendor merchants or staff can sign off inspections.'], 403);
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

    /**
     * POST /api/v1/vendor/orders/verify-handover-otp
     * Confirm courier handover using 6-digit OTP
     */
    public function verifyHandoverOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();
        if ((!$user->isVendor() && !$user->isVendorEmployee()) || $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only active vendor merchants or staff can verify handover.'], 403);
        }

        $order = Order::findOrFail($validated['order_id']);

        $result = $this->settlementService->verifyPickupHandoverOtp($order, $validated['otp'], $user);

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/v1/vendor/products
     * Inventory list with 7-Day Freshness Lifecycle Status
     */
    public function products(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = Product::where('vendor_id', $vendor->id)
            ->with(['category:id,name', 'shop:id,name'])
            ->latest();

        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int)$request->query('category_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->paginate(30);

        // Map with 7-day freshness evaluation
        $now = Carbon::now();
        $items = collect($products->items())->map(function ($product) use ($now) {
            $confirmedAt = $product->listing_confirmed_at ? Carbon::parse($product->listing_confirmed_at) : null;
            $expiresAt = $confirmedAt ? $confirmedAt->copy()->addDays(7) : null;
            $isFresh = $product->isFresh();
            $daysRemaining = $expiresAt && $isFresh ? max(0, $now->diffInDays($expiresAt, false)) : 0;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'unit_price' => $product->unit_price,
                'inventory_quantity' => $product->inventory_quantity,
                'is_in_stock' => (bool)$product->is_in_stock,
                'category_name' => $product->category?->name ?? 'General',
                'shop_name' => $product->shop?->name ?? 'Primary Store',
                'image_path' => $product->thumbnail,
                'is_fresh' => $isFresh,
                'listing_confirmed_at' => $confirmedAt?->toIso8601String(),
                'freshness_expires_at' => $expiresAt?->toIso8601String(),
                'days_remaining' => $daysRemaining,
                'is_approved' => (bool)$product->is_approved,
                'is_flagship' => (bool)$product->is_vmarket_flagship,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/products
     * Create Product strictly scoped to Merchant
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        if (!$shop) {
            return response()->json(['status' => false, 'message' => 'No active shop branch found to assign product.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_price' => 'required|numeric|min:1',
            'inventory_quantity' => 'required|integer|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string|max:2000',
            'image_url' => 'nullable|string|max:500',
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $validated['category_id'],
            'name' => strip_tags(trim($validated['name'])),
            'slug' => Str::slug($validated['name']) . '-' . rand(100, 999),
            'unit_price' => $validated['unit_price'],
            'inventory_quantity' => $validated['inventory_quantity'],
            'is_in_stock' => $validated['inventory_quantity'] > 0,
            'description' => isset($validated['description']) ? strip_tags(trim($validated['description'])) : null,
            'thumbnail' => $validated['image_url'] ?? 'storage/products/default.png',
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => "Product '{$product->name}' successfully published.",
            'data' => $product,
        ], 201);
    }

    /**
     * POST /api/v1/vendor/products/{id}/freshness
     * Refresh 7-Day Product Freshness Lifecycle
     */
    public function refreshFreshness(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);

        $product->listing_confirmed_at = Carbon::now();
        $product->save();

        return response()->json([
            'status' => true,
            'message' => "Freshness lifecycle for '{$product->name}' extended for 7 days.",
            'data' => [
                'id' => $product->id,
                'listing_confirmed_at' => $product->listing_confirmed_at->toIso8601String(),
                'freshness_expires_at' => $product->listing_confirmed_at->copy()->addDays(7)->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/products/{id}/toggle-stock
     */
    public function toggleStock(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:0',
            'is_in_stock' => 'nullable|boolean',
        ]);

        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);

        if (isset($validated['quantity'])) {
            $product->inventory_quantity = $validated['quantity'];
            $product->is_in_stock = $validated['quantity'] > 0;
        }

        if (isset($validated['is_in_stock'])) {
            $product->is_in_stock = (bool)$validated['is_in_stock'];
        }

        $product->save();

        return response()->json([
            'status' => true,
            'message' => "Product stock updated.",
            'data' => $product,
        ]);
    }

    /**
     * GET /api/v1/vendor/payouts
     * Financial Ledger of settlements and 48-Hour Cooldown Guard
     */
    public function payouts(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $settledOrders = Order::where('vendor_id', $vendor->id)
            ->where('vendor_settlement_status', 'settled')
            ->select('id', 'order_number', 'vendor_net_90', 'settled_at', 'settlement_proof_path')
            ->latest('settled_at')
            ->take(30)
            ->get();

        $eligibleOrders = Order::where('vendor_id', $vendor->id)
            ->where('vendor_settlement_status', 'eligible')
            ->select('id', 'order_number', 'vendor_net_90', 'delivered_at')
            ->latest()
            ->take(30)
            ->get();

        $isCooldown = $vendor->isWithinBankCooldown();
        $cooldownExpiresAt = $vendor->bank_updated_at ? $vendor->bank_updated_at->copy()->addHours(48) : null;

        return response()->json([
            'status' => true,
            'data' => [
                'bank_details' => [
                    'bank_name' => $vendor->bank_name,
                    'account_number_masked' => $vendor->account_number ? '******' . substr($vendor->account_number, -4) : null,
                    'account_name' => $vendor->account_name,
                ],
                'security_cooldown' => [
                    'is_active' => $isCooldown,
                    'bank_updated_at' => $vendor->bank_updated_at?->toIso8601String(),
                    'cooldown_expires_at' => $cooldownExpiresAt?->toIso8601String(),
                    'hours_remaining' => $isCooldown && $cooldownExpiresAt ? max(1, Carbon::now()->diffInHours($cooldownExpiresAt, false)) : 0,
                ],
                'totals' => [
                    'settled_sum' => Order::where('vendor_id', $vendor->id)->where('vendor_settlement_status', 'settled')->sum('vendor_net_90') ?? '0.00',
                    'eligible_sum' => Order::where('vendor_id', $vendor->id)->where('vendor_settlement_status', 'eligible')->sum('vendor_net_90') ?? '0.00',
                ],
                'settled_history' => $settledOrders,
                'eligible_pending' => $eligibleOrders,
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/bank/update
     * Update Bank Account (Triggers 48-Hour Cooldown)
     */
    public function updateBank(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        if ($ctx['is_employee']) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: Only merchant owner can modify settlement bank accounts.'], 403);
        }

        $vendor = $ctx['vendor'];

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => ['required', 'string', 'size:10', 'regex:/^[0-9]{10}$/'],
            'account_name' => 'required|string|max:150',
        ], [
            'account_number.size' => 'Account number must be exactly 10 digits (NUBAN format).',
            'account_number.regex' => 'Account number must contain only numbers.',
        ]);

        $vendor->update([
            'bank_name' => strip_tags(trim($validated['bank_name'])),
            'account_number' => trim($validated['account_number']),
            'account_name' => strtoupper(strip_tags(trim($validated['account_name']))),
            'bank_updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Designated bank account details updated successfully. 48-Hour Anti-Fraud Cooldown is now active.',
            'data' => [
                'bank_name' => $vendor->bank_name,
                'account_number_masked' => '******' . substr($vendor->account_number, -4),
                'account_name' => $vendor->account_name,
                'cooldown_active' => true,
                'cooldown_expires_at' => $vendor->bank_updated_at->copy()->addHours(48)->toIso8601String(),
            ],
        ]);
    }
}
