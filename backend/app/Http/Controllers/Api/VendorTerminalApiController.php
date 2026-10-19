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
use Illuminate\Support\Facades\DB;
use App\Services\ImageOptimizationService;
use App\Services\BcMathService;
use App\Models\VendorRecoveryLedger;
use App\Models\VendorPayoutRequest;
use App\Models\FinancialJournalEntry;
use App\Models\RiderProfile;
use App\Models\User;

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
                    'withdrawable_balance' => $vendor->getWithdrawableBalance(),
                    'escrow_held_balance' => $vendor->getEscrowHeldBalance(),
                    'pending_payout_balance' => $vendor->getPendingPayoutBalance(),
                    'total_settled_balance' => $vendor->getTotalSettledBalance(),
                    'unrecovered_debt' => VendorRecoveryLedger::where('vendor_id', $vendor->id)->where('status', 'pending_recovery')->sum('amount') ?? '0.00',
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
                'logisticsCompany:id,company_name,contact_phone',
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
                'logisticsCompany:id,company_name,contact_phone',
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
                'condition' => $product->condition ?? 'brand_new',
                'condition_label' => $product->condition_label,
                'package_tier' => $product->package_tier ?? 'bike',
                'package_tier_label' => $product->package_tier_label,
                'slash_price' => $product->slash_price,
                'discount_percent' => $product->discount_percent,
                'warranty_type' => $product->warranty_type ?? 'in_store_testing',
                'warranty_label' => $product->warranty_label,
                'video_url' => $product->video_url,
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
            'unit_price' => [
                'required',
                'numeric',
                'min:1.00',
                'max:50000000.00',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'slash_price' => [
                'nullable',
                'numeric',
                'gt:unit_price',
                'max:100000000.00',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'inventory_quantity' => 'required|integer|min:0|max:100000',
            'category_id' => 'required|integer|exists:categories,id',
            'condition' => 'nullable|string|in:brand_new,uk_used',
            'package_tier' => 'nullable|string|in:bike,keke,standard,bulky',
            'warranty_type' => 'nullable|string|in:in_store_testing,7_days_replacement,brand_warranty,as_is',
            'description' => 'nullable|string|max:2000',
            'image_url' => 'nullable|string|max:500',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp|max:5120',
            'video_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})([\?&].*)?$/'
            ],
        ], [
            'unit_price.min' => 'Product unit price must be at least ₦1.00 (Paystack standard minimum).',
            'unit_price.max' => 'Product unit price cannot exceed ₦50,000,000.00 per unit.',
            'unit_price.regex' => 'Product unit price must be a valid amount with at most 2 decimal places (kobo).',
            'slash_price.gt' => 'The original slash price must be greater than the selling price.',
            'slash_price.max' => 'Original slash price cannot exceed ₦100,000,000.00.',
            'slash_price.regex' => 'Original slash price must be a valid amount with at most 2 decimal places (kobo).',
            'video_url.regex' => 'Please provide a valid YouTube product video link (e.g., https://youtube.com/watch?v=... or https://youtu.be/...).',
            'condition.in' => 'Condition must be strictly Brand New or UK Used.',
            'package_tier.in' => 'Delivery logistics must be strictly Bike or Keke Cargo.',
        ]);

        $resolvedTier = in_array(strtolower($validated['package_tier'] ?? 'bike'), ['keke', 'bulky'], true) ? 'keke' : 'bike';

        $allImages = [];
        $thumbnailUrl = $validated['image_url'] ?? null;

        // Process gallery photos via ImageOptimizationService
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imgFile) {
                if ($imgFile->isValid()) {
                    $opt = app(ImageOptimizationService::class)->optimizeAndStoreProof($imgFile, 'products', ['max_dimension' => 1600, 'quality' => 84]);
                    $allImages[] = '/' . $opt['path'];
                }
            }
        }

        // Process primary photo
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $opt = app(ImageOptimizationService::class)->optimizeAndStoreProof($request->file('image'), 'products', ['max_dimension' => 1600, 'quality' => 84]);
            $singleUrl = '/' . $opt['path'];
            $thumbnailUrl = $singleUrl;
            if (!in_array($singleUrl, $allImages, true)) {
                array_unshift($allImages, $singleUrl);
            }
        }

        if (empty($thumbnailUrl) && !empty($allImages)) {
            $thumbnailUrl = $allImages[0];
        }

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $validated['category_id'],
            'condition' => $validated['condition'] ?? 'brand_new',
            'package_tier' => $resolvedTier,
            'warranty_type' => $validated['warranty_type'] ?? 'in_store_testing',
            'name' => strip_tags(trim($validated['name'])),
            'slug' => Str::slug($validated['name']) . '-' . rand(100, 999),
            'unit_price' => bcadd((string)$validated['unit_price'], '0', 2),
            'slash_price' => !empty($validated['slash_price']) ? bcadd((string)$validated['slash_price'], '0', 2) : null,
            'inventory_quantity' => $validated['inventory_quantity'],
            'is_in_stock' => $validated['inventory_quantity'] > 0,
            'description' => isset($validated['description']) ? strip_tags(trim($validated['description'])) : null,
            'thumbnail' => $thumbnailUrl ?? 'storage/products/default.png',
            'images' => !empty($allImages) ? $allImages : ($thumbnailUrl ? [$thumbnailUrl] : null),
            'youtube_video_id' => Product::extractYoutubeVideoId($request->input('video_url')),
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
     * POST /api/v1/vendor/products/{id}
     * Update Existing Product in Catalog
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $shop = $ctx['shop'];

        $query = Product::where('vendor_id', $vendor->id);
        if ($ctx['is_employee'] && $shop) {
            $query->where('shop_id', $shop->id);
        }
        $product = $query->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'unit_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:1.00',
                'max:50000000.00',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'slash_price' => [
                'nullable',
                'numeric',
                'max:100000000.00',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'inventory_quantity' => 'sometimes|required|integer|min:0|max:100000',
            'category_id' => 'sometimes|required|integer|exists:categories,id',
            'condition' => 'nullable|string|in:brand_new,uk_used',
            'package_tier' => 'nullable|string|in:bike,keke,standard,bulky',
            'warranty_type' => 'nullable|string|in:in_store_testing,7_days_replacement,brand_warranty,as_is',
            'description' => 'nullable|string|max:2000',
            'image_url' => 'nullable|string|max:500',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,png,jpg,webp|max:5120',
            'video_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})([\?&].*)?$/'
            ],
        ]);

        if (isset($validated['name'])) {
            $product->name = strip_tags(trim($validated['name']));
        }
        if (isset($validated['unit_price'])) {
            $product->unit_price = bcadd((string)$validated['unit_price'], '0', 2);
        }
        if (array_key_exists('slash_price', $validated)) {
            $product->slash_price = !empty($validated['slash_price']) ? bcadd((string)$validated['slash_price'], '0', 2) : null;
        }
        if (isset($validated['inventory_quantity'])) {
            $product->inventory_quantity = $validated['inventory_quantity'];
            $product->is_in_stock = $validated['inventory_quantity'] > 0;
        }
        if (isset($validated['category_id'])) {
            $product->category_id = $validated['category_id'];
        }
        if (isset($validated['condition'])) {
            $product->condition = $validated['condition'];
        }
        if (isset($validated['package_tier'])) {
            $product->package_tier = in_array(strtolower($validated['package_tier']), ['keke', 'bulky'], true) ? 'keke' : 'bike';
        }
        if (isset($validated['warranty_type'])) {
            $product->warranty_type = $validated['warranty_type'];
        }
        if (array_key_exists('description', $validated)) {
            $product->description = !empty($validated['description']) ? strip_tags(trim($validated['description'])) : null;
        }
        if ($request->has('video_url')) {
            $product->youtube_video_id = Product::extractYoutubeVideoId($request->input('video_url'));
        }

        $existingImages = is_array($product->images) ? $product->images : [];
        $uploadedUrls = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file->isValid()) {
                    $opt = app(ImageOptimizationService::class)->optimizeAndStoreProof($file, 'products', ['max_dimension' => 1600, 'quality' => 84]);
                    $uploadedUrls[] = '/' . $opt['path'];
                }
            }
        }

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $opt = app(ImageOptimizationService::class)->optimizeAndStoreProof($request->file('image'), 'products', ['max_dimension' => 1600, 'quality' => 84]);
            $primaryUrl = '/' . $opt['path'];
            $product->thumbnail = $primaryUrl;
            if (!in_array($primaryUrl, $uploadedUrls, true)) {
                array_unshift($uploadedUrls, $primaryUrl);
            }
        } elseif (!empty($validated['image_url'])) {
            $product->thumbnail = $validated['image_url'];
        }

        if (!empty($uploadedUrls)) {
            $product->images = array_values(array_unique(array_merge($uploadedUrls, $existingImages)));
            if (empty($product->thumbnail) || $product->thumbnail === 'storage/products/default.png') {
                $product->thumbnail = $uploadedUrls[0];
            }
        }

        $product->save();

        return response()->json([
            'status' => true,
            'message' => "Product '{$product->name}' updated successfully.",
            'data' => $product,
        ]);
    }
public function refreshFreshness(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);

        $product->listing_confirmed_at = Carbon::now();
        $product->listing_status = 'listed';
        $product->is_in_stock = true;
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

        $payoutRequests = VendorPayoutRequest::where('vendor_id', $vendor->id)
            ->select('id', 'amount', 'status', 'bank_name', 'account_number', 'account_name', 'created_at', 'disbursed_at', 'rejection_reason')
            ->latest()
            ->take(20)
            ->get();

        $isCooldown = $vendor->isWithinBankCooldown();
        $cooldownExpiresAt = $vendor->bank_updated_at ? $vendor->bank_updated_at->copy()->addHours(48) : null;
        $unrecoveredDebt = VendorRecoveryLedger::where('vendor_id', $vendor->id)->where('status', 'pending_recovery')->sum('amount') ?? '0.00';

        $withdrawable = $vendor->getWithdrawableBalance();
        $escrowHeld = $vendor->getEscrowHeldBalance();
        $pendingPayout = $vendor->getPendingPayoutBalance();
        $totalSettled = $vendor->getTotalSettledBalance();

        return response()->json([
            'status' => true,
            'data' => [
                'bank_details' => [
                    'bank_name' => $vendor->bank_name,
                    'bank_code' => $vendor->bank_code,
                    'account_number_masked' => $vendor->account_number ? '******' . substr($vendor->account_number, -4) : null,
                    'account_name' => $vendor->account_name,
                    'is_configured' => (!empty($vendor->account_number) && !empty($vendor->bank_name)),
                ],
                'security_cooldown' => [
                    'is_active' => $isCooldown,
                    'bank_updated_at' => $vendor->bank_updated_at?->toIso8601String(),
                    'cooldown_expires_at' => $cooldownExpiresAt?->toIso8601String(),
                    'hours_remaining' => $isCooldown && $cooldownExpiresAt ? max(1, Carbon::now()->diffInHours($cooldownExpiresAt, false)) : 0,
                ],
                'balances' => [
                    'withdrawable_balance' => $withdrawable,
                    'escrow_held_balance' => $escrowHeld,
                    'pending_payout_balance' => $pendingPayout,
                    'total_settled_balance' => $totalSettled,
                    'unrecovered_debt' => number_format((float)$unrecoveredDebt, 2, '.', ''),
                ],
                'totals' => [
                    'settled_sum' => $totalSettled,
                    'eligible_sum' => $withdrawable,
                ],
                'payout_requests' => $payoutRequests,
                'settled_history' => $settledOrders,
                'eligible_pending' => $eligibleOrders,
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/payouts
     * Merchant Mobile Payout Request
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        if ($ctx['is_employee']) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only merchant store owners can request payout withdrawals.',
            ], 403);
        }
        $vendor = $ctx['vendor'];

        if ($vendor->marketplace_status !== 'active' || ($vendor->user && $vendor->user->status !== 'active')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Merchant store account is suspended or inactive.',
            ], 403);
        }

        if (empty($vendor->account_number) || empty($vendor->bank_name)) {
            return response()->json([
                'status' => false,
                'message' => 'Please configure your verified commercial bank account before requesting withdrawal.',
            ], 422);
        }

        if ($vendor->isWithinBankCooldown()) {
            return response()->json([
                'status' => false,
                'message' => 'Security Cooldown Active: Bank account was recently updated. For fraud prevention, withdrawals are locked for 48 hours following bank detail modifications.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000|max:50000000',
        ], [
            'amount.min' => 'Minimum payout request amount is ₦1,000.00.',
            'amount.max' => 'Maximum single payout request cannot exceed ₦50,000,000.00.',
        ]);

        return DB::transaction(function () use ($vendor, $validated) {
            $lockedVendor = VendorProfile::where('id', $vendor->id)->lockForUpdate()->first();
            $withdrawable = $lockedVendor->getWithdrawableBalance();
            $amount = number_format((float)$validated['amount'], 2, '.', '');

            if (BcMathService::comp((string)$amount, (string)$withdrawable) > 0) {
                return response()->json([
                    'status' => false,
                    'message' => "Insufficient withdrawable balance. Your available cleared balance is ₦" . number_format((float)$withdrawable, 2) . ". In-transit or pending orders are held in platform escrow.",
                ], 422);
            }

            $payout = VendorPayoutRequest::create([
                'vendor_id' => $lockedVendor->id,
                'amount' => $amount,
                'status' => 'pending',
                'bank_name' => $lockedVendor->bank_name,
                'bank_code' => $lockedVendor->bank_code,
                'account_number' => $lockedVendor->account_number,
                'account_name' => $lockedVendor->account_name ?? $lockedVendor->business_name,
            ]);

            \App\Services\NotificationDispatchService::dispatchPayoutRequested($payout);

            // Authoritative double-entry financial journal
            $reqRef = 'MERCHANT-REQ-' . $lockedVendor->id . '-' . date('YmdHis');
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $reqRef,
                'event_id' => 'EVT-MERCHANT-REQ-' . $payout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $lockedVendor->id,
                'account_name' => 'vendor_escrow_holding',
                'entry_type' => 'debit',
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Merchant payout request placed by {$lockedVendor->business_name}",
            ]);

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $reqRef,
                'event_id' => 'EVT-MERCHANT-REQ-' . $payout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $lockedVendor->id,
                'account_name' => 'vendor_payout_payable',
                'entry_type' => 'credit',
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Merchant withdrawal liability accrued for {$lockedVendor->business_name}",
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal request submitted successfully. Treasury will process after audit review.',
                'data' => $payout,
            ], 201);
        });
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

    /**
     * Vendor Returns & Inbound Courier Handover Queue
     */
    public function returns(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $cases = \App\Models\ReturnCase::where('vendor_id', $vendor->id)
            ->with(['order:id,order_number', 'items.product', 'returnedByRider:id,name,phone'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $cases->items(),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'total' => $cases->total(),
            ],
        ]);
    }

    /**
     * Handshake 3: Vendor Disputes Return Condition at Shop Counter
     */
    public function disputeReturn(Request $request, string $caseNumber, \App\Services\DoorstepHandshakeService $handshakeService): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];
        $user = $ctx['user'];

        $validated = $request->validate([
            'dispute_reason' => 'required|string|max:1000',
            'dispute_photo' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        $case = \App\Models\ReturnCase::where('vendor_id', $vendor->id)
            ->where(function ($q) use ($caseNumber) {
                $q->where('case_number', trim($caseNumber))
                  ->orWhere('id', trim($caseNumber));
            })
            ->firstOrFail();

        $photoPath = null;
        if ($request->hasFile('dispute_photo')) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('dispute_photo'), 'vendor_disputes');
            $photoPath = $opt['path'];
        }

        $result = $handshakeService->vendorDisputeReturn(
            $case,
            $user,
            $validated['dispute_reason'],
            $photoPath
        );

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] ? $result['case'] : null,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/v1/vendor/riders
     * List all delivery personnel belonging to this merchant.
     */
    public function riders(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $riders = RiderProfile::where('vendor_id', $vendor->id)
            ->with(['user', 'shop'])
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'user_id' => $r->user_id,
                    'name' => $r->user->name ?? null,
                    'phone' => $r->user->phone ?? null,
                    'email' => $r->user->email ?? null,
                    'vehicle_type' => $r->vehicle_type,
                    'license_plate' => $r->license_plate,
                    'nin_number' => $r->nin_number,
                    'is_active' => (bool)$r->is_active,
                    'is_online' => (bool)$r->is_online,
                    'shop' => $r->shop ? ['id' => $r->shop->id, 'name' => $r->shop->name] : null,
                    'created_at' => $r->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $riders,
        ]);
    }

    /**
     * POST /api/v1/vendor/riders
     * Add and onboard a new delivery rider belonging to this merchant.
     */
    public function storeRider(Request $request): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30|unique:users,phone',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'vehicle_type' => 'nullable|in:motorcycle,bicycle,keke,van,transit_waybill',
            'license_plate' => 'nullable|string|max:50',
            'nin_number' => 'nullable|string|max:50',
            'shop_id' => 'nullable|integer|exists:shops,id',
        ]);

        return DB::transaction(function () use ($vendor, $validated) {
            $email = $validated['email'] ?? ('rider.' . preg_replace('/[^0-9]/', '', $validated['phone']) . '@vmarket.internal');
            if (User::where('email', $email)->exists()) {
                $email = 'rider.' . uniqid() . '@vmarket.internal';
            }

            $user = User::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $email,
                'password' => bcrypt($validated['password']),
                'user_type' => 'rider',
                'status' => 'active',
            ]);

            $shopId = $validated['shop_id'] ?? null;
            if ($shopId) {
                $shop = Shop::where('id', $shopId)->where('vendor_id', $vendor->id)->first();
                if (!$shop) {
                    $shopId = null;
                }
            }

            $rider = RiderProfile::create([
                'user_id' => $user->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shopId,
                'assigned_lga_id' => $shopId ? Shop::find($shopId)?->lga_id : null,
                'vehicle_type' => $validated['vehicle_type'] ?? 'motorcycle',
                'license_plate' => $validated['license_plate'] ?? null,
                'nin_number' => $validated['nin_number'] ?? null,
                'is_active' => true,
                'is_online' => true,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Delivery rider successfully added to your merchant fleet.',
                'data' => [
                    'id' => $rider->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'vehicle_type' => $rider->vehicle_type,
                    'is_active' => $rider->is_active,
                ],
            ], 201);
        });
    }

    /**
     * PATCH /api/v1/vendor/riders/{id}/status
     * Activate or deactivate a delivery rider belonging to this merchant.
     */
    public function updateRiderStatus(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $rider = RiderProfile::where('id', $id)->where('vendor_id', $vendor->id)->firstOrFail();
        $rider->is_active = $validated['is_active'];
        $rider->save();

        return response()->json([
            'status' => true,
            'message' => 'Delivery rider status updated.',
            'data' => [
                'id' => $rider->id,
                'is_active' => (bool)$rider->is_active,
            ],
        ]);
    }

    /**
     * POST /api/v1/vendor/orders/{id}/assign-rider
     * Assign a delivery rider belonging to this merchant to an order.
     */
    public function assignRiderToOrder(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveVendorContext($request);
        $vendor = $ctx['vendor'];

        $validated = $request->validate([
            'rider_id' => 'required|integer|exists:rider_profiles,id',
        ]);

        $order = Order::where('id', $id)->where('vendor_id', $vendor->id)->firstOrFail();
        $rider = RiderProfile::where('id', $validated['rider_id'])->where('vendor_id', $vendor->id)->first();

        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: This delivery rider does not belong to your merchant account.',
            ], 403);
        }

        if (!$rider->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'This delivery rider is currently inactive.',
            ], 422);
        }

        $order->rider_id = $rider->id;
        $order->save();

        return response()->json([
            'status' => true,
            'message' => "Order #{$order->order_number} assigned to delivery person {$rider->user->name}.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'rider_id' => $rider->id,
                'rider_name' => $rider->user->name,
            ],
        ]);
    }
}
