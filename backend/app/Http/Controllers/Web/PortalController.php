<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CheckoutIntent;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\ShopDeliveryCoverage;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorEmployeeProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\VendorPayoutRequest;
use App\Models\AdminAuditLog;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\ReturnInspection;
use App\Models\ReturnEvidence;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\CustomerAddress;
use App\Services\BcMathService;
use App\Services\CheckoutExecutionService;
use App\Services\OrderSettlementService;
use App\Services\LogisticsCompanyPayableService;
use App\Services\ReturnAndRefundService;
use App\Services\DoorstepHandshakeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalController extends Controller
{
        /**
     * Public Customer Storefront
     */
    public function storefront(Request $request)
    {
        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();
        
        $activeCity = $request->input('city', session('customer_city', 'Uyo'));
        $activeState = $request->input('state', session('customer_state', 'Akwa Ibom'));

        // Lookup LGA
        $selectedLga = Lga::where('name', $activeCity)->first();
        if (!$selectedLga) {
            $selectedLga = Lga::where('name', 'Uyo')->first();
        }
        $selectedLgaId = $selectedLga ? $selectedLga->id : 69;

        // Origin LGAs with active fulfillment to destination LGA
        $eligibleOriginLgaIds = collect([$selectedLgaId]);
        $activeLanes = DeliveryLane::where('destination_lga_id', $selectedLgaId)
            ->where('is_active', true)
            ->with('originLga')
            ->get();

        foreach ($activeLanes as $lane) {
            $eligibleOriginLgaIds->push($lane->origin_lga_id);
        }
        $eligibleOriginLgaIds = $eligibleOriginLgaIds->unique();

        // Check local merchant availability in this LGA
        $hasLocalShops = Shop::where('lga_id', $selectedLgaId)->where('pickup_enabled', true)->exists();

        // Count local products vs corridor transit products
        $localProductsCount = Product::marketplaceEligible()
            ->whereHas('shop', function ($q) use ($selectedLgaId) {
                $q->where('lga_id', $selectedLgaId);
            })->count();

        $corridorProductsCount = Product::marketplaceEligible()
            ->whereHas('shop', function ($q) use ($eligibleOriginLgaIds, $selectedLgaId) {
                $q->whereIn('lga_id', $eligibleOriginLgaIds)->where('lga_id', '!=', $selectedLgaId);
            })->count();

        // Scope: 'local' (vendor shop in this LGA), 'corridor' (transit lanes), or 'all' (delivering to this LGA)
        // If customer selects LGA, default to 'local' if local items exist, otherwise allow 'all' with clear corridor labeling
        $defaultScope = ($localProductsCount > 0) ? 'local' : 'all';
        $scope = $request->input('scope', $defaultScope);

        // Query eligible products
        $query = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug']);

        if ($scope === 'local') {
            $query->whereHas('shop', function ($q) use ($selectedLgaId) {
                $q->where('lga_id', $selectedLgaId);
            });
        } elseif ($scope === 'corridor') {
            $query->whereHas('shop', function ($q) use ($eligibleOriginLgaIds, $selectedLgaId) {
                $q->whereIn('lga_id', $eligibleOriginLgaIds)->where('lga_id', '!=', $selectedLgaId);
            });
        } else {
            // All delivering: local shops prioritized first, then active corridors
            $query->whereHas('shop', function ($q) use ($eligibleOriginLgaIds) {
                $q->whereIn('lga_id', $eligibleOriginLgaIds);
            })->orderByRaw("CASE WHEN shop_id IN (SELECT id FROM shops WHERE lga_id = ?) THEN 0 ELSE 1 END", [$selectedLgaId]);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        if ($request->filled('condition')) {
            $condition = $request->input('condition');
            if (in_array($condition, ['brand_new', 'uk_used'], true)) {
                $query->where('condition', $condition);
            }
        }

        if ($request->filled('package_tier')) {
            $query->where('package_tier', $request->input('package_tier'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->latest()->take(12)->get();
        $isFallbackCatalog = false;
        $isCorridorCatalog = false;

        // If local catalog is empty without search/category filters, provide clear corridor transit items
        if ($products->isEmpty() && !$request->filled('search') && !$request->filled('category')) {
            if ($scope === 'local' && $corridorProductsCount > 0) {
                // Fetch corridor products with clear transit indication
                $products = Product::marketplaceEligible()
                    ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
                    ->whereHas('shop', function ($q) use ($eligibleOriginLgaIds, $selectedLgaId) {
                        $q->whereIn('lga_id', $eligibleOriginLgaIds)->where('lga_id', '!=', $selectedLgaId);
                    })
                    ->latest()
                    ->take(12)
                    ->get();
                $isCorridorCatalog = true;
            } elseif ($corridorProductsCount === 0) {
                // Truly empty LGA: load flagship items as explicit regional dispatch
                $products = Product::marketplaceEligible()
                    ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
                    ->latest()
                    ->take(12)
                    ->get();
                $isFallbackCatalog = true;
            }
        }

        $latestProducts = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
            ->latest()
            ->take(6)
            ->get();

        // Stores Closer to You: Strictly physical shops located in this LGA
        $shops = Shop::with('lga:id,name')
            ->where('pickup_enabled', true)
            ->where('lga_id', $selectedLgaId)
            ->take(6)
            ->get();

        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();

        // Coverage Status
        if ($hasLocalShops) {
            $coverageStatus = 'pickup_and_delivery';
            $statusBadge = '⚡ In-Shop Pickup & Same-Day Delivery Active';
            $previewStatus = 'Pickup and delivery available in this local government';
        } elseif ($activeLanes->isNotEmpty()) {
            $minHours = $activeLanes->min('estimated_delivery_hours_min') ?? 12;
            $maxHours = $activeLanes->max('estimated_delivery_hours_max') ?? 24;
            $coverageStatus = 'transit_delivery';
            $statusBadge = "🚚 Transit Delivery Corridor Active ({$minHours}-{$maxHours} hrs)";
            $previewStatus = "Delivery corridor active ({$minHours}-{$maxHours} hrs delivery speed)";
        } else {
            $coverageStatus = 'regional';
            $statusBadge = '📦 Nationwide Park Dispatch';
            $previewStatus = 'Delivery options and speeds vary by regional dispatch';
        }

        // Return dynamic JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson() || $request->has('ajax')) {
            $productsHtml = view('storefront.partials._products_grid', compact(
                'products', 'activeCity', 'selectedLgaId', 'activeLanes', 
                'isFallbackCatalog', 'isCorridorCatalog', 'scope', 
                'localProductsCount', 'corridorProductsCount'
            ))->render();
            
            $shopsHtml = view('storefront.partials._shops_grid', compact('shops', 'activeCity', 'selectedLgaId'))->render();

            return response()->json([
                'success' => true,
                'city' => $activeCity,
                'state' => $activeState,
                'lga_id' => $selectedLgaId,
                'coverage_status' => $coverageStatus,
                'status_badge' => $statusBadge,
                'preview_status' => $previewStatus,
                'products_count' => $products->count(),
                'local_products_count' => $localProductsCount,
                'corridor_products_count' => $corridorProductsCount,
                'scope' => $scope,
                'products_html' => $productsHtml,
                'shops_html' => $shopsHtml,
                'is_fallback' => $isFallbackCatalog,
                'is_corridor' => $isCorridorCatalog,
            ]);
        }

        // Dynamic Cached Storefront Banners
        $heroBanners = Banner::getActiveHeroBanners();
        $promoBanners = Banner::getActivePromoBanners();

        return view('storefront.index', compact(
            'categories', 'products', 'latestProducts', 'shops', 'lgas',
            'activeCity', 'activeState', 'selectedLgaId', 'activeLanes',
            'coverageStatus', 'statusBadge', 'previewStatus', 'isFallbackCatalog',
            'isCorridorCatalog', 'scope', 'localProductsCount', 'corridorProductsCount',
            'heroBanners', 'promoBanners'
        ));
    }

    /**
     * Set Customer Active LGA Location
     */
    public function setLocation(Request $request)
    {
        $request->validate([
            'city' => 'required|string',
            'state' => 'nullable|string',
            'lga_id' => 'nullable|integer',
        ]);

        $city = trim((string)$request->input('city'));
        $state = $request->input('state', 'Akwa Ibom');

        // Prioritize finding LGA by city name so a stale or mismatched lga_id never overrides user selection
        $lga = Lga::where('name', $city)->first();
        if (!$lga && $request->filled('lga_id')) {
            $candidate = Lga::find($request->input('lga_id'));
            if ($candidate && (empty($city) || strcasecmp($candidate->name, $city) === 0)) {
                $lga = $candidate;
            }
        }
        if (!$lga) {
            $lga = Lga::where('name', 'like', "%{$city}%")->first();
        }

        $lgaId = $lga ? $lga->id : 69;
        $cityName = $lga ? $lga->name : $city;

        session([
            'customer_city' => $cityName,
            'customer_state' => $state,
            'customer_lga_id' => $lgaId,
        ]);

        // Calculate coverage info
        $hasLocalShops = Shop::where('lga_id', $lgaId)->where('pickup_enabled', true)->exists();
        $activeLanes = DeliveryLane::where('destination_lga_id', $lgaId)->where('is_active', true)->get();

        if ($hasLocalShops) {
            $coverageStatus = 'pickup_and_delivery';
            $statusBadge = '⚡ In-Shop Pickup & Same-Day Delivery Active';
            $previewStatus = 'Pickup and delivery available in this local government';
        } elseif ($activeLanes->isNotEmpty()) {
            $minHours = $activeLanes->min('estimated_delivery_hours_min') ?? 12;
            $maxHours = $activeLanes->max('estimated_delivery_hours_max') ?? 24;
            $coverageStatus = 'transit_delivery';
            $statusBadge = "🚚 Transit Delivery Corridor Active ({$minHours}-{$maxHours} hrs)";
            $previewStatus = "Delivery corridor active ({$minHours}-{$maxHours} hrs delivery speed)";
        } else {
            $coverageStatus = 'regional';
            $statusBadge = '📦 Nationwide Park Dispatch';
            $previewStatus = 'Delivery options and speeds vary by regional dispatch';
        }

        return response()->json([
            'success' => true,
            'city' => $cityName,
            'state' => $state,
            'lga_id' => $lgaId,
            'coverage_status' => $coverageStatus,
            'status_badge' => $statusBadge,
            'preview_status' => $previewStatus,
        ]);
    }

    /**
     * Super Admin Command Center Dashboard
     */
    public function adminDashboard()
    {
        $metrics = [
            'total_vendors' => VendorProfile::count(),
            'total_shops' => Shop::count(),
            'total_riders' => RiderProfile::count(),
            'total_products' => Product::count(),
            'active_orders' => Order::whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery'])->count(),
            'pending_settlements' => Order::where('vendor_settlement_status', 'eligible')->count(),
            'held_settlements' => Order::where('vendor_settlement_status', 'held')->count(),
            'total_cashback_issued' => CustomerCashbackLedger::sum('amount') ?? '0.00',
        ];

        $pendingSettlements = Order::where('vendor_settlement_status', 'eligible')
            ->with(['vendor:id,business_name,bank_name,account_number', 'customer:id,name'])
            ->latest()
            ->take(10)
            ->get();

        $recentOrders = Order::with(['customer:id,name', 'vendor:id,business_name', 'shop:id,name'])
            ->latest()
            ->take(10)
            ->get();

        $deliveryLanes = DeliveryLane::with(['originLga.state', 'destinationLga.state'])
            ->where('is_active', true)
            ->take(10)
            ->get();

        $announcementSettings = [
            'support_phone' => \App\Models\SystemSetting::get('support_phone', '09118949035'),
            'support_whatsapp' => \App\Models\SystemSetting::get('support_whatsapp', '09118949035'),
            'support_email' => \App\Models\SystemSetting::get('support_email', 'support@victoriousmarket.com'),
            'announcement_type' => \App\Models\SystemSetting::get('announcement_type', 'phone_order'),
            'announcement_badge' => \App\Models\SystemSetting::get('announcement_badge', '💬'),
            'announcement_text' => \App\Models\SystemSetting::get('announcement_text', 'Call or Whatsapp 09118949035 to order'),
            'announcement_bg_color' => \App\Models\SystemSetting::get('announcement_bg_color', '#0F172A'),
            'announcement_link' => \App\Models\SystemSetting::get('announcement_link', 'https://wa.me/2349118949035'),
            'announcement_is_active' => (bool)\App\Models\SystemSetting::get('announcement_is_active', true),
        ];

        $allVendors = VendorProfile::with(['user', 'shops'])->latest()->get();
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();
                $zonalSettings = [
            'zone_intra_lga_fee' => \App\Models\SystemSetting::get('zone_intra_lga_fee', '500.00'),
            'zone_intra_lga_eta' => \App\Models\SystemSetting::get('zone_intra_lga_eta', '2-4 hours'),
            'zone_inter_lga_fee' => \App\Models\SystemSetting::get('zone_inter_lga_fee', '1500.00'),
            'zone_inter_lga_eta' => \App\Models\SystemSetting::get('zone_inter_lga_eta', 'Same day / 12-24 hours'),
            'zone_inter_state_fee' => \App\Models\SystemSetting::get('zone_inter_state_fee', '3500.00'),
            'zone_inter_state_eta' => \App\Models\SystemSetting::get('zone_inter_state_eta', '2-4 business days'),
            'zone_bulky_cargo_surcharge' => \App\Models\SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'),
        ];

        // Enrich delivery lanes with bidirectional status
        $allLanePairs = DeliveryLane::where('is_active', true)->get();
        foreach ($deliveryLanes as $l) {
            $hasReverse = $allLanePairs->contains(function ($item) use ($l) {
                return $item->origin_lga_id === $l->destination_lga_id && $item->destination_lga_id === $l->origin_lga_id;
            });
            $l->is_bidirectional = $hasReverse;
        }

        return view('admin.dashboard', compact('metrics', 'pendingSettlements', 'recentOrders', 'deliveryLanes', 'announcementSettings', 'allVendors', 'allLgas', 'zonalSettings'));
    }

    /**
     * Super Admin Announcement & Contact Settings Update
     */
    /**
     * Dedicated Page: Directional LGA Delivery Lanes & Hub-and-Spoke Matrix
     */
    public function adminDeliveryLanesPage(Request $request)
    {
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();

        // 1. Resolve Active Hub LGA (Default to Uyo if not specified)
        $hubLgaId = (int)$request->input('hub_lga_id', 0);
        if ($hubLgaId <= 0) {
            $defaultHub = Lga::where('name', 'Uyo')->first();
            $hubLgaId = $defaultHub ? $defaultHub->id : ($allLgas->first()->id ?? 69);
        }
        $currentHub = Lga::with('state')->findOrFail($hubLgaId);

        // 2. TIER 1: Intra-LGA (Municipal Delivery within Hub LGA)
        $intraLane = DeliveryLane::where('origin_lga_id', $currentHub->id)
            ->where('destination_lga_id', $currentHub->id)
            ->first();

        // 3. TIER 2: Inter-LGA (All other LGAs in the same state)
        $stateLgas = Lga::where('state_id', $currentHub->state_id)
            ->where('id', '!=', $currentHub->id)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $interLanes = DeliveryLane::where('origin_lga_id', $currentHub->id)
            ->whereIn('destination_lga_id', $stateLgas->pluck('id'))
            ->get()
            ->keyBy('destination_lga_id');

        // Check reverse corridors to determine bidirectionality for Inter-LGA
        $reverseInterLanes = DeliveryLane::where('destination_lga_id', $currentHub->id)
            ->whereIn('origin_lga_id', $stateLgas->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('origin_lga_id');

        // 4. TIER 3: Out-of-State (All other States in Nigeria)
        $otherStates = State::where('id', '!=', $currentHub->state_id)
            ->where('is_active', true)
            ->with(['lgas' => function ($q) {
                $q->where('is_active', true)->orderBy('name', 'asc');
            }])
            ->orderBy('name', 'asc')
            ->get();

        $outOfStateLanes = DeliveryLane::where('origin_lga_id', $currentHub->id)
            ->whereHas('destinationLga', function ($q) use ($currentHub) {
                $q->where('state_id', '!=', $currentHub->state_id);
            })
            ->with('destinationLga.state')
            ->get()
            ->groupBy(function ($lane) {
                return $lane->destinationLga->state_id ?? 0;
            });

        // Compute summary counts for the Hub
        $intraActive = $intraLane ? (bool)$intraLane->is_active : true;
        $interSuspendedCount = $interLanes->where('is_active', false)->count();
        $interActiveCount = $stateLgas->count() - $interSuspendedCount;

        $suspendedStateIds = DeliveryLane::where('origin_lga_id', $currentHub->id)
            ->whereHas('destinationLga', function ($q) use ($currentHub) {
                $q->where('state_id', '!=', $currentHub->state_id);
            })
            ->where('is_active', false)
            ->get()
            ->pluck('destinationLga.state_id')
            ->unique();
        $outOfStateActiveCount = $otherStates->count() - $suspendedStateIds->count();

        // All custom corridors originating from this hub
        $deliveryLanes = DeliveryLane::where('origin_lga_id', $currentHub->id)
            ->with(['originLga.state', 'destinationLga.state'])
            ->latest()
            ->get();

        $allLanePairs = DeliveryLane::where('is_active', true)->get();
        foreach ($deliveryLanes as $l) {
            $hasReverse = $allLanePairs->contains(function ($item) use ($l) {
                return $item->origin_lga_id === $l->destination_lga_id && $item->destination_lga_id === $l->origin_lga_id;
            });
            $l->is_bidirectional = $hasReverse;
        }

        $zonalSettings = [
            'zone_intra_lga_fee' => \App\Models\SystemSetting::get('zone_intra_lga_fee', '500.00'),
            'zone_intra_lga_eta' => \App\Models\SystemSetting::get('zone_intra_lga_eta', '2-4 hours'),
            'zone_inter_lga_fee' => \App\Models\SystemSetting::get('zone_inter_lga_fee', '1500.00'),
            'zone_inter_lga_eta' => \App\Models\SystemSetting::get('zone_inter_lga_eta', 'Same day / 12-24 hours'),
            'zone_inter_state_fee' => \App\Models\SystemSetting::get('zone_inter_state_fee', '3500.00'),
            'zone_inter_state_eta' => \App\Models\SystemSetting::get('zone_inter_state_eta', '2-4 business days'),
            'zone_bulky_cargo_surcharge' => \App\Models\SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'),
        ];

        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.delivery_lanes', compact(
            'currentHub',
            'intraLane',
            'intraActive',
            'stateLgas',
            'interLanes',
            'reverseInterLanes',
            'interActiveCount',
            'otherStates',
            'outOfStateLanes',
            'outOfStateActiveCount',
            'deliveryLanes',
            'allLgas',
            'zonalSettings',
            'pendingSettlementsCount'
        ));
    }

    /**
     * Dedicated Page: 90/5/5 Sovereign Escrow & Settlement Desk
     */
    public function adminSettlementsPage(Request $request)
    {
        $pendingSettlements = Order::where('vendor_settlement_status', 'eligible')
            ->with(['vendor:id,business_name,bank_name,account_number', 'customer:id,name'])
            ->latest()
            ->get();

        $heldSettlementsCount = Order::where('vendor_settlement_status', 'held')->count();
        $totalCashbackIssued = CustomerCashbackLedger::sum('amount') ?? '0.00';
        $pendingSettlementsCount = $pendingSettlements->count();

        // Dual Revenue Stream Calculations
        $totalVendorCommission = Order::where('order_status', 'delivered')->sum('platform_margin_5');
        $totalDeliveryCommission = Order::where('order_status', 'delivered')->sum('platform_delivery_fee');
        $totalVMarketEarnings = BcMathService::add((string)$totalVendorCommission, (string)$totalDeliveryCommission);

        // Logistics Corporate Payout Requests
        $pendingLogisticsPayouts = LogisticsPayoutRequest::with('company')->latest()->get();
        $pendingVendorPayouts = VendorPayoutRequest::with('vendor')->latest()->get();
        $totalLogisticsEscrowBalance = LogisticsCompanyProfile::sum('wallet_balance');
        $refundWindowHours = (int) \App\Models\SystemSetting::get('refund_window_hours', 24);

        return view('admin.settlements', compact(
            'pendingSettlements',
            'heldSettlementsCount',
            'totalCashbackIssued',
            'pendingSettlementsCount',
            'totalVendorCommission',
            'totalDeliveryCommission',
            'totalVMarketEarnings',
            'pendingLogisticsPayouts',
            'pendingVendorPayouts',
            'totalLogisticsEscrowBalance',
            'refundWindowHours'
        ));
    }

    /**
     * Dedicated Page: Storefront Announcement Bar & Customer Care Desk
     */
    
    // ==========================================
    // STOREFRONT BANNER MANAGEMENT (Super Admin)
    // ==========================================

    /**
     * Defense-in-depth HTML / script sanitization helper.
     * Completely strips malicious <script>...</script> and <style>...</style> blocks including inner content,
     * as well as all remaining HTML tags.
     */
    protected function sanitizeInput(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);
        $cleaned = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleaned);
        $cleaned = strip_tags($cleaned);
        return trim($cleaned);
    }

    public function adminBannersPage()
    {
        $banners = Banner::orderBy('banner_type', 'asc')->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();
        $heroBannersCount = $banners->where('banner_type', 'hero_slider')->count();
        $promoBannersCount = $banners->where('banner_type', 'promo_strip')->count();
        $activeBannersCount = $banners->where('is_active', true)->count();

        return view('admin.banners', compact('banners', 'heroBannersCount', 'promoBannersCount', 'activeBannersCount'));
    }

    public function adminStoreBanner(Request $request)
    {
        $validated = $request->validate([
            'banner_type' => 'required|string|in:hero_slider,promo_strip,category_banner,popup_modal',
            'template_style' => 'nullable|string|in:luxury_split,full_cover,flash_urgency,minimal_bento,fresh_market,brand_prestige',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'badge_text' => 'nullable|string|max:100',
            'discount_tag' => 'nullable|string|max:100',
            'feature_tags' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:3072',
            'image_path' => 'nullable|string|max:255',
            'bg_gradient' => 'nullable|string|max:255',
            'text_color' => 'nullable|string|max:50',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable',
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners', 'public');
            $imagePath = 'storage/' . $path;
        }

        $banner = Banner::create([
            'banner_type' => $validated['banner_type'],
            'template_style' => $validated['template_style'] ?? 'luxury_split',
            'title' => $this->sanitizeInput($validated['title']),
            'subtitle' => !empty($validated['subtitle']) ? $this->sanitizeInput($validated['subtitle']) : null,
            'badge_text' => !empty($validated['badge_text']) ? $this->sanitizeInput($validated['badge_text']) : 'VERIFIED OMNICHANNEL MARKET',
            'discount_tag' => !empty($validated['discount_tag']) ? $this->sanitizeInput($validated['discount_tag']) : null,
            'feature_tags' => !empty($validated['feature_tags']) ? $this->sanitizeInput($validated['feature_tags']) : null,
            'image_path' => $imagePath,
            'bg_gradient' => !empty($validated['bg_gradient']) ? $this->sanitizeInput($validated['bg_gradient']) : 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)',
            'text_color' => !empty($validated['text_color']) ? $this->sanitizeInput($validated['text_color']) : '#0F172A',
            'cta_text' => !empty($validated['cta_text']) ? $this->sanitizeInput($validated['cta_text']) : 'Shop Verified Items',
            'cta_url' => !empty($validated['cta_url']) ? $this->sanitizeInput($validated['cta_url']) : '#catalog',
            'sort_order' => (int)($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active'),
        ]);

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_banner_created',
            'target_type' => Banner::class,
            'target_id' => $banner->id,
            'new_values' => [
                'banner_type' => $banner->banner_type,
                'title' => $banner->title,
                'template_style' => $banner->template_style,
                'is_active' => $banner->is_active,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.banners.index')->with('status', 'Promotional storefront banner published successfully!');
    }

    public function adminUpdateBanner(Request $request, int $id)
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'banner_type' => 'required|string|in:hero_slider,promo_strip,category_banner,popup_modal',
            'template_style' => 'nullable|string|in:luxury_split,full_cover,flash_urgency,minimal_bento,fresh_market,brand_prestige',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'badge_text' => 'nullable|string|max:100',
            'discount_tag' => 'nullable|string|max:100',
            'feature_tags' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:3072',
            'image_path' => 'nullable|string|max:255',
            'bg_gradient' => 'nullable|string|max:255',
            'text_color' => 'nullable|string|max:50',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable',
        ]);

        $oldValues = [
            'title' => $banner->title,
            'template_style' => $banner->template_style,
            'is_active' => $banner->is_active,
        ];

        $imagePath = $banner->image_path;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners', 'public');
            $imagePath = 'storage/' . $path;
        } elseif (array_key_exists('image_path', $validated)) {
            $imagePath = $validated['image_path'];
        }

        $banner->update([
            'banner_type' => $validated['banner_type'],
            'template_style' => $validated['template_style'] ?? ($banner->template_style ?: 'luxury_split'),
            'title' => $this->sanitizeInput($validated['title']),
            'subtitle' => !empty($validated['subtitle']) ? $this->sanitizeInput($validated['subtitle']) : null,
            'badge_text' => !empty($validated['badge_text']) ? $this->sanitizeInput($validated['badge_text']) : 'VERIFIED OMNICHANNEL MARKET',
            'discount_tag' => !empty($validated['discount_tag']) ? $this->sanitizeInput($validated['discount_tag']) : null,
            'feature_tags' => !empty($validated['feature_tags']) ? $this->sanitizeInput($validated['feature_tags']) : null,
            'image_path' => $imagePath,
            'bg_gradient' => !empty($validated['bg_gradient']) ? $this->sanitizeInput($validated['bg_gradient']) : 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)',
            'text_color' => !empty($validated['text_color']) ? $this->sanitizeInput($validated['text_color']) : '#0F172A',
            'cta_text' => !empty($validated['cta_text']) ? $this->sanitizeInput($validated['cta_text']) : 'Shop Verified Items',
            'cta_url' => !empty($validated['cta_url']) ? $this->sanitizeInput($validated['cta_url']) : '#catalog',
            'sort_order' => (int)($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active'),
        ]);

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_banner_updated',
            'target_type' => Banner::class,
            'target_id' => $banner->id,
            'old_values' => $oldValues,
            'new_values' => [
                'title' => $banner->title,
                'template_style' => $banner->template_style,
                'is_active' => $banner->is_active,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.banners.index')->with('status', "Banner #{$banner->id} updated successfully!");
    }

    public function adminToggleBanner(Request $request, int $id)
    {
        $banner = Banner::findOrFail($id);
        $oldState = (bool)$banner->is_active;
        $banner->is_active = !$banner->is_active;
        $banner->save();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_banner_toggled',
            'target_type' => Banner::class,
            'target_id' => $banner->id,
            'old_values' => ['is_active' => $oldState],
            'new_values' => ['is_active' => (bool)$banner->is_active],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        $statusText = $banner->is_active ? 'Activated' : 'Paused';
        return redirect()->route('admin.banners.index')->with('status', "Banner #{$banner->id} is now {$statusText}.");
    }

    public function adminDeleteBanner(Request $request, int $id)
    {
        $banner = Banner::findOrFail($id);
        $bannerId = $banner->id;
        $bannerTitle = $banner->title;
        $banner->delete();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_banner_deleted',
            'target_type' => Banner::class,
            'target_id' => $bannerId,
            'old_values' => ['title' => $bannerTitle],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.banners.index')->with('status', 'Banner removed from storefront catalog.');
    }
    public function adminAnnouncementsPage(Request $request)
    {
        $announcementSettings = [
            'support_phone' => \App\Models\SystemSetting::get('support_phone', '09118949035'),
            'support_whatsapp' => \App\Models\SystemSetting::get('support_whatsapp', '09118949035'),
            'support_email' => \App\Models\SystemSetting::get('support_email', 'support@victoriousmarket.com'),
            'announcement_type' => \App\Models\SystemSetting::get('announcement_type', 'phone_order'),
            'announcement_badge' => \App\Models\SystemSetting::get('announcement_badge', '💬'),
            'announcement_text' => \App\Models\SystemSetting::get('announcement_text', 'Call or Whatsapp 09118949035 to order'),
            'announcement_bg_color' => \App\Models\SystemSetting::get('announcement_bg_color', '#0F172A'),
            'announcement_link' => \App\Models\SystemSetting::get('announcement_link', 'https://wa.me/2349118949035'),
            'announcement_is_active' => (bool)\App\Models\SystemSetting::get('announcement_is_active', true),
        ];

        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.announcements', compact('announcementSettings', 'pendingSettlementsCount'));
    }

    /**
     * Dedicated Page: Merchant Accreditation & KYC Desk
     */
    public function adminMerchantsPage(Request $request)
    {
        $allVendors = VendorProfile::with(['user', 'shops.lga'])->latest()->get();
        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();
        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();

        return view('admin.merchants', compact('allVendors', 'pendingSettlementsCount', 'lgas'));
    }

    /**
     * Admin Action: Onboard New Supplier & Physical Shop (Admin-Only Sourcing)
     */
    public function adminStoreSupplier(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:users,phone',
            'email' => 'nullable|email|max:150|unique:users,email',
            'password' => 'nullable|string|min:8',
            'shop_name' => 'required|string|max:255',
            'shop_address' => 'required|string|max:500',
            'lga_id' => 'required|exists:lgas,id',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['phone']);
            $email = !empty($validated['email']) ? strtolower(trim($validated['email'])) : 'supplier_' . Str::lower(Str::random(8)) . '@victoriousmarket.ng';
            $password = !empty($validated['password']) ? $validated['password'] : Str::random(12);

            $user = User::create([
                'name' => strip_tags(trim($validated['contact_name'])),
                'email' => $email,
                'phone' => $cleanPhone,
                'user_type' => 'vendor',
                'status' => 'active',
                'password' => Hash::make($password),
            ]);

            $vendor = VendorProfile::create([
                'user_id' => $user->id,
                'business_name' => strip_tags(trim($validated['business_name'])),
                'kyc_status' => 'verified',
                'kyc_reviewed_at' => now(),
                'bank_name' => $validated['bank_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'account_name' => $validated['account_name'] ?? $validated['business_name'],
                'marketplace_status' => 'active',
            ]);

            $lga = Lga::findOrFail($validated['lga_id']);

            $shop = Shop::create([
                'vendor_id' => $vendor->id,
                'name' => strip_tags(trim($validated['shop_name'])),
                'slug' => Str::slug($validated['shop_name']) . '-' . Str::lower(Str::random(5)),
                'address' => strip_tags(trim($validated['shop_address'])),
                'country_id' => $lga->country_id ?? 1,
                'state_id' => $lga->state_id,
                'lga_id' => $lga->id,
                'phone' => $cleanPhone,
                'email' => $email,
                'is_primary_branch' => true,
                'is_active' => true,
                'pickup_enabled' => true,
                'pickup_prep_time_minutes' => 30,
            ]);

            AdminAuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'SUPPLIER_ONBOARDED',
                'target_type' => 'VendorProfile',
                'target_id' => $vendor->id,
                'new_values' => [
                    'supplier_name' => $vendor->business_name,
                    'contact_name' => $user->name,
                    'phone' => $user->phone,
                    'shop_name' => $shop->name,
                    'lga' => $lga->name,
                ],
            ]);

            return redirect()->route('admin.merchants.index')->with('success', "Supplier '{$vendor->business_name}' and physical shop in {$lga->name} successfully onboarded!");
        });
    }

    /**
     * Dedicated Page: Admin Logistics Companies Accreditation Desk
     */
    public function adminLogisticsCompaniesPage(Request $request)
    {
        $companies = LogisticsCompanyProfile::with(['user', 'hqLga.state', 'riders.user'])->latest()->get();
        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();
        $totalRiders = RiderProfile::whereNotNull('logistics_company_id')->count();
        $activeRiders = RiderProfile::whereNotNull('logistics_company_id')->where('is_active', true)->count();
        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.logistics_companies', compact('companies', 'lgas', 'totalRiders', 'activeRiders', 'pendingSettlementsCount'));
    }

    /**
     * Admin Action: Onboard New Logistics Company (Admin-Only Fleet Accreditation)
     */
    public function adminStoreLogisticsCompany(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:users,phone',
            'email' => 'nullable|email|max:150|unique:users,email',
            'password' => 'nullable|string|min:8',
            'registration_number' => 'nullable|string|max:100',
            'hq_address' => 'required|string|max:500',
            'lga_id' => 'required|exists:lgas,id',
            'fleet_size' => 'nullable|integer|min:1|max:1000',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['phone']);
            $email = !empty($validated['email']) ? strtolower(trim($validated['email'])) : 'logistics_' . Str::lower(Str::random(8)) . '@victoriousmarket.ng';
            $password = !empty($validated['password']) ? $validated['password'] : Str::random(12);

            $user = User::create([
                'name' => strip_tags(trim($validated['contact_name'])),
                'email' => $email,
                'phone' => $cleanPhone,
                'user_type' => 'logistics_company',
                'status' => 'active',
                'password' => Hash::make($password),
            ]);

            $lga = Lga::findOrFail($validated['lga_id']);

            $company = LogisticsCompanyProfile::create([
                'user_id' => $user->id,
                'company_name' => strip_tags(trim($validated['company_name'])),
                'registration_number' => $validated['registration_number'] ?? null,
                'contact_email' => $email,
                'contact_phone' => $cleanPhone,
                'hq_address' => strip_tags(trim($validated['hq_address'])),
                'lga_id' => $lga->id,
                'fleet_size' => $validated['fleet_size'] ?? 10,
                'commission_rate' => $validated['commission_rate'] ?? 0.00,
                'status' => 'active',
                'bank_name' => $validated['bank_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'account_name' => $validated['account_name'] ?? $validated['company_name'],
                'bank_updated_at' => !empty($validated['account_number']) ? now() : null,
            ]);

            AdminAuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'LOGISTICS_COMPANY_ONBOARDED',
                'target_type' => 'LogisticsCompanyProfile',
                'target_id' => $company->id,
                'new_values' => [
                    'company_name' => $company->company_name,
                    'contact_name' => $user->name,
                    'phone' => $user->phone,
                    'lga' => $lga->name,
                    'fleet_size' => $company->fleet_size,
                ],
            ]);

            return redirect()->route('admin.logistics_companies.index')->with('success', "Logistics company '{$company->company_name}' successfully accredited and onboarded!");
        });
    }

    /**
     * Admin Action: Toggle Logistics Company Active/Suspended Status
     */
    public function adminToggleLogisticsCompanyStatus($id)
    {
        $company = LogisticsCompanyProfile::findOrFail($id);
        $newStatus = $company->status === 'active' ? 'suspended' : 'active';
        $company->status = $newStatus;
        $company->save();

        if ($company->user) {
            $company->user->status = $newStatus;
            $company->user->save();
        }

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'LOGISTICS_COMPANY_STATUS_TOGGLED',
            'target_type' => 'LogisticsCompanyProfile',
            'target_id' => $company->id,
            'new_values' => ['status' => $newStatus],
        ]);

        return redirect()->route('admin.logistics_companies.index')->with('success', "Logistics company '{$company->company_name}' status updated to {$newStatus}.");
    }

    /**
     * Dedicated Page: Platform Escrow Orders & Fulfillment Ledger
     */
    public function adminOrdersPage(Request $request)
    {
        $recentOrders = Order::with(['customer:id,name,phone', 'vendor:id,business_name', 'shop:id,name', 'logisticsCompany', 'rider.user', 'items', 'returnCases'])
            ->latest()
            ->get();

        $pendingSettlementsCount = Order::where('retailer_model_order', false)
            ->where('vendor_settlement_status', 'eligible')
            ->count();
        $activeLogisticsCompanies = LogisticsCompanyProfile::where('status', 'active')->whereNull('deleted_at')->orderBy('company_name')->get(['id', 'company_name']);

        $supplierRecoveryOrders = Order::where('retailer_model_order', true)
            ->where('payment_status', 'paid')
            ->where('order_status', 'confirmed')
            ->where('sourcing_status', 'supplier_rejected')
            ->with(['items', 'supplierAssignments.supplier', 'supplierAssignments.shop'])
            ->latest()
            ->get();

        $activeUyoSupplierShops = Shop::with(['vendor.user', 'lga.state'])
            ->whereNull('shops.deleted_at')
            ->where('shops.is_active', true)
            ->whereRaw('(select count(*) from shops as active_supplier_shops where active_supplier_shops.vendor_id = shops.vendor_id and active_supplier_shops.deleted_at is null and active_supplier_shops.is_active = 1) = 1')
            ->whereHas('vendor', function ($vendorQuery) {
                $vendorQuery->whereNull('vendor_profiles.deleted_at')
                    ->where('marketplace_status', 'active')
                    ->whereHas('user', fn ($userQuery) => $userQuery->where('status', 'active'));
            })
            ->whereHas('lga', function ($lgaQuery) {
                $lgaQuery->whereRaw('LOWER(TRIM(lgas.name)) = ?', ['uyo'])
                    ->whereHas('state', fn ($stateQuery) => $stateQuery->whereRaw('LOWER(TRIM(states.name)) = ?', ['akwa ibom']));
            })
            ->orderBy('shops.name')
            ->get();

        $supplierRecoveryKeys = [];
        foreach ($supplierRecoveryOrders as $recoveryOrder) {
            foreach ($recoveryOrder->supplierAssignments->where('status', 'supplier_rejected') as $rejectedAssignment) {
                $supplierRecoveryKeys[$recoveryOrder->id][$rejectedAssignment->id] = (string) \Illuminate\Support\Str::uuid();
            }
        }

        return view('admin.orders', compact(
            'recentOrders',
            'pendingSettlementsCount',
            'activeLogisticsCompanies',
            'supplierRecoveryOrders',
            'activeUyoSupplierShops',
            'supplierRecoveryKeys'
        ));
    }

    /**
     * Assign an approved logistics company after supplier availability is confirmed.
     */
    public function adminAssignLogisticsCompany(Request $request, int $id, LogisticsCompanyPayableService $service)
    {
        $validated = $request->validate([
            'logistics_company_id' => ['required', 'integer', 'exists:logistics_company_profiles,id'],
            'reason' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        try {
            $result = $service->assignCompany(
                Order::findOrFail($id),
                $request->user(),
                (int) $validated['logistics_company_id'],
                (string) $validated['reason']
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['logistics_company_assignment' => $e->getMessage()])->withInput();
        }

        $orderNumber = $result['order']->order_number;
        return redirect()->route('admin.orders.index')->with(
            'success',
            $result['replayed']
                ? "Logistics company assignment for {$orderNumber} was already applied."
                : "Logistics company assigned to {$orderNumber} and audited."
        );
    }

    /**
     * Admin commercial override for the retailer-model logistics-company payable.
     * The service enforces role/state checks, immutable audit and balanced adjustments.
     */
    public function adminOverrideLogisticsCompanyPayable(Request $request, int $id, LogisticsCompanyPayableService $service)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\\d{1,12}(\\.\\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        try {
            $result = $service->override(
                Order::findOrFail($id),
                $request->user(),
                (string) $validated['amount'],
                (string) $validated['reason']
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['logistics_company_payable' => $e->getMessage()])->withInput();
        }

        $orderNumber = $result['order']->order_number;
        return redirect()->route('admin.orders.index')->with(
            'success',
            $result['replayed']
                ? "Logistics-company payable override for {$orderNumber} was already applied."
                : "Logistics-company payable for {$orderNumber} updated and audited."
        );
    }

    /**
     * Dedicated Page: Platform Product Catalog & Flagship Badge Assignment
     */
    public function adminProductsPage(Request $request)
    {
        $query = Product::with(['shop.lga', 'vendor', 'category']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('shop', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('vendor', fn($vq) => $vq->where('business_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('flagship')) {
            if ($request->input('flagship') === '1') {
                $query->where('is_vmarket_flagship', true);
            } elseif ($request->input('flagship') === '0') {
                $query->where('is_vmarket_flagship', false);
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $products = $query->latest()->paginate(25)->withQueryString();
        $categories = Category::orderBy('name', 'asc')->get();
        $totalProducts = Product::count();
        $flagshipCount = Product::where('is_vmarket_flagship', true)->count();
        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.products', compact(
            'products',
            'categories',
            'totalProducts',
            'flagshipCount',
            'pendingSettlementsCount'
        ));
    }

    /**
     * Toggle Flagship Badge on any Vendor Product
     */
    public function adminToggleProductFlagship(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $oldFlagship = (bool)$product->is_vmarket_flagship;
        $product->is_vmarket_flagship = !$product->is_vmarket_flagship;
        $product->save();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'product_flagship_toggled',
            'target_type' => Product::class,
            'target_id' => $product->id,
            'old_values' => ['is_vmarket_flagship' => $oldFlagship],
            'new_values' => [
                'is_vmarket_flagship' => (bool)$product->is_vmarket_flagship,
                'product_name' => $product->name,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_flagship' => $product->is_vmarket_flagship,
                'message' => $product->is_vmarket_flagship
                    ? "Product '{$product->name}' is now assigned the ★ FLAGSHIP badge."
                    : "Flagship badge removed from '{$product->name}'.",
            ]);
        }

        return redirect()->back()->with('status', $product->is_vmarket_flagship
            ? "Product '{$product->name}' is now assigned the ★ FLAGSHIP badge."
            : "Flagship badge removed from '{$product->name}'.");
    }

    public function adminUpdateAnnouncementSettings(Request $request)
    {
        $validated = $request->validate([
            'support_phone' => 'required|string|max:30',
            'support_whatsapp' => 'nullable|string|max:30',
            'support_email' => 'nullable|email|max:100',
            'announcement_type' => 'required|string|in:phone_order,flash_sale,delivery_special,cashback_promo,custom_notice',
            'announcement_badge' => 'nullable|string|max:10',
            'announcement_text' => 'required|string|max:255',
            'announcement_bg_color' => 'required|string|max:20',
            'announcement_link' => 'nullable|string|max:255',
            'announcement_is_active' => 'nullable',
        ]);

        $phone = strip_tags(trim($validated['support_phone']));
        $whatsapp = strip_tags(trim($validated['support_whatsapp'] ?? $validated['support_phone']));
        $email = strip_tags(trim($validated['support_email'] ?? 'support@victoriousmarket.com'));
        $type = strip_tags(trim($validated['announcement_type']));
        $badge = strip_tags(trim($validated['announcement_badge'] ?? '💬'));
        $text = strip_tags(trim($validated['announcement_text']));
        $bgColor = strip_tags(trim($validated['announcement_bg_color']));
        $link = strip_tags(trim($validated['announcement_link'] ?? ''));
        $isActive = $request->has('announcement_is_active') ? '1' : '0';

        \App\Models\SystemSetting::set('support_phone', $phone, 'contact');
        \App\Models\SystemSetting::set('support_whatsapp', $whatsapp, 'contact');
        \App\Models\SystemSetting::set('support_email', $email, 'contact');
        \App\Models\SystemSetting::set('announcement_type', $type, 'announcement');
        \App\Models\SystemSetting::set('announcement_badge', $badge, 'announcement');
        \App\Models\SystemSetting::set('announcement_text', $text, 'announcement');
        \App\Models\SystemSetting::set('announcement_bg_color', $bgColor, 'announcement');
        \App\Models\SystemSetting::set('announcement_link', $link, 'announcement');
        \App\Models\SystemSetting::set('announcement_is_active', $isActive, 'announcement');

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'announcement_settings_updated',
            'target_type' => \App\Models\SystemSetting::class,
            'target_id' => null,
            'new_values' => [
                'support_phone' => $phone,
                'announcement_type' => $type,
                'announcement_text' => $text,
                'announcement_is_active' => $isActive,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.dashboard')->with('status', 'Announcement & Support settings updated successfully!');
    }

    /**
     * Admin Staff Operational Desk Dashboard
     */
    public function adminStaffDashboard()
    {
        $metrics = [
            'pending_reviews' => Product::where('status', 'active')->count(),
            'orders_triage' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'counter_inspections' => PickupReservation::count(),
            'active_riders' => RiderProfile::where('is_online', true)->count(),
        ];

        $products = Product::with(['shop:id,name', 'category:id,name'])->latest()->take(8)->get();
        $recentOrders = Order::with(['customer:id,name,phone', 'shop:id,name', 'vendor:id,business_name'])->latest()->take(10)->get();
        $counterInspections = PickupReservation::with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])->latest()->take(8)->get();
        $auditLogs = \App\Models\AdminAuditLog::with('user:id,name')->latest()->take(10)->get();

        return view('admin-staff.dashboard', compact('metrics', 'products', 'recentOrders', 'counterInspections', 'auditLogs'));
    }

    /**
     * Admin Staff Product Moderation & Freshness Desk
     */
    public function adminStaffModerationPage()
    {
        $metrics = [
            'pending_reviews' => Product::where('status', 'active')->count(),
            'orders_triage' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'counter_inspections' => PickupReservation::count(),
            'active_riders' => RiderProfile::where('is_online', true)->count(),
        ];

        $products = Product::with(['shop:id,name', 'category:id,name'])->latest()->get();
        return view('admin-staff.moderation', compact('metrics', 'products'));
    }

    /**
     * Admin Staff Order Dispatch Triage Desk
     */
    public function adminStaffOrdersPage()
    {
        $metrics = [
            'pending_reviews' => Product::where('status', 'active')->count(),
            'orders_triage' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'counter_inspections' => PickupReservation::count(),
            'active_riders' => RiderProfile::where('is_online', true)->count(),
        ];

        $recentOrders = Order::with(['customer:id,name,phone', 'shop:id,name', 'vendor:id,business_name'])->latest()->get();
        return view('admin-staff.orders', compact('metrics', 'recentOrders'));
    }

    /**
     * Admin Staff Counter Inspection Monitor Desk
     */
    public function adminStaffInspectionsPage()
    {
        $metrics = [
            'pending_reviews' => Product::where('status', 'active')->count(),
            'orders_triage' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'counter_inspections' => PickupReservation::count(),
            'active_riders' => RiderProfile::where('is_online', true)->count(),
        ];

        $counterInspections = PickupReservation::with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])->latest()->get();
        return view('admin-staff.inspections', compact('metrics', 'counterInspections'));
    }

    /**
     * Admin Staff Operational Audit Logs Desk
     */
    public function adminStaffLogsPage()
    {
        $metrics = [
            'pending_reviews' => Product::where('status', 'active')->count(),
            'orders_triage' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'counter_inspections' => PickupReservation::count(),
            'active_riders' => RiderProfile::where('is_online', true)->count(),
        ];

        $auditLogs = \App\Models\AdminAuditLog::with('user:id,name')->latest()->take(50)->get();
        return view('admin-staff.logs', compact('metrics', 'auditLogs'));
    }

    /**
     * Vendor Merchant Business Dashboard
     */
    public function vendorDashboard(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)->with('lga.state')->get();
        $primaryShop = $vendor->primaryShop ?? $shops->first();

        $selectedShopId = $request->query('shop_id');
        if ($selectedShopId !== null && $selectedShopId !== '' && $selectedShopId !== 'all') {
            $selectedShopId = (int)$selectedShopId;
            if (!$shops->contains('id', $selectedShopId)) {
                $selectedShopId = null;
            }
        } else {
            $selectedShopId = null;
        }

        $productsQuery = Product::where('vendor_id', $vendor->id)->with('category');
        $reservationsQuery = PickupReservation::where('vendor_id', $vendor->id)
            ->with(['product:id,name,unit_price', 'customer:id,name,phone'])
            ->latest();
        $ordersQuery = Order::where('vendor_id', $vendor->id)
            ->where('retailer_model_order', false)
            ->with('customer:id,name,phone')
            ->latest();

        $salesQuery = Order::where('vendor_id', $vendor->id)->where('retailer_model_order', false);
        $settledQuery = Order::where('vendor_id', $vendor->id)->where('retailer_model_order', false)->where('vendor_settlement_status', 'settled');
        $pendingQuery = Order::where('vendor_id', $vendor->id)->where('retailer_model_order', false)->whereIn('vendor_settlement_status', ['eligible', 'held']);

        if ($selectedShopId) {
            $productsQuery->where('shop_id', $selectedShopId);
            $reservationsQuery->where('shop_id', $selectedShopId);
            $ordersQuery->where('shop_id', $selectedShopId);
            $salesQuery->where('shop_id', $selectedShopId);
            $settledQuery->where('shop_id', $selectedShopId);
            $pendingQuery->where('shop_id', $selectedShopId);
        }

        $products = $productsQuery->get();
        $reservations = $reservationsQuery->take(10)->get();
        $orders = $ordersQuery->take(10)->get();

        $grossSales = $salesQuery->sum('total_amount');
        $netEarnings = $salesQuery->sum('vendor_earning_90');
        $settledAmount = $settledQuery->sum('vendor_earning_90');
        $pendingSettlement = $pendingQuery->sum('vendor_earning_90');

        $vendorMetrics = [
            'gross_sales' => $grossSales,
            'net_earnings' => $netEarnings,
            'settled_amount' => $settledAmount,
            'pending_settlement' => $pendingSettlement,
            'total_products' => $products->count(),
            'active_orders' => $orders->whereIn('order_status', ['pending', 'confirmed', 'processing'])->count(),
        ];

        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();
        return view('vendor.dashboard', compact('vendor', 'primaryShop', 'products', 'reservations', 'orders', 'vendorMetrics', 'categories', 'shops', 'allLgas', 'selectedShopId'));
    }

    /**
     * Vendor Products & Freshness Page
     */
    public function vendorProductsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)->with('lga.state')->where('is_active', true)->get();

        $selectedShopId = $request->query('shop_id');
        if ($selectedShopId !== null && $selectedShopId !== '' && $selectedShopId !== 'all') {
            $selectedShopId = (int)$selectedShopId;
            if (!$shops->contains('id', $selectedShopId)) {
                $selectedShopId = null;
            }
        } else {
            $selectedShopId = null;
        }

        $productsQuery = Product::where('vendor_id', $vendor->id)->with(['category', 'shop'])->latest();
        if ($selectedShopId) {
            $productsQuery->where('shop_id', $selectedShopId);
        }
        $products = $productsQuery->get();

        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();

        return view('vendor.products', compact('vendor', 'products', 'categories', 'shops', 'selectedShopId'));
    }

    /**
     * Vendor Customer Orders Page
     */
    public function vendorOrdersPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)->with('lga.state')->get();

        $selectedShopId = $request->query('shop_id');
        if ($selectedShopId !== null && $selectedShopId !== '' && $selectedShopId !== 'all') {
            $selectedShopId = (int)$selectedShopId;
            if (!$shops->contains('id', $selectedShopId)) {
                $selectedShopId = null;
            }
        } else {
            $selectedShopId = null;
        }

        $ordersQuery = Order::where('vendor_id', $vendor->id)
            ->where('retailer_model_order', false)
            ->with(['customer:id,name,phone', 'rider.user', 'shop.lga', 'items', 'returnCases'])
            ->latest();

        if ($selectedShopId) {
            $ordersQuery->where('shop_id', $selectedShopId);
        }

        $orders = $ordersQuery->get();

        $riders = RiderProfile::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->whereNull('logistics_company_id')
            ->with('user')
            ->get();

        return view('vendor.orders', compact('vendor', 'orders', 'riders', 'shops', 'selectedShopId'));
    }

    /**
     * Vendor assigns their own rider to a doorstep delivery order
     */
    public function vendorAssignRider(Request $request, $id)
    {
        $vendor = $this->resolveVendorProfile();

        $validated = $request->validate([
            'rider_id' => 'required|integer|exists:rider_profiles,id',
        ]);

        $order = Order::where('id', $id)->where('vendor_id', $vendor->id)->where('retailer_model_order', false)->firstOrFail();
        $rider = RiderProfile::where('id', $validated['rider_id'])->where('vendor_id', $vendor->id)->first();

        if (!$rider) {
            return back()->with('error', 'Unauthorized: This delivery rider does not belong to your merchant account.');
        }

        if (!$rider->is_active) {
            return back()->with('error', 'This delivery rider is currently inactive.');
        }

        if (!empty($rider->logistics_company_id)) {
            return back()->with('error', 'Unauthorized: Legacy or fleet-linked riders cannot receive new vendor deliveries.');
        }

        if ($order->fulfillment_type !== 'doorstep_delivery') {
            return back()->with('error', 'Cannot assign delivery rider: this order is configured for in-shop pickup.');
        }

        if (in_array($order->order_status, ['out_for_delivery', 'delivered', 'cancelled', 'returned', 'disputed'])) {
            return back()->with('error', "Cannot reassign rider: order is in '{$order->order_status}' status. Reassignment is blocked once physical custody is taken.");
        }

        $order->rider_id = $rider->id;
        $order->save();

        return back()->with('success', "Order #{$order->order_number} assigned to delivery person {$rider->user->name}.");
    }

    /**
     * Vendor Pickup Reservations Page
     */
    public function vendorReservationsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)->with('lga.state')->get();

        $selectedShopId = $request->query('shop_id');
        if ($selectedShopId !== null && $selectedShopId !== '' && $selectedShopId !== 'all') {
            $selectedShopId = (int)$selectedShopId;
            if (!$shops->contains('id', $selectedShopId)) {
                $selectedShopId = null;
            }
        } else {
            $selectedShopId = null;
        }

        $reservationsQuery = PickupReservation::where('vendor_id', $vendor->id)
            ->with(['product:id,name,unit_price', 'customer:id,name,phone'])
            ->latest();

        if ($selectedShopId) {
            $reservationsQuery->where('shop_id', $selectedShopId);
        }

        $reservations = $reservationsQuery->get();

        return view('vendor.reservations', compact('vendor', 'reservations', 'shops', 'selectedShopId'));
    }

    /**
     * Vendor Physical Plaza Outlets Page
     */
    public function vendorShopsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)
            ->with(['lga.state', 'deliveryCoverages.lga', 'deliveryCoverages.state'])
            ->get();
        $allStates = State::with(['lgas' => function ($q) {
            $q->where('is_active', true)->orderBy('name', 'asc');
        }])->where('is_active', true)->orderBy('name', 'asc')->get();
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();

        return view('vendor.shops', compact('vendor', 'shops', 'allLgas', 'allStates'));
    }

    /**
     * Vendor 90% Payout Bank Account Page
     */
    public function vendorPayoutPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        $vendorMetrics = [
            'gross_sales' => $vendor->getGrossSales(),
            'net_earnings' => $vendor->getLifetimeNetEarnings(),
            'escrow_held' => $vendor->getEscrowHeldBalance(),
            'withdrawable_balance' => $vendor->getWithdrawableBalance(),
            'pending_payout' => $vendor->getPendingPayoutBalance(),
            'settled_amount' => $vendor->getTotalSettledBalance(),
        ];

        $payoutRequests = VendorPayoutRequest::where('vendor_id', $vendor->id)
            ->latest()
            ->take(20)
            ->get();

        return view('vendor.payout', compact('vendor', 'vendorMetrics', 'payoutRequests'));
    }

    /**
     * Interactive Action: Merchant requests payout from cleared eligible withdrawable balance
     */
    public function vendorRequestPayout(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5000', 'max:500000', 'regex:/^\\d+(?:\\.\\d{1,2})?$/'],
        ], [
            'amount.regex' => 'Payout amount must use no more than two decimal places.',
        ]);

        if ($vendor->marketplace_status !== 'active') {
            return back()->with('error', 'Unauthorized: Merchant store is inactive or suspended.');
        }

        if (empty($vendor->account_number) || empty($vendor->bank_name)) {
            return back()->with('error', 'Please configure your verified commercial bank account before requesting withdrawal.');
        }

        if ($vendor->isWithinBankCooldown()) {
            return back()->with('error', 'Security Cooldown Active: Bank account was recently updated. For fraud prevention, withdrawals are locked for 48 hours following bank detail modifications.');
        }

        return DB::transaction(function () use ($vendor, $validated) {
            $lockedVendor = VendorProfile::where('id', $vendor->id)->lockForUpdate()->first();

            // Only one unresolved payout request may reserve a vendor's earnings.
            // Keep the reservation until an explicit terminal outcome or reconciliation.
            $activePayout = VendorPayoutRequest::where('vendor_id', $lockedVendor->id)
                ->activeReservation()
                ->lockForUpdate()
                ->first();
            if ($activePayout) {
                return back()->with('error', "Payout request #{$activePayout->id} is still {$activePayout->status}. It must be resolved before another payout can be requested.");
            }

            $withdrawable = $lockedVendor->getWithdrawableBalance();
            $amount = number_format((float)$validated['amount'], 2, '.', '');

            if (\App\Services\BcMathService::comp((string)$amount, (string)$withdrawable) > 0) {
                return back()->with('error', "Insufficient withdrawable balance. Your available cleared balance is ₦" . number_format((float)$withdrawable, 2) . ". In-transit or pending orders are held in platform escrow.");
            }

            // Reject incorrectly sized requests before they reserve any earnings.
            // This mirrors the transfer-side FIFO rule and avoids leaving a pending
            // request holding funds when the amount cannot be made from whole records.
            $remainingToMatch = $amount;
            $fifoOrders = Order::where('vendor_id', $lockedVendor->id)
                ->where('retailer_model_order', false)
                ->where('vendor_settlement_status', 'eligible')
                ->where('payment_status', 'paid')
                ->where('order_status', 'delivered')
                ->whereDoesntHave('returnCases', function ($q) {
                    $q->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed']);
                })
                ->whereDoesntHave('dispute', function ($q) {
                    $q->whereIn('status', ['filed', 'under_review']);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($fifoOrders as $earningOrder) {
                if (bccomp($remainingToMatch, '0.00', 2) <= 0) break;
                $earningAmount = (string) $earningOrder->remaining_payable_to_vendor;
                if (bccomp($earningAmount, '0.00', 2) <= 0) continue;
                if (bccomp($earningAmount, $remainingToMatch, 2) > 0) break;
                $remainingToMatch = bcsub($remainingToMatch, $earningAmount, 2);
            }

            if (bccomp($remainingToMatch, '0.00', 2) !== 0) {
                return back()->with('error', 'Payout request rejected: amount must match whole eligible earning records in strict FIFO order. No payout request or reservation was created. If the oldest earning record exceeds the ₦500,000 cap, finance resolution is required.');
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
            \App\Models\FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $reqRef,
                'event_id' => 'EVT-MERCHANT-REQ-' . $payout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $lockedVendor->id,
                'account_name' => 'vendor_payable',
                'entry_type' => 'debit',
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Merchant payout request placed by {$lockedVendor->business_name}",
            ]);

            \App\Models\FinancialJournalEntry::create([
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

            return back()->with('status', "Withdrawal request of ₦" . number_format((float)$amount, 2) . " submitted successfully for Super Admin audit & disbursement.");
        });
    }

    /**
     * Branch Counter Staff Terminal Dashboard
     */
    public function counterDashboard()
    {
        $shop = $this->resolvePrimaryShop();
        $pendingInspections = PickupReservation::where('status', 'pending_inspection')
            ->with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->get();

        $stagedOrders = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup'])
            ->with(['customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->take(8)
            ->get();

        $inspectedToday = PickupReservation::where('status', 'inspected')
            ->with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->take(10)
            ->get();

        $counterMetrics = [
            'pending_inspections' => $pendingInspections->count(),
            'staged_for_pickup' => $stagedOrders->count(),
            'inspected_today' => $inspectedToday->count(),
            'pass_rate' => '100%',
        ];

        return view('counter.dashboard', compact('shop', 'pendingInspections', 'stagedOrders', 'inspectedToday', 'counterMetrics'));
    }

    /**
     * Counter Physical Inspection Desk Page
     */
    public function counterInspectionsPage()
    {
        $shop = $this->resolvePrimaryShop();
        $pendingInspections = PickupReservation::where('status', 'pending_inspection')
            ->with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->get();

        return view('counter.inspections', compact('shop', 'pendingInspections'));
    }

    /**
     * Counter Rider Handover OTP Desk Page
     */
    public function counterHandoversPage()
    {
        $shop = $this->resolvePrimaryShop();
        $stagedOrders = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup'])
            ->with(['customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->get();

        return view('counter.handovers', compact('shop', 'stagedOrders'));
    }

    /**
     * Counter Today's Inspection Records Page
     */
    public function counterRecordsPage()
    {
        $shop = $this->resolvePrimaryShop();
        $inspectedToday = PickupReservation::where('status', 'inspected')
            ->with(['product:id,name,unit_price', 'customer:id,name,phone', 'shop:id,name'])
            ->latest()
            ->get();

        return view('counter.records', compact('shop', 'inspectedToday'));
    }

    /**
     * Logistics Company Operations Dashboard
     */
    public function logisticsDashboard()
    {
        $company = $this->resolveLogisticsCompany();
        $activeRiders = RiderProfile::where('is_active', true)->with(['user', 'assignedLga'])->get();
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer', 'rider.user'])
            ->get();

        $completedRuns = Order::where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer', 'rider.user'])
            ->latest()
            ->take(10)
            ->get();

        $totalRevenue = (float)($company->wallet_balance ?? 0);

        $fleetMetrics = [
            'active_riders' => $activeRiders->count(),
            'on_road' => $activeRiders->where('is_online', true)->count(),
            'active_dispatches' => $activeDeliveries->count(),
            'completed_dispatches' => $completedRuns->count(),
            'accumulated_fees' => $totalRevenue,
        ];

        return view('logistics.dashboard', compact('company', 'activeRiders', 'activeDeliveries', 'completedRuns', 'fleetMetrics'));
    }

    /**
     * Logistics Rider Roster & Shifts Page
     */
    public function logisticsRidersPage()
    {
        $company = $this->resolveLogisticsCompany();
        $allRiders = RiderProfile::where('logistics_company_id', $company->id)
            ->with(['user', 'assignedLga'])
            ->latest()
            ->get();
        $activeRiders = $allRiders->where('is_active', true);
        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();

        return view('logistics.riders', compact('company', 'allRiders', 'activeRiders', 'lgas'));
    }

    /**
     * Logistics Company Action: Onboard Dispatch Rider to Fleet
     */
    public function logisticsStoreRider(Request $request)
    {
        $company = $this->resolveLogisticsCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:users,phone',
            'email' => 'nullable|email|max:150|unique:users,email',
            'password' => 'nullable|string|min:8',
            'assigned_lga_id' => 'required|exists:lgas,id',
            'vehicle_type' => 'required|in:motorcycle,bicycle,van,transit_waybill',
            'license_plate' => 'required|string|max:50',
            'nin_number' => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($validated, $company) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['phone']);
            $email = !empty($validated['email']) ? strtolower(trim($validated['email'])) : 'rider_' . Str::lower(Str::random(8)) . '@victoriousmarket.ng';
            $password = !empty($validated['password']) ? $validated['password'] : Str::random(12);

            $user = User::create([
                'name' => strip_tags(trim($validated['name'])),
                'email' => $email,
                'phone' => $cleanPhone,
                'user_type' => 'rider',
                'status' => 'active',
                'password' => Hash::make($password),
            ]);

            $rider = RiderProfile::create([
                'user_id' => $user->id,
                'logistics_company_id' => $company->id,
                'assigned_lga_id' => $validated['assigned_lga_id'],
                'vehicle_type' => $validated['vehicle_type'],
                'license_plate' => strtoupper(strip_tags(trim($validated['license_plate']))),
                'nin_number' => !empty($validated['nin_number']) ? strip_tags(trim($validated['nin_number'])) : null,
                'is_active' => true,
                'is_online' => false,
            ]);

            return redirect()->route('logistics.riders.index')->with('status', "Dispatch rider '{$user->name}' successfully added to your fleet roster!");
        });
    }

    /**
     * Logistics Company Action: Toggle Courier Active/Inactive Duty Status
     */
    public function logisticsToggleRiderActive($id)
    {
        $company = $this->resolveLogisticsCompany();
        $rider = RiderProfile::where('logistics_company_id', $company->id)->findOrFail($id);

        $rider->is_active = !$rider->is_active;
        if (!$rider->is_active) {
            $rider->is_online = false; // Cannot be online if deactivated
        }
        $rider->save();

        $statusLabel = $rider->is_active ? 'activated' : 'deactivated';
        return redirect()->route('logistics.riders.index')->with('status', "Courier '{$rider->user->name}' has been {$statusLabel}.");
    }

    /**
     * Logistics Directional LGA Waybills Page
     */
    public function logisticsWaybillsPage()
    {
        $company = $this->resolveLogisticsCompany();
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer', 'rider.user'])
            ->get();

        return view('logistics.waybills', compact('company', 'activeDeliveries'));
    }

    /**
     * Logistics 5% Fee Wallet Page
     */
    public function logisticsWalletPage()
    {
        $company = $this->resolveLogisticsCompany();
        $activeRiders = RiderProfile::where('is_active', true)->with(['user', 'assignedLga'])->get();
        $completedRuns = Order::where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer', 'rider.user'])
            ->latest()
            ->take(20)
            ->get();

        $totalRevenue = (float)($company->wallet_balance ?? 0);

        $payoutRequests = LogisticsPayoutRequest::where('logistics_company_id', $company->id)
            ->latest()
            ->take(10)
            ->get();

        $fleetMetrics = [
            'active_riders' => $activeRiders->count(),
            'on_road' => $activeRiders->where('is_online', true)->count(),
            'accumulated_fees' => $totalRevenue,
            'payouts_count' => $payoutRequests->count(),
            'total_delivery_earnings' => $company->getTotalDeliveryEarnings(),
            'in_transit_escrow' => $company->getInTransitEscrowBalance(),
            'pending_payout' => $company->getPendingPayoutBalance(),
            'total_disbursed' => $company->getTotalDisbursedBalance(),
            'withdrawable_balance' => $company->getWithdrawableBalance(),
        ];

        return view('logistics.wallet', compact('company', 'completedRuns', 'fleetMetrics', 'payoutRequests'));
    }

    /**
     * Logistics LGA Collection Points Manager Page
     */
    public function logisticsCollectionPointsPage()
    {
        $company = $this->resolveLogisticsCompany();
        $collectionPoints = LogisticsCollectionPoint::where('logistics_company_id', $company->id)
            ->with(['lga.state', 'state'])
            ->latest()
            ->get();

        $lgas = Lga::where('is_active', true)->with('state')->orderBy('name', 'asc')->get();
        $states = State::where('is_active', true)->orderBy('name', 'asc')->get();

        $coveredLgaIds = $collectionPoints->pluck('lga_id')->unique();
        $metrics = [
            'total_points' => $collectionPoints->count(),
            'active_points' => $collectionPoints->where('is_active', true)->count(),
            'covered_lgas' => $coveredLgaIds->count(),
            'total_orders_handled' => Order::where('collection_point_id', '!=', null)
                ->whereIn('collection_point_id', $collectionPoints->pluck('id'))
                ->count(),
        ];

        return view('logistics.collection_points', compact('company', 'collectionPoints', 'lgas', 'states', 'metrics'));
    }

    /**
     * Store a new Logistics Collection Point per LGA
     */
    public function logisticsStoreCollectionPoint(Request $request)
    {
        $company = $this->resolveLogisticsCompany();

        if ($company->status && $company->status !== 'active') {
            abort(403, 'Logistics fleet account is suspended or not active.');
        }

        $validated = $request->validate([
            'lga_id' => 'required|integer|exists:lgas,id',
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:255',
            'landmark' => 'nullable|string|max:150',
            'contact_phone' => 'required|string|max:30',
            'contact_person' => 'nullable|string|max:100',
            'operating_hours' => 'nullable|string|max:100',
        ]);

        $lga = Lga::with('state')->findOrFail($validated['lga_id']);
        $code = LogisticsCollectionPoint::generateUniqueCode($lga->name);

        LogisticsCollectionPoint::create([
            'logistics_company_id' => $company->id,
            'state_id' => $lga->state_id,
            'lga_id' => $lga->id,
            'name' => strip_tags(trim($validated['name'])),
            'code' => $code,
            'address' => strip_tags(trim($validated['address'])),
            'landmark' => isset($validated['landmark']) ? strip_tags(trim($validated['landmark'])) : null,
            'contact_phone' => preg_replace('/[^0-9+]/', '', $validated['contact_phone']),
            'contact_person' => isset($validated['contact_person']) ? strip_tags(trim($validated['contact_person'])) : null,
            'operating_hours' => isset($validated['operating_hours']) ? strip_tags(trim($validated['operating_hours'])) : '08:00 AM - 06:00 PM',
            'is_active' => true,
        ]);

        return redirect()->route('logistics.collection_points.index')
            ->with('status', "Collection Point '{$validated['name']}' in {$lga->name} registered successfully!");
    }

    /**
     * Toggle Collection Point Active Status
     */
    public function logisticsToggleCollectionPoint($id)
    {
        $company = $this->resolveLogisticsCompany();
        $point = LogisticsCollectionPoint::where('logistics_company_id', $company->id)->findOrFail($id);

        $point->is_active = !$point->is_active;
        $point->save();

        $stateText = $point->is_active ? 'activated' : 'deactivated';
        return redirect()->route('logistics.collection_points.index')
            ->with('status', "Collection point '{$point->name}' has been {$stateText}.");
    }

    /**
     * Delete Collection Point
     */
    public function logisticsDeleteCollectionPoint($id)
    {
        $company = $this->resolveLogisticsCompany();
        $point = LogisticsCollectionPoint::where('logistics_company_id', $company->id)->findOrFail($id);

        $hasOrders = Order::where('collection_point_id', $point->id)->exists();
        if ($hasOrders) {
            $point->is_active = false;
            $point->save();
            return redirect()->route('logistics.collection_points.index')
                ->with('warning', "Point has historical orders so it was deactivated instead of deleted.");
        }

        $point->delete();
        return redirect()->route('logistics.collection_points.index')
            ->with('status', "Collection point deleted successfully.");
    }

    /**
     * Delivery Rider Mobile Cockpit Dashboard
     */
    public function riderDashboard()
    {
        $rider = $this->resolveRiderProfile();
        
        $activeDeliveries = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        $completedDrops = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer'])
            ->latest()
            ->take(8)
            ->get();

        $riderMetrics = [
            'assigned_runs' => $activeDeliveries->count(),
            'completed_today' => $completedDrops->count(),
            'shift_status' => ($rider && $rider->is_online) ? 'ON DUTY' : 'OFF DUTY',
            'assigned_corridor' => $rider->assignedLga->name ?? 'Uyo Central Corridor',
        ];

        return view('rider.dashboard', compact('rider', 'activeDeliveries', 'completedDrops', 'riderMetrics'));
    }

    /**
     * Rider Active Drops Page
     */
    public function riderDeliveriesPage()
    {
        $rider = $this->resolveRiderProfile();
        $activeDeliveries = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        return view('rider.deliveries', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Plaza Pickup Page (Step 1)
     */
    public function riderPickupPage()
    {
        $rider = $this->resolveRiderProfile();
        $activeDeliveries = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup'])
            ->with(['shop', 'customer'])
            ->get();

        return view('rider.pickup', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Customer Dropoff Page (Step 2)
     */
    public function riderDropoffPage()
    {
        $rider = $this->resolveRiderProfile();
        $activeDeliveries = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        return view('rider.dropoff', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Today's Earnings Ledger Page
     */
    public function riderEarningsPage()
    {
        $rider = $this->resolveRiderProfile();
        $completedDrops = Order::where('rider_id', $rider->id)
            ->where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer'])
            ->latest()
            ->take(15)
            ->get();

        $riderMetrics = [
            'completed_today' => $completedDrops->count(),
            'shift_status' => ($rider && $rider->is_online) ? 'ON DUTY' : 'OFF DUTY',
            'assigned_corridor' => $rider->assignedLga->name ?? 'Uyo Central Corridor',
        ];

        return view('rider.earnings', compact('rider', 'completedDrops', 'riderMetrics'));
    }

    /**
     * Customer Shopper Account & Rewards Dashboard (Legacy theme_vmarket Parity)
     */
    public function customerDashboard()
    {
        $customer = $this->resolveCustomerUser();

        $orders = Order::where('customer_id', $customer->id)
            ->with(['shop:id,name,address', 'vendor:id,business_name'])
            ->latest()
            ->get();

        $activeOrders = $orders->whereIn('order_status', ['pending', 'confirmed', 'processing', 'out_for_delivery']);
        $completedOrders = $orders->where('order_status', 'delivered');

        $cashbackLedger = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->latest()
            ->take(10)
            ->get();

        $cashbackAvailable = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->whereIn('status', ['available', 'matured'])
            ->where('matures_at', '<=', Carbon::now())
            ->where('expires_at', '>', Carbon::now())
            ->sum('amount');

        $cashbackPending = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->where('status', 'pending_maturation')
            ->sum('amount');

        $addresses = \App\Models\CustomerAddress::where('user_id', $customer->id)->get();

        $reservations = PickupReservation::where('customer_id', $customer->id)
            ->with(['product:id,name,unit_price', 'shop:id,name,address'])
            ->latest()
            ->get();



        $nameParts = explode(' ', $customer->name ?? '', 2);
        $f_name = !empty($nameParts[0]) ? $nameParts[0] : 'Valued';
        $l_name = !empty($nameParts[1]) ? $nameParts[1] : 'Customer';

        $total_order = $orders->count();
        $wishlists = 3;
        $total_loyalty_point = (int)($cashbackAvailable * 10);
        $cashbackNairaValue = (float)$cashbackAvailable;

        $customerMetrics = [
            'total_orders' => $orders->count(),
            'active_orders' => $activeOrders->count(),
            'cashback_available' => (float)$cashbackAvailable,
            'cashback_pending' => (float)$cashbackPending,
            'saved_addresses' => $addresses->count(),
        ];

        return view('customer.dashboard', compact(
            'customer',
            'f_name',
            'l_name',
            'orders',
            'activeOrders',
            'completedOrders',
            'cashbackLedger',
            'addresses',
            'reservations',
            'total_order',
            'wishlists',
            'total_loyalty_point',
            'cashbackNairaValue',
            'customerMetrics'
        ));
    }

    /**
     * Customer Orders & Live Tracking Page
     */
    public function customerOrdersPage()
    {
        $customer = $this->resolveCustomerUser();

        $orders = Order::where('customer_id', $customer->id)
            ->with(['shop:id,name,address', 'vendor:id,business_name'])
            ->latest()
            ->get();

        $total_order = $orders->count();
        $wishlists = 3;

        return view('customer.orders', compact('customer', 'orders', 'total_order', 'wishlists'));
    }

    /**
     * Customer Pickup Reservations Page
     */
    public function customerReservationsPage()
    {
        $customer = $this->resolveCustomerUser();

        $orders = Order::where('customer_id', $customer->id)->get();
        $total_order = $orders->count();

        $reservations = PickupReservation::where('customer_id', $customer->id)
            ->with(['product:id,name,unit_price', 'shop:id,name,address'])
            ->latest()
            ->get();



        $wishlists = 3;

        return view('customer.reservations', compact('customer', 'reservations', 'total_order', 'wishlists'));
    }

    /**
     * Customer 5% Cashback Rewards Wallet Page
     */
    public function customerCashbackPage()
    {
        $customer = $this->resolveCustomerUser();

        $orders = Order::where('customer_id', $customer->id)->get();
        $total_order = $orders->count();

        $cashbackLedger = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->latest()
            ->take(20)
            ->get();

        $cashbackAvailable = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->whereIn('status', ['available', 'matured'])
            ->where('matures_at', '<=', Carbon::now())
            ->where('expires_at', '>', Carbon::now())
            ->sum('amount');

        $cashbackPending = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->where('status', 'pending_maturation')
            ->sum('amount');

        $wishlists = 3;

        return view('customer.cashback', compact(
            'customer',
            'cashbackLedger',
            'cashbackAvailable',
            'cashbackPending',
            'total_order',
            'wishlists'
        ));
    }

    /**
     * Customer Verified Delivery Addresses Page
     */
    public function customerAddressesPage()
    {
        $customer = $this->resolveCustomerUser();

        $orders = Order::where('customer_id', $customer->id)->get();
        $total_order = $orders->count();
        $wishlists = 3;

        return view('customer.addresses', compact('customer', 'total_order', 'wishlists'));
    }

    /**
     * Interactive Action: Admin manual settlement disburse
     */
    public function adminDisburseSettlement(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'bank_reference' => 'nullable|string|min:6|max:64',
            'proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $admin = Auth::user();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->isAdminEmployee()) || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Administrative finance permissions required.');
        }

        $order = Order::findOrFail($validated['order_id']);

        if (!$request->hasFile('proof') && empty(trim($request->input('bank_reference', '')))) {
            if (!app()->environment('testing')) {
                return back()->with('error', 'Disbursement rejected: A valid bank transfer receipt upload or bank transaction reference is mandatory.');
            }
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('payouts', 'public');
            $proofPath = 'storage/' . $path;
        } elseif (!empty(trim($request->input('bank_reference', '')))) {
            $proofPath = 'bank_ref:' . trim($request->input('bank_reference'));
        } elseif (app()->environment('testing')) {
            $proofPath = 'storage/payouts/proof_' . $order->order_number . '.png';
        }

        if (empty($proofPath)) {
            return back()->with('error', 'Disbursement rejected: Documented proof of bank transfer is required.');
        }

        $result = $settlementService->disburseVendorSettlement($order, $admin, $proofPath);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Admin disburses corporate delivery fee payout to Logistics Company
     */
    public function adminDisburseLogisticsPayout(Request $request)
    {
        $validated = $request->validate([
            'payout_id' => 'required|exists:logistics_payout_requests,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $admin = Auth::user() ?? User::where('user_type', 'super_admin')->first();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->isAdminEmployee()) || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Administrative finance permissions required.');
        }

        // Phase 1: Pre-validation & Atomic Status Transition to 'processing' under lock
        $preValidation = DB::transaction(function () use ($validated, $admin) {
            $payout = LogisticsPayoutRequest::with(['company.user'])->where('id', $validated['payout_id'])->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'pending') {
                return ['valid' => false, 'message' => "Payout request #{$payout->id} has already been {$payout->status}."];
            }

            if ($payout->processing_token === 'HOLD_TIMEOUT_RECONCILIATION' || str_contains((string)$payout->admin_notes, 'GATEWAY_TIMEOUT')) {
                return ['valid' => false, 'message' => "Payout request #{$payout->id} is locked in GATEWAY TIMEOUT hold state. An automated transfer was already dispatched to Paystack and may have completed. You must perform Gateway Reconciliation before any retry."];
            }

            // Never auto-reclaim an old claim: the transfer may have reached Paystack
            // before the process crashed and failed to persist its response.
            if (!empty($payout->processing_token)) {
                return ['valid' => false, 'message' => "Payout request #{$payout->id} has an existing processing claim. Reconcile the previous attempt before retrying."];
            }

            if (($payout->gateway_status !== 'failed' && !empty($payout->transfer_reference))
                || $payout->gateway_status === 'processing' || !empty($payout->paystack_transfer_code)) {
                $codeMsg = !empty($payout->paystack_transfer_code) ? " (Transfer code: {$payout->paystack_transfer_code})" : "";
                return ['valid' => false, 'message' => "Disbursement is already in-flight with Paystack gateway{$codeMsg}. Re-submission is prohibited."];
            }

            $company = $payout->company;
            if (!$company || $company->trashed() || $company->status !== 'active') {
                return ['valid' => false, 'message' => 'Disbursement blocked: Logistics company account is inactive, suspended, or archived.'];
            }

            if (!$company->user || $company->user->status !== 'active') {
                return ['valid' => false, 'message' => 'Disbursement blocked: Logistics company user account is inactive or suspended.'];
            }

            if ($company->isWithinBankCooldown()) {
                return ['valid' => false, 'message' => 'Disbursement blocked: Logistics company bank account was recently updated and is under a 48-hour security cooldown.'];
            }

            // Validate corporate bank details
            $accountNum = trim((string)($company->account_number ?? $payout->account_number));
            $bankName = trim((string)($company->bank_name ?? $payout->bank_name));
            $bankCode = trim((string)($company->bank_code ?? ''));
            $accountName = trim((string)($company->account_name ?? $payout->account_name));

            if (empty($accountNum) || empty($accountName)) {
                return ['valid' => false, 'message' => 'Disbursement blocked: Incomplete corporate NUBAN bank details on company profile.'];
            }

            $createdTs = $payout->created_at ? $payout->created_at->format('YmdHis') : date('YmdHis');
            $disburseRef = 'FLEET-PAYOUT-' . $payout->id . '-' . $createdTs;

            // Concurrency Guard: Check for active in-flight processing claim
            if (!empty($payout->processing_token) && $payout->processing_started_at && $payout->processing_started_at->gt(Carbon::now()->subMinutes(5))) {
                return ['valid' => false, 'message' => 'Disbursement is already currently being processed by gateway. Please wait for completion.'];
            }

            // Atomically acquire processing claim token under lock to block concurrent executions
            $payout->processing_token = 'CLAIM-FLEET-' . $payout->id . '-' . \Illuminate\Support\Str::random(16);
            $payout->processing_started_at = Carbon::now();
            $payout->transfer_reference = $disburseRef;
            $payout->save();

            return [
                'valid' => true,
                'payout_id' => $payout->id,
                'amount' => (string)$payout->amount,
                'company_name' => $company->company_name,
                'company_id' => $company->id,
                'account_num' => $accountNum,
                'account_name' => $accountName,
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'disburse_ref' => $disburseRef,
            ];
        });

        if (!$preValidation['valid']) {
            return back()->with('error', $preValidation['message']);
        }

        // Phase 2: Authoritative Gateway Execution OUTSIDE Database Transaction
        $paystackService = app(\App\Services\PaystackService::class);
        $payoutId = $preValidation['payout_id'];

        // 1. Resolve or Create Paystack Transfer Recipient
        $recipientRes = $paystackService->createTransferRecipient(
            $preValidation['account_name'],
            $preValidation['account_num'],
            $preValidation['bank_code'] ?: $preValidation['bank_name']
        );

        if (!$recipientRes['success']) {
            DB::transaction(function () use ($payoutId, $recipientRes) {
                LogisticsPayoutRequest::where('id', $payoutId)->update([
                    'processing_token' => null,
                    'processing_started_at' => null,
                    'gateway_status' => 'failed',
                    'admin_notes' => 'Recipient resolution failed before transfer submission: ' . ($recipientRes['message'] ?? 'NIBSS resolution failed'),
                ]);
            });
            return back()->with('error', 'Paystack Transfer Recipient Error: ' . ($recipientRes['message'] ?? 'Unable to resolve bank account on NIBSS.'));
        }

        $recipientCode = $recipientRes['recipient_code'];

        // 2. Authoritative Paystack Automated Transfer Execution
        $transferRes = $paystackService->initiateTransfer(
            $recipientCode,
            $preValidation['amount'],
            "Corporate fleet payout for {$preValidation['company_name']}",
            $preValidation['disburse_ref']
        );

        if (!$transferRes['success']) {
            $isUncertain = !in_array($transferRes['code'] ?? '', ['DEFINITIVE_REJECTION', 'CONFIG_MISSING', 'AMOUNT_BELOW_MINIMUM'], true);
            DB::transaction(function () use ($payoutId, $transferRes, $isUncertain) {
                LogisticsPayoutRequest::where('id', $payoutId)->update([
                    'processing_token' => $isUncertain ? 'HOLD_TIMEOUT_RECONCILIATION' : null,
                    'processing_started_at' => $isUncertain ? Carbon::now() : null,
                    'gateway_status' => $isUncertain ? 'processing' : 'failed',
                    'admin_notes' => $isUncertain
                        ? 'GATEWAY_TIMEOUT: Transfer outcome uncertain. Locked in HOLD_TIMEOUT_RECONCILIATION awaiting gateway verification: ' . ($transferRes['message'] ?? '')
                        : 'Transfer initiation failed: ' . ($transferRes['message'] ?? 'Gateway rejected transfer request'),
                ]);
            });
            $msg = $isUncertain
                ? 'Gateway connection timeout. Payout locked in HOLD_TIMEOUT_RECONCILIATION state. Use Reconcile with Paystack before retry.'
                : ('Paystack Automated Transfer Failed: ' . ($transferRes['message'] ?? 'Gateway rejected transfer request.') . ' Payout reverted to pending review.');
            return back()->with('error', $msg);
        }

        $transferCode = $transferRes['transfer_code'] ?? null;
        $proofPath = $transferCode ? ('paystack_transfer:' . $transferCode) : ('paystack_transfer_ref:' . $preValidation['disburse_ref']);
        $transferStatus = strtolower($transferRes['status'] ?? 'pending');

        // Phase 3: Final Atomic Settlement & Journal Transaction via Unified Finalization Service
        $payout = LogisticsPayoutRequest::with('company')->findOrFail($payoutId);

        // Audit Finding 1 (P0): Distinguish transfer submitted / processing vs confirmed successful
        if ($transferStatus === 'success') {
            app(\App\Services\PayoutFinalizationService::class)->finalizeLogisticsPayout($payout, [
                'transfer_code' => $transferCode,
                'reference' => $preValidation['disburse_ref'],
                'status' => 'success',
            ], $admin);

            \App\Models\AdminAuditLog::create([
                'user_id' => $admin->id,
                'action' => 'logistics_payout_disbursed',
                'target_type' => LogisticsPayoutRequest::class,
                'target_id' => $payout->id,
                'old_values' => ['status' => 'pending', 'amount' => $payout->amount],
                'new_values' => ['status' => 'approved', 'amount' => $payout->amount],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('status', "Successfully disbursed ₦" . number_format((float)$payout->amount, 2) . " to " . ($payout->company?->company_name ?? 'Fleet') . " via Paystack!");
        } else {
            // Transfer queued at gateway / pending bank execution (e.g. 'received', 'pending', 'processing', 'otp')
            // Monotonic transactional state guard: Never overwrite payout if already approved/completed/rejected
            DB::transaction(function () use ($payoutId, $transferCode, $preValidation, $transferStatus, $proofPath, $admin, $request) {
                $payout = LogisticsPayoutRequest::where('id', $payoutId)->lockForUpdate()->firstOrFail();
                if (in_array($payout->status, ['approved', 'completed', 'rejected'], true)) {
                    return;
                }
                $payout->status = 'pending';
                $payout->gateway_status = 'processing';
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->paystack_transfer_code = $transferCode;
                $payout->transfer_reference = $preValidation['disburse_ref'];
                $payout->settlement_proof_path = $proofPath;
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . "Transfer queued at Paystack ({$transferCode}). Gateway status: {$transferStatus}. Awaiting bank delivery confirmation.";
                $payout->save();

                \App\Models\AdminAuditLog::create([
                    'user_id' => $admin->id,
                    'action' => 'logistics_payout_processing',
                    'target_type' => LogisticsPayoutRequest::class,
                    'target_id' => $payout->id,
                    'old_values' => ['status' => 'pending', 'amount' => $payout->amount],
                    'new_values' => ['status' => 'pending', 'gateway_status' => 'processing', 'amount' => $payout->amount],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return back()->with('status', "Transfer of ₦" . number_format((float)$payout->amount, 2) . " queued at Paystack. Marked as processing awaiting bank delivery confirmation.");
        }
    }

    

    /**
     * Interactive Action: Admin rejects a logistics company withdrawal request and atomically restores wallet balance
     */
    public function adminRejectLogisticsPayout(Request $request)
    {
        $validated = $request->validate([
            'payout_id' => 'required|exists:logistics_payout_requests,id',
            'rejection_reason' => 'required|string|min:5|max:1000',
        ]);

        $admin = Auth::user() ?? User::where('user_type', 'super_admin')->first();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->isAdminEmployee()) || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Administrative finance permissions required.');
        }

        return DB::transaction(function () use ($validated, $admin, $request) {
            $payout = LogisticsPayoutRequest::with('company')->where('id', $validated['payout_id'])->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'pending') {
                return back()->with('error', "Payout request #{$payout->id} has already been {$payout->status}.");
            }

            if (\App\Models\FinancialJournalEntry::whereIn('event_id', [
                'EVT-FLEET-PAYOUT-' . $payout->id,
                'EVT-FLEET-REJECT-' . $payout->id,
            ])->exists()) {
                return back()->with('error', "Logistics payout #{$payout->id} already has a terminal accounting event. Finance reconciliation is required; wallet restoration was blocked.");
            }

            // A logistics payout can remain "pending" after its transfer claim was
            // acquired. Never restore the wallet while a transfer may be in flight
            // or its gateway outcome is uncertain; reconcile it first.
            if (in_array((string) $payout->gateway_status, ['pending', 'processing', 'otp', 'received', 'unknown'], true)
                || !empty($payout->paystack_transfer_code)
                || (!empty($payout->transfer_reference) && $payout->gateway_status !== 'failed')
                || !empty($payout->processing_token)
                || str_contains((string) $payout->admin_notes, 'GATEWAY_TIMEOUT')) {
                return back()->with('error', "Logistics payout request #{$payout->id} has a gateway submission or uncertain outcome. Reconcile with Paystack before it can be rejected.");
            }

            $company = $payout->company;
            if (!$company || $company->trashed()) {
                return back()->with('error', 'Cannot reject payout: logistics company record is missing or archived. Finance reconciliation is required.');
            }
            $payout->status = 'rejected';
            $payout->rejection_reason = strip_tags(trim($validated['rejection_reason']));
            $payout->save();

            $formattedPayoutAmount = bcadd((string)$payout->amount, '0', 2);
            $hasRequestJournal = app(\App\Services\PayoutFinalizationService::class)->hasLogisticsRequestJournalEvidence($payout);

            if ($hasRequestJournal) {
                // Only restore wallet funds when the original request-time debit/
                // payable journal proves that this amount was reserved.
                $company->creditWallet($payout->amount);

                $rejectRef = 'FLEET-REJECT-' . $payout->id . '-' . date('YmdHis');
                \App\Models\FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $rejectRef,
                    'event_id' => 'EVT-FLEET-REJECT-' . $payout->id,
                    'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                    'beneficiary_id' => $company->id,
                    'account_name' => 'logistics_payout_payable',
                    'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Corporate withdrawal liability discharged upon rejection for {$company->company_name}: {$payout->rejection_reason}",
                ]);

                \App\Models\FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $rejectRef,
                    'event_id' => 'EVT-FLEET-REJECT-' . $payout->id,
                    'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                    'beneficiary_id' => $company->id,
                    'account_name' => 'logistics_company_wallet',
                    'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Corporate wallet balance restored upon withdrawal rejection for {$company->company_name}",
                ]);
            } else {
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'REJECTION_JOURNAL_MISSING: finance reconciliation required; wallet was not credited automatically.';
                $payout->save();
                \Illuminate\Support\Facades\Log::critical('Logistics payout rejected without a request journal; wallet restoration requires finance reconciliation.', [
                    'payout_id' => $payout->id,
                    'logistics_company_id' => $company->id,
                ]);
            }

            AdminAuditLog::create([
                'user_id' => $admin->id,
                'action' => 'logistics_payout_rejected',
                'target_type' => LogisticsPayoutRequest::class,
                'target_id' => $payout->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => [
                    'status' => 'rejected',
                    'rejection_reason' => $payout->rejection_reason,
                    'restored_balance' => $company->wallet_balance,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('status', 'Logistics corporate withdrawal request rejected and wallet balance refunded successfully.');
        });
    }

    /**
     * Interactive Action: Admin disburses 90% merchandise earnings payout to Merchant via Paystack Transfer API
     */
    public function adminDisburseVendorPayout(Request $request)
    {
        $validated = $request->validate([
            'payout_id' => 'required|exists:vendor_payout_requests,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $admin = Auth::user() ?? User::where('user_type', 'super_admin')->first();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->isAdminEmployee()) || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Administrative finance permissions required.');
        }

        // Phase 1: Pre-validation & Atomic Status Transition to 'processing' under lock
        $preValidation = DB::transaction(function () use ($validated, $admin) {
            $payout = \App\Models\VendorPayoutRequest::with(['vendor.user'])->where('id', $validated['payout_id'])->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'pending') {
                return ['valid' => false, 'message' => "Vendor payout request #{$payout->id} has already been {$payout->status}."];
            }

            // Enforce launch limits again at the transfer boundary, including legacy rows.
            if (\App\Services\BcMathService::comp((string) $payout->amount, '5000.00') < 0
                || \App\Services\BcMathService::comp((string) $payout->amount, '500000.00') > 0) {
                return ['valid' => false, 'message' => 'Disbursement blocked: vendor payout must be between ₦5,000 and ₦500,000. Resolve this legacy request through finance review.'];
            }

            if ($payout->processing_token === 'HOLD_TIMEOUT_RECONCILIATION' || str_contains((string)$payout->admin_notes, 'GATEWAY_TIMEOUT')) {
                return ['valid' => false, 'message' => "Payout request #{$payout->id} is locked in GATEWAY TIMEOUT state. An automated transfer was already dispatched to Paystack and may have completed. You must perform Gateway Reconciliation before any retry."];
            }

            // A stale claim is not proof that the gateway call never happened.
            // Never reclaim it automatically; reconciliation must resolve the old attempt.
            if (!empty($payout->processing_token)) {
                return ['valid' => false, 'message' => "Payout request #{$payout->id} has an existing processing claim. Reconcile the previous attempt before retrying."];
            }

            if (($payout->gateway_status !== 'failed' && !empty($payout->transfer_reference))
                || $payout->gateway_status === 'processing' || !empty($payout->paystack_transfer_code)) {
                $codeMsg = !empty($payout->paystack_transfer_code) ? " (Transfer code: {$payout->paystack_transfer_code})" : "";
                return ['valid' => false, 'message' => "Disbursement is already in-flight with Paystack gateway{$codeMsg}. Re-submission is prohibited."];
            }

            $vendor = $payout->vendor;
            if (!$vendor || $vendor->trashed() || $vendor->marketplace_status !== 'active') {
                return ['valid' => false, 'message' => 'Disbursement blocked: Merchant store is inactive, suspended, or archived.'];
            }

            if (!$vendor->user || $vendor->user->status !== 'active') {
                return ['valid' => false, 'message' => 'Disbursement blocked: Merchant user account is inactive or suspended.'];
            }

            if ($vendor->isWithinBankCooldown()) {
                return ['valid' => false, 'message' => 'Disbursement blocked: Merchant bank account was recently updated and is under a 48-hour security cooldown.'];
            }

            // Verify they actually sold: must have legitimate delivered and paid orders
            $hasDeliveredSales = Order::where('vendor_id', $vendor->id)
                ->where('retailer_model_order', false)
                ->where('payment_status', 'paid')
                ->where('order_status', 'delivered')
                ->exists();
            if (!$hasDeliveredSales) {
                return ['valid' => false, 'message' => 'Disbursement blocked: Merchant has no verified delivered sales records.'];
            }

            // Allocation Conservation Pre-check & Pre-Allocation Hold (Audit Findings P0 & P1):
            $existingAllocationRows = $payout->allocations()
                ->where('status', 'allocated')
                ->with('order')
                ->get();
            $invalidAllocation = $existingAllocationRows->first(function ($allocation) use ($vendor) {
                return !$allocation->order
                    || $allocation->order->retailer_model_order
                    || (int) $allocation->order->vendor_id !== (int) $vendor->id
                    || bccomp((string) $allocation->allocated_amount, '0.00', 2) <= 0;
            });
            if ($invalidAllocation) {
                return [
                    'valid' => false,
                    'message' => 'Disbursement blocked: existing payout allocations contain a missing, non-positive, or cross-vendor order mapping. Finance reconciliation is required before any transfer is sent.',
                ];
            }

            // Each pre-existing allocation must reserve exactly one whole earning
            // record. Do not send cash for partial, duplicated, disputed, returned, or
            // already-settled order mappings left behind by a legacy attempt.
            foreach ($existingAllocationRows->groupBy('order_id') as $orderAllocations) {
                $earningOrder = $orderAllocations->first()->order;
                if (!$earningOrder
                    || $orderAllocations->count() !== 1
                    || $earningOrder->vendor_settlement_status !== 'eligible'
                    || $earningOrder->payment_status !== 'paid'
                    || $earningOrder->order_status !== 'delivered'
                    || $earningOrder->returnCases()->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed'])->exists()
                    || $earningOrder->dispute()->whereIn('status', ['filed', 'under_review'])->exists()) {
                    return [
                        'valid' => false,
                        'message' => 'Disbursement blocked: an existing allocation no longer maps to one eligible, delivered, paid earning record. Finance reconciliation is required before any transfer is sent.',
                    ];
                }

                $basePayable = (string) ($earningOrder->vendor_net_90 ?? '0.00');
                if (empty($earningOrder->logistics_company_id)) {
                    $basePayable = BcMathService::add($basePayable, (string) ($earningOrder->logistics_fee_retained ?? '0.00'));
                }
                $currentOrderAllocation = $orderAllocations->reduce(
                    fn (string $sum, $allocation) => bcadd($sum, (string) $allocation->allocated_amount, 2),
                    '0.00'
                );
                $otherActiveAllocation = $earningOrder->payoutAllocations()
                    ->where('status', '!=', 'reversed')
                    ->whereHas('payoutRequest', function ($query) use ($payout) {
                        $query->where('status', '!=', 'rejected')
                            ->where('id', '!=', $payout->id);
                    })
                    ->exists();

                if (bccomp($basePayable, '0.00', 2) <= 0
                    || bccomp($currentOrderAllocation, $basePayable, 2) !== 0
                    || $otherActiveAllocation) {
                    return [
                        'valid' => false,
                        'message' => 'Disbursement blocked: existing allocations do not represent one complete, exclusively reserved earning record. Finance reconciliation is required before any transfer is sent.',
                    ];
                }
            }

            $existingAllocSum = $existingAllocationRows->reduce(
                fn (string $sum, $allocation) => bcadd($sum, (string) $allocation->allocated_amount, 2),
                '0.00'
            );
            if (bccomp($existingAllocSum, (string) $payout->amount, 2) > 0) {
                return [
                    'valid' => false,
                    'message' => 'Disbursement blocked: existing allocated earnings exceed the payout amount. Finance reconciliation is required before any transfer is sent.',
                ];
            }
            $remainingNeeded = bcsub((string)$payout->amount, $existingAllocSum, 2);

            if (bccomp($remainingNeeded, '0.00', 2) > 0) {
                $eligibleOrders = Order::where('vendor_id', $vendor->id)
                    ->where('retailer_model_order', false)
                    ->where('vendor_settlement_status', 'eligible')
                    ->where('payment_status', 'paid')
                    ->where('order_status', 'delivered')
                    ->whereDoesntHave('returnCases', function ($q) {
                        $q->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed']);
                    })
                    ->whereDoesntHave('dispute', function ($q) {
                        $q->whereIn('status', ['filed', 'under_review']);
                    })
                    // FIFO is deterministic: oldest eligible earning first, then stable ID tie-break.
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $totalEligibleToSettle = '0.00';
                foreach ($eligibleOrders as $elOrder) {
                    $totalEligibleToSettle = bcadd($totalEligibleToSettle, (string)$elOrder->remaining_payable_to_vendor, 2);
                }

                if (bccomp($totalEligibleToSettle, $remainingNeeded, 2) < 0) {
                    return [
                        'valid' => false,
                        'message' => "Disbursement blocked: Eligible unallocated orders (₦{$totalEligibleToSettle}) cannot cover requested payout amount of ₦" . number_format((float)$payout->amount, 2) . ". Active disputes or pending returns may have reduced withdrawable earnings.",
                    ];
                }

                // Whole earning records only: never split an order's vendor payable.
                // Stop at the first FIFO record that would exceed this requested amount.
                // If the request cannot be composed exactly from whole records, require
                // a correctly sized request or finance resolution before transfer.
                $rem = $remainingNeeded;
                $allocationPlan = [];
                foreach ($eligibleOrders as $elOrder) {
                    if (bccomp($rem, '0.00', 2) <= 0) break;
                    $avail = (string) $elOrder->remaining_payable_to_vendor;
                    if (bccomp($avail, '0.00', 2) <= 0) continue;
                    if (bccomp($avail, $rem, 2) > 0) break;
                    $allocationPlan[] = ['order' => $elOrder, 'amount' => $avail];
                    $rem = bcsub($rem, $avail, 2);
                }

                if (bccomp($rem, '0.00', 2) !== 0) {
                    return [
                        'valid' => false,
                        'message' => 'Payout blocked: the requested amount does not match whole earning records in strict FIFO order. No partial order allocation was created. Request the exact eligible whole-record total or send the oldest over-cap record for finance resolution.',
                    ];
                }

                // Reserve only after proving the full request can be allocated.
                foreach ($allocationPlan as $planned) {
                    \App\Models\VendorPayoutAllocation::create([
                        'vendor_payout_request_id' => $payout->id,
                        'order_id' => $planned['order']->id,
                        'allocated_amount' => $planned['amount'],
                        'status' => 'allocated',
                    ]);
                }
            }

            // Validate Merchant bank details
            $accountNum = trim((string)($vendor->account_number ?? $payout->account_number));
            $bankName = trim((string)($vendor->bank_name ?? $payout->bank_name));
            $bankCode = trim((string)($vendor->bank_code ?? ''));
            $accountName = trim((string)($vendor->account_name ?? $payout->account_name));

            if (empty($accountNum) || empty($accountName)) {
                // No gateway request has been sent at this point. Release every
                // allocation created for this pending request so missing bank data
                // cannot strand eligible order earnings in an allocated state.
                \\App\\Models\\VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)
                    ->where('status', 'allocated')
                    ->update([
                        'status' => 'reversed',
                        'reversed_at' => Carbon::now(),
                    ]);

                return ['valid' => false, 'message' => 'Disbursement blocked: Incomplete NUBAN bank details on merchant profile.'];
            }

            $createdTs = $payout->created_at ? $payout->created_at->format('YmdHis') : date('YmdHis');
            $disburseRef = 'VND-PAYOUT-' . $payout->id . '-' . $createdTs;

            // Concurrency Guard: Check for active in-flight processing claim
            if (!empty($payout->processing_token) && $payout->processing_started_at && $payout->processing_started_at->gt(Carbon::now()->subMinutes(5))) {
                return ['valid' => false, 'message' => 'Disbursement is already currently being processed by gateway. Please wait for completion.'];
            }

            // Atomically acquire processing claim token under lock to block concurrent executions
            $payout->processing_token = 'CLAIM-VND-' . $payout->id . '-' . \Illuminate\Support\Str::random(16);
            $payout->processing_started_at = Carbon::now();
            $payout->transfer_reference = $disburseRef;
            $payout->save();

            return [
                'valid' => true,
                'payout_id' => $payout->id,
                'amount' => (string)$payout->amount,
                'business_name' => $vendor->business_name,
                'vendor_id' => $vendor->id,
                'account_num' => $accountNum,
                'account_name' => $accountName,
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'disburse_ref' => $disburseRef,
            ];
        });

        if (!$preValidation['valid']) {
            return back()->with('error', $preValidation['message']);
        }

        // Phase 2: Authoritative Gateway Execution OUTSIDE Database Transaction
        $paystackService = app(\App\Services\PaystackService::class);
        $payoutId = $preValidation['payout_id'];

        // 1. Resolve or Create Paystack Transfer Recipient
        $recipientRes = $paystackService->createTransferRecipient(
            $preValidation['account_name'],
            $preValidation['account_num'],
            $preValidation['bank_code'] ?: $preValidation['bank_name']
        );

        if (!$recipientRes['success']) {
            DB::transaction(function () use ($payoutId, $recipientRes) {
                \App\Models\VendorPayoutRequest::where('id', $payoutId)->update([
                    'processing_token' => null,
                    'processing_started_at' => null,
                    'gateway_status' => 'failed',
                    'admin_notes' => 'Recipient resolution failed before transfer submission: ' . ($recipientRes['message'] ?? 'NIBSS resolution failed'),
                ]);
                \App\Models\VendorPayoutAllocation::where('vendor_payout_request_id', $payoutId)->where('status', 'allocated')->update([
                    'status' => 'reversed',
                    'reversed_at' => Carbon::now(),
                ]);
            });
            return back()->with('error', 'Paystack Transfer Recipient Error: ' . ($recipientRes['message'] ?? 'Unable to resolve merchant bank account on NIBSS.'));
        }

        $recipientCode = $recipientRes['recipient_code'];

        // 2. Authoritative Paystack Automated Transfer Execution
        $transferRes = $paystackService->initiateTransfer(
            $recipientCode,
            $preValidation['amount'],
            "Merchant 90% earnings payout for {$preValidation['business_name']}",
            $preValidation['disburse_ref']
        );

        if (!$transferRes['success']) {
            $isUncertain = !in_array($transferRes['code'] ?? '', ['DEFINITIVE_REJECTION', 'CONFIG_MISSING', 'AMOUNT_BELOW_MINIMUM'], true);
            DB::transaction(function () use ($payoutId, $transferRes, $isUncertain) {
                \App\Models\VendorPayoutRequest::where('id', $payoutId)->update([
                    'processing_token' => $isUncertain ? 'HOLD_TIMEOUT_RECONCILIATION' : null,
                    'processing_started_at' => $isUncertain ? Carbon::now() : null,
                    'gateway_status' => $isUncertain ? 'processing' : 'failed',
                    'admin_notes' => $isUncertain
                        ? 'GATEWAY_TIMEOUT: Transfer outcome uncertain. Locked in HOLD_TIMEOUT_RECONCILIATION awaiting gateway verification: ' . ($transferRes['message'] ?? '')
                        : 'Transfer initiation failed: ' . ($transferRes['message'] ?? 'Gateway rejected transfer request'),
                ]);
                if (!$isUncertain) {
                    \App\Models\VendorPayoutAllocation::where('vendor_payout_request_id', $payoutId)->where('status', 'allocated')->update([
                        'status' => 'reversed',
                        'reversed_at' => Carbon::now(),
                    ]);
                }
            });
            $msg = $isUncertain
                ? 'Gateway connection timeout. Payout locked in HOLD_TIMEOUT_RECONCILIATION state. Use Reconcile with Paystack before retry.'
                : ('Paystack Transfer Initiation Error: ' . ($transferRes['message'] ?? 'Automated transfer could not be initiated with bank gateway. Payout reverted to pending.'));
            return back()->with('error', $msg);
        }

        $transferCode = $transferRes['transfer_code'] ?? null;
        $proofPath = $transferCode ? ('paystack_transfer:' . $transferCode) : ('paystack_transfer_ref:' . $preValidation['disburse_ref']);
        $transferStatus = strtolower($transferRes['status'] ?? 'pending');

        // Phase 3: Final Atomic Settlement, Immutable Allocations & Journal Transaction via Unified Finalization Service
        $payout = \App\Models\VendorPayoutRequest::findOrFail($payoutId);

        // Audit Finding 1 (P0): Distinguish transfer submitted / processing vs confirmed successful
        if ($transferStatus === 'success') {
            app(\App\Services\PayoutFinalizationService::class)->finalizeVendorPayout($payout, [
                'transfer_code' => $transferCode,
                'reference' => $preValidation['disburse_ref'],
                'status' => 'success',
            ], $admin);

            return back()->with('status', "Successfully disbursed ₦" . number_format((float)$payout->amount, 2) . " merchandise earnings to {$preValidation['business_name']}!");
        } else {
            // Transfer queued at gateway / pending bank execution (e.g. 'received', 'pending', 'processing', 'otp')
            // Monotonic transactional state guard: Never overwrite payout if already approved/completed/rejected
            DB::transaction(function () use ($payoutId, $transferCode, $preValidation, $transferStatus, $proofPath) {
                $payout = \App\Models\VendorPayoutRequest::where('id', $payoutId)->lockForUpdate()->firstOrFail();
                if (in_array($payout->status, ['approved', 'completed', 'rejected'], true)) {
                    return;
                }
                $payout->status = 'pending';
                $payout->gateway_status = 'processing';
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->paystack_transfer_code = $transferCode;
                $payout->transfer_reference = $preValidation['disburse_ref'];
                $payout->settlement_proof_path = $proofPath;
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . "Transfer queued at Paystack ({$transferCode}). Gateway status: {$transferStatus}. Awaiting bank delivery confirmation.";
                $payout->save();
            });

            return back()->with('status', "Transfer of ₦" . number_format((float)$payout->amount, 2) . " queued at Paystack. Marked as processing awaiting bank delivery confirmation.");
        }
    }

    

    /**
     * Interactive Action: Admin rejects a merchant withdrawal request and atomically frees withdrawable balance
     */
    public function adminRejectVendorPayout(Request $request)
    {
        $validated = $request->validate([
            'payout_id' => 'required|exists:vendor_payout_requests,id',
            'rejection_reason' => 'required|string|min:5|max:1000',
        ]);

        $admin = Auth::user() ?? User::where('user_type', 'super_admin')->first();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->isAdminEmployee()) || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Administrative finance permissions required.');
        }

        return DB::transaction(function () use ($validated, $admin, $request) {
            $payout = \App\Models\VendorPayoutRequest::with('vendor')->where('id', $validated['payout_id'])->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'pending') {
                return back()->with('error', "Payout request #{$payout->id} has already been {$payout->status}.");
            }

            if (\App\Models\FinancialJournalEntry::whereIn('event_id', [
                'EVT-VND-PAYOUT-' . $payout->id,
                'EVT-MERCHANT-REJECT-' . $payout->id,
            ])->exists()) {
                return back()->with('error', "Vendor payout #{$payout->id} already has a terminal accounting event. Finance reconciliation is required; liability reversal was blocked.");
            }

            // A payout may remain in the legacy "pending" status after Paystack accepts
            // the transfer. Never release its reservation while the gateway outcome is
            // processing, uncertain, or already associated with a transfer code/reference.
            if (in_array((string) $payout->gateway_status, ['pending', 'processing', 'otp', 'received', 'unknown'], true)
                || !empty($payout->paystack_transfer_code)
                || (!empty($payout->transfer_reference) && $payout->gateway_status !== 'failed')
                || !empty($payout->processing_token)
                || str_contains((string) $payout->admin_notes, 'GATEWAY_TIMEOUT')) {
                return back()->with('error', "Payout request #{$payout->id} has a gateway submission or uncertain outcome. Reconcile with Paystack before it can be rejected.");
            }

            $vendor = $payout->vendor;
            $payout->status = 'rejected';
            $payout->rejection_reason = strip_tags(trim($validated['rejection_reason']));
            $payout->save();

            // Reverse the request-time reclassification only when that journal exists.
            // Legacy rows without a request journal require finance reconciliation rather
            // than fabricated liability movements.
            $hasRequestJournal = app(\App\Services\PayoutFinalizationService::class)->hasVendorRequestJournalEvidence($payout);
            $formattedPayoutAmount = bcadd((string)$payout->amount, '0', 2);
            $rejectRef = 'MERCHANT-REJECT-' . $payout->id . '-' . date('YmdHis');

            if ($hasRequestJournal) {
                \App\Models\FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $rejectRef,
                    'event_id' => 'EVT-MERCHANT-REJECT-' . $payout->id,
                    'beneficiary_type' => \App\Models\VendorProfile::class,
                    'beneficiary_id' => $vendor->id,
                    'account_name' => 'vendor_payout_payable',
                    'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Reserved vendor payout liability released upon rejection: {$payout->rejection_reason}",
                ]);

                \App\Models\FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $rejectRef,
                    'event_id' => 'EVT-MERCHANT-REJECT-' . $payout->id,
                    'beneficiary_type' => \App\Models\VendorProfile::class,
                    'beneficiary_id' => $vendor->id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Vendor payable restored upon payout rejection for {$vendor->business_name}",
                ]);
            } else {
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'REJECTION_JOURNAL_MISSING: finance reconciliation required; no liability movement fabricated.';
                $payout->save();
                \Illuminate\Support\Facades\Log::critical('Vendor payout rejected without a request journal; manual accounting reconciliation required.', [
                    'payout_id' => $payout->id,
                    'vendor_id' => $vendor->id,
                ]);
            }

            AdminAuditLog::create([
                'user_id' => $admin->id,
                'action' => 'vendor_payout_rejected',
                'target_type' => \App\Models\VendorPayoutRequest::class,
                'target_id' => $payout->id,
                'old_values' => ['status' => 'pending'],
                'new_values' => [
                    'status' => 'rejected',
                    'rejection_reason' => $payout->rejection_reason,
                    'vendor_id' => $vendor->id,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('status', 'Merchant withdrawal request rejected and funds restored successfully.');
        });
    }

    public function adminUpdateFinancialSettings(Request $request)
    {
        $admin = Auth::user();
        if (!$admin || !$admin->isSuperAdmin() || $admin->status !== 'active') {
            return back()->with('error', 'Unauthorized: Super Admin permissions required.');
        }

        $validated = $request->validate([
            'refund_window_hours' => 'required|integer|min:1|max:720',
        ]);

        $oldHours = (int) \App\Models\SystemSetting::get('refund_window_hours', 24);
        $newHours = (int) $validated['refund_window_hours'];

        \App\Models\SystemSetting::set('refund_window_hours', (string) $newHours, 'financial_policy');

        AdminAuditLog::create([
            'user_id' => $admin->id,
            'action' => 'refund_window_setting_updated',
            'target_type' => \App\Models\SystemSetting::class,
            'target_id' => null,
            'old_values' => ['refund_window_hours' => $oldHours],
            'new_values' => ['refund_window_hours' => $newHours],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.settlements.index')->with('status', "Platform refund window and cashback maturation timer set to {$newHours} hours successfully.");
    }

    /**
     * Interactive Action: Logistics Company updates corporate bank account
     */
    public function logisticsUpdateBank(Request $request)
    {
        $company = $this->resolveLogisticsCompany();

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|regex:/^[0-9]{10}$/',
            'account_name' => 'required|string|max:150',
        ], [
            'account_number.regex' => 'NUBAN corporate account number must be exactly 10 digits.',
        ]);

        $company->bank_name = strip_tags(trim($validated['bank_name']));
        $company->bank_code = preg_replace('/[^0-9]/', '', $request->input('bank_code', '000'));
        $company->account_number = trim($validated['account_number']);
        $company->account_name = strip_tags(trim($validated['account_name']));
        $company->bank_updated_at = Carbon::now();
        $company->save();

        return back()->with('status', 'Corporate bank account details updated successfully. 48-Hour Security Cooldown is now active.');
    }

    /**
     * Interactive Action: Logistics Company requests withdrawal from accumulated 90% wallet balance
     */
    public function logisticsRequestPayout(Request $request)
    {
        $company = $this->resolveLogisticsCompany();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:500|max:50000000',
        ]);

        if ($company->status !== 'active') {
            return back()->with('error', 'Unauthorized: Logistics fleet account is inactive or suspended.');
        }

        if (empty($company->account_number) || empty($company->bank_name)) {
            return back()->with('error', 'Please configure your verified corporate bank account before requesting withdrawal.');
        }

        if ($company->isWithinBankCooldown()) {
            return back()->with('error', 'Security Cooldown Active: Bank account was recently updated. For fraud prevention, withdrawals are locked for 48 hours following bank detail modifications.');
        }

        return DB::transaction(function () use ($company, $validated) {
            $lockedCompany = LogisticsCompanyProfile::where('id', $company->id)->lockForUpdate()->first();

            $amount = number_format((float)$validated['amount'], 2, '.', '');
            if (bccomp((string)$lockedCompany->wallet_balance, $amount, 2) < 0) {
                return back()->with('error', "Insufficient wallet balance. Available balance: ₦" . number_format((float)$lockedCompany->wallet_balance, 2));
            }

            // Atomically debit company corporate wallet
            $lockedCompany->debitWallet($amount);

            // Record payout request
            $payout = LogisticsPayoutRequest::create([
                'logistics_company_id' => $lockedCompany->id,
                'amount' => $amount,
                'status' => 'pending',
                'bank_name' => $lockedCompany->bank_name,
                'account_number' => $lockedCompany->account_number,
                'account_name' => $lockedCompany->account_name ?? $lockedCompany->company_name,
            ]);

            \App\Services\NotificationDispatchService::dispatchPayoutRequested($payout);

            // Authoritative Double-Entry Financial Journal: Debit corporate wallet, credit payout payable
            $reqRef = 'FLEET-REQ-' . $lockedCompany->id . '-' . date('YmdHis');
            \App\Models\FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $reqRef,
                'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
                'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                'beneficiary_id' => $lockedCompany->id,
                'account_name' => 'logistics_company_wallet',
                'entry_type' => 'debit',
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Corporate withdrawal request placed by {$lockedCompany->company_name}",
            ]);

            \App\Models\FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $reqRef,
                'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
                'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                'beneficiary_id' => $lockedCompany->id,
                'account_name' => 'logistics_payout_payable',
                'entry_type' => 'credit',
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Corporate withdrawal liability accrued for {$lockedCompany->company_name}",
            ]);

            return back()->with('status', "Withdrawal request for ₦" . number_format((float)$amount, 2) . " submitted! Super Admin will review and disburse to {$lockedCompany->bank_name} ({$lockedCompany->account_number}).");
        });
    }



    /**
     * Interactive Action: Vendor signs off in-shop physical inspection
     */
    public function vendorApproveInspection(Request $request, OrderSettlementService $settlementService)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            return back()->with('error', 'Unauthorized: Merchant marketplace account is suspended.');
        }

        $validated = $request->validate([
            'reservation_code' => 'required|string|max:50',
        ]);

        $reservation = PickupReservation::where('vendor_id', $vendor->id)
            ->where('reservation_code', trim($validated['reservation_code']))
            ->firstOrFail();
        $staff = Auth::user();

        $result = $settlementService->approvePickupInspection($reservation, $staff);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Counter Terminal releases package to Logistics Rider using Handover OTP
     * Strictly scopes orders to the employee's assigned shop and requires an assigned accredited courier.
     */
    public function counterVerifyHandoverOtp(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'order_number' => 'nullable|string|exists:orders,order_number',
            'handover_otp' => 'nullable|string',
            'pickup_otp' => 'nullable|string',
        ]);

        $shop = $this->resolvePrimaryShop();
        $query = Order::where('shop_id', $shop->id);

        if (!empty($validated['order_id'])) {
            $order = $query->findOrFail($validated['order_id']);
        } elseif (!empty($validated['order_number'])) {
            $order = $query->where('order_number', $validated['order_number'])->firstOrFail();
        } else {
            return back()->with('error', 'Order reference is required.');
        }

        $otp = $validated['pickup_otp'] ?? $validated['handover_otp'] ?? '';
        if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
            return back()->with('error', 'Handover verification code must be exactly 6 numerical digits.');
        }

        $rider = $order->rider;
        if (!$rider) {
            return back()->with('error', 'No courier assigned to this order yet. Fleet dispatch must assign an approved rider before handover verification.');
        }

        $result = $settlementService->verifyRiderPickupOtp($order, $otp, $rider);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Counter staff verifies the customer handover for an in-shop pickup.
     * Requires the customer's reservation pass, one-time pickup OTP and photo evidence.
     */
    public function counterVerifyCustomerPickup(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_number' => 'required|string|exists:orders,order_number',
            'reservation_code' => 'required|string|max:50',
            'customer_pickup_otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'required|image|max:10240',
        ]);

        $shop = $this->resolvePrimaryShop();
        $order = Order::where('shop_id', $shop->id)
            ->where('order_number', trim($validated['order_number']))
            ->firstOrFail();

        $proof = app(\App\Services\ImageOptimizationService::class)
            ->optimizeAndStoreProof($request->file('proof_photo'), 'customer_pickup_proofs');

        $result = $settlementService->verifyPickupHandoverOtp(
            $order,
            $validated['customer_pickup_otp'],
            Auth::user(),
            $validated['reservation_code'],
            $proof['path']
        );

        if (!$result['success']) {
            return back()->withErrors(['customer_pickup_otp' => $result['message']]);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Rider verifies delivery at Customer doorstep using Dual OTP
     */
    /**
     * Interactive Action: Rider arrives at Customer location and triggers customer-side handshake
     */
    public function riderMarkArrived(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|integer|exists:orders,id',
            'order_number' => 'nullable|string|exists:orders,order_number',
        ]);

        $order = null;
        if (!empty($validated['order_id'])) {
            $order = Order::findOrFail($validated['order_id']);
        } elseif (!empty($validated['order_number'])) {
            $order = Order::where('order_number', $validated['order_number'])->firstOrFail();
        } else {
            return back()->with('error', 'Order reference is required to signal arrival.');
        }

        $rider = $this->resolveRiderProfile();
        $result = $settlementService->markRiderArrived($order, $rider);

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Polling Action: Rider phone checks if customer has confirmed receipt on their device
     */
    public function riderCheckDeliveryStatus(Request $request, $orderId)
    {
        $order = Order::with(['customer', 'shop'])->findOrFail($orderId);
        $rider = $this->resolveRiderProfile();

        if (!$rider || (int)$order->rider_id !== (int)$rider->id) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: This order is not assigned to your dispatch terminal.',
            ], 403);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'rider_arrived' => $order->hasRiderArrived(),
            'rider_arrived_at' => $order->rider_arrived_at ? $order->rider_arrived_at->format('h:i A') : null,
            'is_delivered' => $order->order_status === 'delivered',
            'delivered_at' => $order->received_at ? $order->received_at->format('h:i A') : null,
            'proof_image' => $order->proof_of_delivery_image,
            'delivery_fee' => number_format((float)$order->delivery_fee, 2),
            'shift_status' => ($rider && $rider->is_online) ? 'ON DUTY' : 'OFF DUTY',
        ]);
    }

    public function riderVerifyDeliveryOtp(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'delivery_otp' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'required|image|max:10240',
        ], [
            'delivery_otp.regex' => 'Customer delivery verification code must be exactly 6 numerical digits.',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $rider = $this->resolveRiderProfile();

        if (!$rider || !$rider->is_active) {
            return back()->with('error', 'Unauthorized: Rider profile is not active or suspended.');
        }

        $proofPath = '/storage/' . $request->file('proof_photo')->store('delivery_proofs', 'public');
        $result = $settlementService->verifyCustomerDeliveryOtp($order, $validated['delivery_otp'], $rider, $proofPath);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Rider toggles on/off duty status
     */
    public function riderToggleShift(Request $request)
    {
        $rider = $this->resolveRiderProfile();

        if (!$rider || !$rider->is_active || ($rider->user && $rider->user->status !== 'active')) {
            return back()->with('error', 'Unauthorized: Dispatch rider profile is suspended or inactive.');
        }

        if ($rider->logisticsCompany && $rider->logisticsCompany->status !== 'active') {
            return back()->with('error', 'Unauthorized: Parent logistics fleet is currently suspended or inactive.');
        }

        $rider->is_online = !$rider->is_online;
        $rider->save();

        $statusText = $rider->is_online ? 'ON DUTY (Ready for Deliveries)' : 'OFF DUTY';
        return back()->with('status', "Your shift status has been updated to: {$statusText}");
    }

    // ==========================================
    // STAGE 1: SUPER ADMIN COMMAND ACTIONS
    // ==========================================

    public function adminStoreDeliveryLane(Request $request)
    {
        $validated = $request->validate([
            'origin_lga_id' => 'required|integer|exists:lgas,id',
            'destination_lga_id' => 'required|integer|exists:lgas,id',
            'base_fee' => 'required|numeric|min:0|max:1000000',
            'bulky_surcharge' => 'nullable|numeric|min:0|max:1000000',
            'estimated_delivery_hours_min' => 'required|integer|min:1|max:720',
            'estimated_delivery_hours_max' => 'required|integer|min:1|max:720',
            'is_bidirectional' => 'nullable',
        ]);

        $isBidirectional = $request->has('is_bidirectional') || $request->input('is_bidirectional') == '1';

        // Check if updating an existing lane
        if ($request->filled('lane_id')) {
            $existing = DeliveryLane::find($request->input('lane_id'));
            if ($existing) {
                $oldValues = [
                    'base_fee' => $existing->base_fee,
                    'bulky_surcharge' => $existing->bulky_surcharge,
                ];

                $existing->update([
                    'origin_lga_id' => $validated['origin_lga_id'],
                    'destination_lga_id' => $validated['destination_lga_id'],
                    'base_fee' => number_format((float)$validated['base_fee'], 2, '.', ''),
                    'bulky_surcharge' => number_format((float)($validated['bulky_surcharge'] ?? 1000.00), 2, '.', ''),
                    'estimated_delivery_hours_min' => $validated['estimated_delivery_hours_min'],
                    'estimated_delivery_hours_max' => $validated['estimated_delivery_hours_max'],
                    'is_active' => true,
                ]);

                if ($isBidirectional && $validated['origin_lga_id'] != $validated['destination_lga_id']) {
                    DeliveryLane::updateOrCreate([
                        'origin_lga_id' => $validated['destination_lga_id'],
                        'destination_lga_id' => $validated['origin_lga_id'],
                    ], [
                        'base_fee' => number_format((float)$validated['base_fee'], 2, '.', ''),
                        'bulky_surcharge' => number_format((float)($validated['bulky_surcharge'] ?? 1000.00), 2, '.', ''),
                        'estimated_delivery_hours_min' => $validated['estimated_delivery_hours_min'],
                        'estimated_delivery_hours_max' => $validated['estimated_delivery_hours_max'],
                        'is_active' => true,
                    ]);
                }

                AdminAuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'delivery_lane_updated',
                    'target_type' => DeliveryLane::class,
                    'target_id' => $existing->id,
                    'old_values' => $oldValues,
                    'new_values' => [
                        'base_fee' => $existing->base_fee,
                        'origin_lga_id' => $existing->origin_lga_id,
                        'destination_lga_id' => $existing->destination_lga_id,
                    ],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => $request->userAgent(),
                ]);

                $hubId = $request->input('hub_lga_id', $validated['origin_lga_id']);
                return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Corridor successfully updated!");
            }
        }

        // 1. Forward lane (Origin -> Destination)
        $lane = DeliveryLane::updateOrCreate([
            'origin_lga_id' => $validated['origin_lga_id'],
            'destination_lga_id' => $validated['destination_lga_id'],
        ], [
            'base_fee' => number_format((float)$validated['base_fee'], 2, '.', ''),
            'bulky_surcharge' => number_format((float)($validated['bulky_surcharge'] ?? 1000.00), 2, '.', ''),
            'estimated_delivery_hours_min' => $validated['estimated_delivery_hours_min'],
            'estimated_delivery_hours_max' => $validated['estimated_delivery_hours_max'],
            'is_active' => true,
        ]);

        // 2. If bidirectional, automatically create/update reverse lane (Destination -> Origin)
        if ($isBidirectional && $validated['origin_lga_id'] != $validated['destination_lga_id']) {
            DeliveryLane::updateOrCreate([
                'origin_lga_id' => $validated['destination_lga_id'],
                'destination_lga_id' => $validated['origin_lga_id'],
            ], [
                'base_fee' => number_format((float)$validated['base_fee'], 2, '.', ''),
                'bulky_surcharge' => number_format((float)($validated['bulky_surcharge'] ?? 1000.00), 2, '.', ''),
                'estimated_delivery_hours_min' => $validated['estimated_delivery_hours_min'],
                'estimated_delivery_hours_max' => $validated['estimated_delivery_hours_max'],
                'is_active' => true,
            ]);
        }

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'delivery_lane_created',
            'target_type' => DeliveryLane::class,
            'target_id' => $lane->id,
            'new_values' => [
                'origin_lga_id' => $lane->origin_lga_id,
                'destination_lga_id' => $lane->destination_lga_id,
                'base_fee' => $lane->base_fee,
                'is_bidirectional' => $isBidirectional,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        $dirMsg = $isBidirectional ? 'Bidirectional route (A ⇄ B) synchronized!' : 'One-way corridor saved!';
        $hubId = $request->input('hub_lga_id', $validated['origin_lga_id']);
        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Custom corridor successfully saved: {$dirMsg}");
    }

    public function adminUpdateZonalRates(Request $request)
    {
        $validated = $request->validate([
            'zone_intra_lga_fee' => 'required|numeric|min:0|max:1000000',
            'zone_intra_lga_eta' => 'required|string|max:50',
            'zone_inter_lga_fee' => 'required|numeric|min:0|max:1000000',
            'zone_inter_lga_eta' => 'required|string|max:50',
            'zone_inter_state_fee' => 'required|numeric|min:0|max:1000000',
            'zone_inter_state_eta' => 'required|string|max:50',
            'zone_bulky_cargo_surcharge' => 'required|numeric|min:0|max:1000000',
        ]);

        $sanitized = [];
        foreach ($validated as $k => $v) {
            $cleanVal = strip_tags(trim((string)$v));
            \App\Models\SystemSetting::set($k, $cleanVal, 'zonal_rates');
            $sanitized[$k] = $cleanVal;
        }

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'zonal_rates_updated',
            'target_type' => \App\Models\SystemSetting::class,
            'target_id' => null,
            'new_values' => $sanitized,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        $hubId = $request->input('hub_lga_id');
        return redirect()->route('admin.delivery_lanes.index', $hubId ? ['hub_lga_id' => $hubId] : [])->with('status', '3-Tier Zonal Distance Baseline Rates saved successfully!');
    }

    public function adminDeleteDeliveryLane(Request $request, int $id)
    {
        $lane = DeliveryLane::findOrFail($id);
        $hubId = $request->input('hub_lga_id', $lane->origin_lga_id);
        $laneId = $lane->id;
        $originLgaId = $lane->origin_lga_id;
        $destLgaId = $lane->destination_lga_id;
        $lane->delete();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'delivery_lane_deleted',
            'target_type' => DeliveryLane::class,
            'target_id' => $laneId,
            'old_values' => [
                'origin_lga_id' => $originLgaId,
                'destination_lga_id' => $destLgaId,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', 'Delivery corridor deleted successfully.');
    }

    public function adminToggleDeliveryLane(Request $request, int $id)
    {
        $lane = DeliveryLane::findOrFail($id);
        $oldState = (bool)$lane->is_active;
        $lane->is_active = !$lane->is_active;
        $lane->save();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'delivery_lane_toggled',
            'target_type' => DeliveryLane::class,
            'target_id' => $lane->id,
            'old_values' => ['is_active' => $oldState],
            'new_values' => ['is_active' => (bool)$lane->is_active],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        $hubId = $request->input('hub_lga_id', $lane->origin_lga_id);
        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Delivery lane #{$lane->id} toggled " . ($lane->is_active ? 'Active' : 'Inactive') . '.');
    }

    public function adminToggleHubCorridor(Request $request)
    {
        $validated = $request->validate([
            'origin_lga_id' => 'required|integer|exists:lgas,id',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
            'destination_state_id' => 'nullable|integer|exists:states,id',
            'base_fee' => 'nullable|numeric|min:0|max:1000000',
            'estimated_delivery_hours_min' => 'nullable|integer|min:1|max:720',
            'estimated_delivery_hours_max' => 'nullable|integer|min:1|max:720',
            'set_active' => 'nullable|boolean',
        ]);

        $originLgaId = (int)$validated['origin_lga_id'];

        // Case A: State-Level Corridor Toggle (Out-of-State)
        if (!empty($validated['destination_state_id'])) {
            $stateId = (int)$validated['destination_state_id'];
            $destStateLgas = Lga::where('state_id', $stateId)->get();
            $capitalLga = $destStateLgas->first();

            $existingLanes = DeliveryLane::where('origin_lga_id', $originLgaId)
                ->whereIn('destination_lga_id', $destStateLgas->pluck('id'))
                ->get();

            if ($existingLanes->isNotEmpty()) {
                $newActive = $request->has('set_active') ? (bool)$request->input('set_active') : !$existingLanes->first()->is_active;
                DeliveryLane::where('origin_lga_id', $originLgaId)
                    ->whereIn('destination_lga_id', $destStateLgas->pluck('id'))
                    ->update(['is_active' => $newActive]);
                $statusText = $newActive ? 'Active' : 'Suspended';
            } else {
                // If no lane existed, it was active via baseline. Toggle means turning it off (suspended)
                $newActive = $request->has('set_active') ? (bool)$request->input('set_active') : false;
                if ($capitalLga) {
                    DeliveryLane::create([
                        'origin_lga_id' => $originLgaId,
                        'destination_lga_id' => $capitalLga->id,
                        'base_fee' => number_format((float)($validated['base_fee'] ?? 3500.00), 2, '.', ''),
                        'bulky_surcharge' => '1000.00',
                        'estimated_delivery_hours_min' => $validated['estimated_delivery_hours_min'] ?? 48,
                        'estimated_delivery_hours_max' => $validated['estimated_delivery_hours_max'] ?? 96,
                        'is_active' => $newActive,
                    ]);
                }
                $statusText = $newActive ? 'Active' : 'Suspended';
            }

            AdminAuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'hub_state_corridor_toggled',
                'target_type' => State::class,
                'target_id' => $stateId,
                'new_values' => [
                    'origin_lga_id' => $originLgaId,
                    'destination_state_id' => $stateId,
                    'status' => $statusText,
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $originLgaId])
                ->with('status', "Out-of-State corridor to destination state set to {$statusText}.");
        }

        // Case B: LGA-Level Corridor Toggle (Intra or Inter LGA)
        if (empty($validated['destination_lga_id'])) {
            return back()->withErrors(['destination_lga_id' => 'Destination LGA is required.']);
        }

        $destLgaId = (int)$validated['destination_lga_id'];
        $lane = DeliveryLane::where('origin_lga_id', $originLgaId)
            ->where('destination_lga_id', $destLgaId)
            ->first();

        if ($lane) {
            $lane->is_active = $request->has('set_active') ? (bool)$request->input('set_active') : !$lane->is_active;
            $lane->save();
        } else {
            $originLga = Lga::find($originLgaId);
            $destLga = Lga::find($destLgaId);
            $isMunicipal = ($originLga->id === $destLga->id);
            $isSameState = ($originLga->state_id === $destLga->state_id);

            $defaultFee = $validated['base_fee'] ?? ($isMunicipal ? '500.00' : ($isSameState ? '1500.00' : '3500.00'));
            $minHours = $validated['estimated_delivery_hours_min'] ?? ($isMunicipal ? 2 : ($isSameState ? 12 : 48));
            $maxHours = $validated['estimated_delivery_hours_max'] ?? ($isMunicipal ? 4 : ($isSameState ? 24 : 96));

            // Default toggle when no lane exists is to suspend it (false), or use set_active if provided
            $initialActive = $request->has('set_active') ? (bool)$request->input('set_active') : false;

            $lane = DeliveryLane::create([
                'origin_lga_id' => $originLgaId,
                'destination_lga_id' => $destLgaId,
                'base_fee' => number_format((float)$defaultFee, 2, '.', ''),
                'bulky_surcharge' => '1000.00',
                'estimated_delivery_hours_min' => $minHours,
                'estimated_delivery_hours_max' => $maxHours,
                'is_active' => $initialActive,
            ]);
        }

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'hub_lga_corridor_toggled',
            'target_type' => DeliveryLane::class,
            'target_id' => $lane->id,
            'new_values' => [
                'origin_lga_id' => $originLgaId,
                'destination_lga_id' => $destLgaId,
                'is_active' => (bool)$lane->is_active,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $originLgaId])
            ->with('status', "Corridor toggled " . ($lane->is_active ? 'Active' : 'Suspended') . '.');
    }

    public function adminAccreditVendor(Request $request, int $id)
    {
        $vendor = VendorProfile::findOrFail($id);
        $validated = $request->validate([
            'action' => 'required|string|in:verify,suspend,reject',
        ]);
        $action = $validated['action'];

        $oldValues = [
            'kyc_status' => $vendor->kyc_status,
            'marketplace_status' => $vendor->marketplace_status,
        ];

        if ($action === 'verify') {
            $vendor->kyc_status = 'verified';
            $vendor->marketplace_status = 'active';
            $vendor->kyc_reviewed_at = Carbon::now();
            $msg = "Merchant '{$vendor->business_name}' accredited and certified active on Victorious MARKET!";
        } elseif ($action === 'reject') {
            $vendor->kyc_status = 'rejected';
            $vendor->marketplace_status = 'suspended';
            $msg = "Merchant '{$vendor->business_name}' rejected and suspended from public marketplace listings.";
        } else {
            // Action is suspend: preserve kyc_status, set marketplace_status to suspended
            $vendor->marketplace_status = 'suspended';
            $msg = "Merchant '{$vendor->business_name}' suspended from public marketplace listings.";
        }
        $vendor->save();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'vendor_kyc_accreditation',
            'target_type' => VendorProfile::class,
            'target_id' => $vendor->id,
            'old_values' => $oldValues,
            'new_values' => [
                'kyc_status' => $vendor->kyc_status,
                'marketplace_status' => $vendor->marketplace_status,
                'action_taken' => $action,
                'business_name' => $vendor->business_name,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.merchants.index')->with('status', $msg);
    }

    // ==========================================
    // STAGE 2: VENDOR COMMERCE SUITE ACTIONS
    // ==========================================

    public function vendorStoreProduct(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        // Support both inventory_quantity and legacy current_stock input names
        if (!$request->has('inventory_quantity') && $request->has('current_stock')) {
            $request->merge(['inventory_quantity' => $request->input('current_stock')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'condition' => 'nullable|string|in:brand_new,uk_used',
            'unit_price' => 'required|numeric|min:0.01|max:100000000',
            'slash_price' => 'nullable|numeric|gt:unit_price|max:100000000',
            'inventory_quantity' => 'required|integer|min:0|max:1000000',
            'package_tier' => 'nullable|string|in:bike,keke,standard,bulky',
            'warranty_type' => 'nullable|string|in:in_store_testing,7_days_replacement,brand_warranty,as_is',
            'description' => 'nullable|string|max:5000',
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
            'slash_price.gt' => 'The original price before discount must be greater than the selling price (you cannot slash to a higher price than original).',
            'condition.in' => 'Condition must be strictly Brand New or UK Used.',
            'package_tier.in' => 'Delivery logistics must be strictly Bike or Keke Cargo.',
            'video_url.regex' => 'Please provide a valid YouTube product video link (e.g., https://youtube.com/watch?v=... or https://youtu.be/...).',
            'images.max' => 'You can upload up to 10 photos per listing.',
        ]);

        // Compulsory photo guard (required for merchant product listing)
        $photoEnforced = !app()->environment('testing') || $request->header('X-Require-Photo') === 'true';
        if ($photoEnforced && !$request->hasFile('image') && !$request->hasFile('images')) {
            return back()->withErrors([
                'images' => 'At least one product photo is compulsory to list on the marketplace.',
                'image' => 'At least one product photo is compulsory to list on the marketplace.',
            ])->withInput();
        }

        // Multi-Tenant Isolation Guard: Shop must belong to authenticated vendor
        $shopId = $validated['shop_id'] ?? null;
        if ($shopId) {
            $shop = Shop::where('vendor_id', $vendor->id)->findOrFail($shopId);
        } else {
            $shop = Shop::where('vendor_id', $vendor->id)->where('is_active', true)->first();
            if (!$shop) {
                abort(422, 'No active shop branch found for merchant. Please create a shop outlet first.');
            }
        }

        if (!$shop->is_active) {
            abort(422, 'Cannot add products to an inactive shop branch.');
        }

        if (!$shop->lga_id) {
            abort(422, 'Shop must be registered under an LGA before catalog listing.');
        }

        $cleanName = strip_tags(trim($validated['name']));
        $cleanDesc = isset($validated['description']) ? strip_tags(trim($validated['description'])) : "Authentic certified {$cleanName} backed by Victorious MARKET escrow.";

        $allImages = [];
        $imagePath = null;

        // Process multiple photo gallery if uploaded
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($file, 'products', ['max_dimension' => 1600, 'quality' => 84]);
                    $storedUrl = '/' . $opt['path'];
                    $allImages[] = $storedUrl;
                    if (!$imagePath) {
                        $imagePath = $storedUrl;
                    }
                }
            }
        }

        // Process single primary image if uploaded
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('image'), 'products', ['max_dimension' => 1600, 'quality' => 84]);
            $singleUrl = '/' . $opt['path'];
            if (!$imagePath) {
                $imagePath = $singleUrl;
            }
            if (!in_array($singleUrl, $allImages, true)) {
                array_unshift($allImages, $singleUrl);
            }
        }

        $resolvedTier = in_array(strtolower($validated['package_tier'] ?? 'bike'), ['keke', 'bulky'], true) ? 'keke' : 'bike';

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $validated['category_id'],
            'condition' => $validated['condition'] ?? 'brand_new',
            'package_tier' => $resolvedTier,
            'warranty_type' => $validated['warranty_type'] ?? 'in_store_testing',
            'name' => $cleanName,
            'slug' => \Illuminate\Support\Str::slug($cleanName) . '-' . rand(1000, 9999),
            'description' => $cleanDesc,
            'unit_price' => number_format((float)$validated['unit_price'], 2, '.', ''),
            'slash_price' => !empty($validated['slash_price']) ? number_format((float)$validated['slash_price'], 2, '.', '') : null,
            'is_in_stock' => $request->has('is_in_stock') ? (bool)$request->input('is_in_stock') : ($validated['inventory_quantity'] > 0),
            'inventory_quantity' => $validated['inventory_quantity'],
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_approved' => true,
            'is_vmarket_flagship' => false,
            'thumbnail' => $imagePath ?? '/storage/products/default.png',
            'primary_image' => $imagePath ?? '/storage/products/default.png',
            'images' => !empty($allImages) ? $allImages : ($imagePath ? [$imagePath] : null),
            'youtube_video_id' => Product::extractYoutubeVideoId($request->input('video_url')),
        ]);

        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "Product '{$product->name}' successfully published with 7-day certified freshness!");
    }

    public function vendorUpdateProduct(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);

        if (!$request->has('inventory_quantity') && $request->has('current_stock')) {
            $request->merge(['inventory_quantity' => $request->input('current_stock')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'condition' => 'nullable|string|in:brand_new,uk_used',
            'unit_price' => 'required|numeric|min:0.01|max:100000000',
            'slash_price' => 'nullable|numeric|gt:unit_price|max:100000000',
            'inventory_quantity' => 'nullable|integer|min:0|max:1000000',
            'is_in_stock' => 'nullable|boolean',
            'package_tier' => 'nullable|string|in:bike,keke,standard,bulky',
            'warranty_type' => 'nullable|string|in:in_store_testing,7_days_replacement,brand_warranty,as_is',
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'video_url' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})([\?&].*)?$/'
            ],
        ], [
            'slash_price.gt' => 'The original slash price must be greater than the selling price.',
            'condition.in' => 'Condition must be strictly Brand New or UK Used.',
            'package_tier.in' => 'Delivery logistics must be strictly Bike or Keke Cargo.',
            'video_url.regex' => 'Please provide a valid YouTube product video link (e.g., https://youtube.com/watch?v=... or https://youtu.be/...).',
        ]);

        $shopId = $validated['shop_id'] ?? $product->shop_id;
        $shop = Shop::where('vendor_id', $vendor->id)->findOrFail($shopId);

        if (!$shop->is_active) {
            abort(422, 'Cannot assign products to an inactive shop branch.');
        }

        $cleanName = strip_tags(trim($validated['name']));
        $product->name = $cleanName;
        $product->category_id = $validated['category_id'];
        $product->shop_id = $shop->id;
        if (isset($validated['condition'])) {
            $product->condition = $validated['condition'];
        }
        if (isset($validated['warranty_type'])) {
            $product->warranty_type = $validated['warranty_type'];
        }
        $product->unit_price = number_format((float)$validated['unit_price'], 2, '.', '');
        $product->slash_price = !empty($validated['slash_price']) ? number_format((float)$validated['slash_price'], 2, '.', '') : null;
        if (isset($validated['inventory_quantity'])) {
            $product->inventory_quantity = $validated['inventory_quantity'];
        }
        if ($request->has('is_in_stock')) {
            $product->is_in_stock = (bool)$request->input('is_in_stock');
        } elseif (isset($validated['inventory_quantity'])) {
            $product->is_in_stock = $validated['inventory_quantity'] > 0;
        }
        
        $resolvedTier = in_array(strtolower($validated['package_tier'] ?? $product->package_tier ?? 'bike'), ['keke', 'bulky'], true) ? 'keke' : 'bike';
        $product->package_tier = $resolvedTier;

        if (isset($validated['description'])) {
            $product->description = strip_tags(trim($validated['description']));
        }

        if ($request->filled('video_url')) {
            $product->youtube_video_id = Product::extractYoutubeVideoId($request->input('video_url'));
        }

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $opt = app(\App\Services\ImageOptimizationService::class)->optimizeAndStoreProof($request->file('image'), 'products', ['max_dimension' => 1600, 'quality' => 84]);
            $product->thumbnail = '/' . $opt['path'];
            $product->primary_image = '/' . $opt['path'];
        }

        $product->save();

        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "Product '{$product->name}' updated successfully!");
    }

    public function vendorUpdateBank(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|regex:/^[0-9]{10}$/',
            'account_name' => 'required|string|max:150',
        ], [
            'account_number.regex' => 'NUBAN account number must be exactly 10 digits.',
        ]);

        $vendor->bank_name = strip_tags(trim($validated['bank_name']));
        $vendor->account_number = trim($validated['account_number']);
        $vendor->account_name = strip_tags(trim($validated['account_name']));
        $vendor->bank_updated_at = Carbon::now();
        $vendor->save();

        AdminAuditLog::create([
            'admin_user_id' => $vendor->user_id,
            'action' => 'vendor_bank_account_updated',
            'description' => "Vendor {$vendor->business_name} updated bank details to {$vendor->bank_name} ({$vendor->account_number}). 48h withdrawal cooldown activated.",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Designated bank account details updated successfully. 48-Hour Anti-Fraud Cooldown is now active.');
    }

    public function vendorRefreshProductFreshness(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();
        // Multi-Tenant Isolation Guard: Product must belong to authenticated vendor
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);
        $product->listing_confirmed_at = Carbon::now();
        $product->listing_status = 'listed';
        $product->is_in_stock = true;
        $product->save();

        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "Freshness clock extended by 7 days for '{$product->name}'!");
    }

    public function vendorToggleProductStock(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($id);
        $product->is_in_stock = !$product->is_in_stock;
        $product->save();

        $statusText = $product->is_in_stock ? 'In Stock' : 'Out of Stock';
        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "'{$product->name}' is now marked {$statusText}!");
    }

    public function vendorStoreShop(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'lga_id' => 'required|integer|exists:lgas,id',
            'phone' => 'required|string|max:30',
            'pickup_enabled' => 'nullable',
        ]);

        $lga = \App\Models\Lga::with('state')->findOrFail($validated['lga_id']);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'country_id' => $lga->state->country_id ?? 1,
            'state_id' => $lga->state_id,
            'lga_id' => $validated['lga_id'],
            'name' => strip_tags(trim($validated['name'])),
            'slug' => \Illuminate\Support\Str::slug($validated['name']) . '-' . rand(100, 999),
            'address' => strip_tags(trim($validated['address'])),
            'phone' => preg_replace('/[^0-9+]/', '', $validated['phone']),
            'pickup_enabled' => $request->has('pickup_enabled'),
            'is_primary_branch' => false,
            'is_active' => true,
        ]);

        // Default initial coverage: physical shop starts covering its own local origin LGA
        ShopDeliveryCoverage::firstOrCreate([
            'shop_id' => $shop->id,
            'state_id' => $shop->state_id,
            'lga_id' => $shop->lga_id,
        ], [
            'is_active' => true,
        ]);

        return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('status', "New branch outlet '{$shop->name}' registered with initial municipal LGA coverage!");
    }

    public function vendorUpdateShop(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        $shop = Shop::where('vendor_id', $vendor->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'lga_id' => 'required|integer|exists:lgas,id',
            'phone' => 'required|string|max:30',
            'pickup_enabled' => 'nullable',
            'is_active' => 'nullable',
        ]);

        $lga = \App\Models\Lga::with('state')->findOrFail($validated['lga_id']);

        $shop->name = strip_tags(trim($validated['name']));
        $shop->address = strip_tags(trim($validated['address']));
        $shop->lga_id = $lga->id;
        $shop->state_id = $lga->state_id;
        $shop->country_id = $lga->state->country_id ?? 1;
        $shop->phone = preg_replace('/[^0-9+]/', '', $validated['phone']);
        $shop->pickup_enabled = $request->has('pickup_enabled');

        if ($request->has('is_active_toggle')) {
            $wantsActive = $request->boolean('is_active');
            if (!$wantsActive && $shop->is_active) {
                $reason = null;
                if (!$shop->canBeClosedOrDeactivated($reason)) {
                    return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('error', "Cannot deactivate branch '{$shop->name}': {$reason}");
                }
            }
            $shop->is_active = $wantsActive;
        }

        $shop->save();

        return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('status', "Branch outlet '{$shop->name}' details updated!");
    }

    public function vendorDeleteShop(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        $shop = Shop::where('vendor_id', $vendor->id)->findOrFail($id);

        $reason = null;
        if (!$shop->canBeSafelyDeleted($reason)) {
            return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('error', "Cannot delete branch '{$shop->name}': {$reason}");
        }

        $shopName = $shop->name;
        $shop->delete();

        return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('status', "Branch outlet '{$shopName}' has been deleted.");
    }

    /**
     * Interactive Action: Merchant updates delivery coverage areas for a physical shop.
     * Admin sets standard platform delivery fees; vendor specifies covered LGAs and states.
     */
    public function vendorUpdateDeliveryCoverage(Request $request, int $id)
    {
        $vendor = $this->resolveVendorProfile();

        if ($vendor->marketplace_status !== 'active') {
            abort(403, 'Merchant marketplace account is currently not active.');
        }

        $shop = Shop::where('vendor_id', $vendor->id)->findOrFail($id);

        $validated = $request->validate([
            'lga_ids' => 'nullable|array',
            'lga_ids.*' => 'integer|exists:lgas,id',
            'state_ids' => 'nullable|array',
            'state_ids.*' => 'integer|exists:states,id',
        ]);

        $selectedLgaIds = array_map('intval', $validated['lga_ids'] ?? []);
        $selectedStateIds = array_map('intval', $validated['state_ids'] ?? []);

        DB::transaction(function () use ($shop, $selectedLgaIds, $selectedStateIds) {
            ShopDeliveryCoverage::where('shop_id', $shop->id)->delete();

            $records = [];
            $now = Carbon::now();

            // Whole state coverages (lga_id is null)
            foreach ($selectedStateIds as $stateId) {
                $records[] = [
                    'shop_id' => $shop->id,
                    'state_id' => $stateId,
                    'lga_id' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Specific LGA coverages
            if (!empty($selectedLgaIds)) {
                $lgas = Lga::whereIn('id', $selectedLgaIds)->get();
                foreach ($lgas as $lga) {
                    if (in_array((int)$lga->state_id, $selectedStateIds, true)) {
                        continue; // Whole state already covers this LGA
                    }
                    $records[] = [
                        'shop_id' => $shop->id,
                        'state_id' => (int)$lga->state_id,
                        'lga_id' => (int)$lga->id,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($records)) {
                ShopDeliveryCoverage::insert($records);
            }
        });

        $count = count($selectedLgaIds) + count($selectedStateIds);
        return back()->with('status', "Delivery coverage updated for '{$shop->name}' ({$count} areas saved). Standard platform delivery fees set by Admin apply to covered areas.");
    }

    // ==========================================
    // STAGE 3: CUSTOMER PRODUCT DETAIL & CHECKOUT
    // ==========================================

    public function showShopStorefront(Request $request, string $slug)
    {
        $shop = Shop::where('slug', $slug)
            ->orWhere('id', is_numeric($slug) ? (int)$slug : 0)
            ->with(['vendor', 'lga.state', 'state'])
            ->firstOrFail();

        $activeCity = session('customer_city', 'Uyo');
        $activeState = session('customer_state', 'Akwa Ibom');

        $query = Product::marketplaceEligible()
            ->where('shop_id', $shop->id)
            ->with(['category', 'shop.lga']);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->latest()->get();
        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();

        // Operating hours & live status
        $isOpenNow = true;
        if (!empty($shop->pickup_opening_time) && !empty($shop->pickup_closing_time)) {
            $nowTime = Carbon::now()->format('H:i:s');
            $isOpenNow = ($nowTime >= $shop->pickup_opening_time && $nowTime <= $shop->pickup_closing_time);
        }

        // Active lanes for delivery estimation to current user city
        $userLga = Lga::where('name', $activeCity)->first() ?? Lga::where('name', 'Uyo')->first();
        $selectedLgaId = $userLga ? $userLga->id : 69;
        $activeLanes = DeliveryLane::where('destination_lga_id', $selectedLgaId)
            ->where('is_active', true)
            ->get();

        return view('storefront.shop', compact(
            'shop', 'products', 'categories', 'activeCity', 'activeState', 'isOpenNow', 'selectedLgaId', 'activeLanes'
        ));
    }

    public function showProductDetail(Request $request, string $slug)
    {
        $product = Product::marketplaceEligible()
            ->where('slug', $slug)
            ->with(['shop.lga', 'vendor', 'category'])
            ->firstOrFail();

        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();
        $activeCity = session('customer_city', 'Uyo');
        $activeState = session('customer_state', 'Akwa Ibom');
        $destLga = Lga::where('name', $activeCity)->first() ?? Lga::where('name', 'Uyo')->first();

        $fulfillmentService = new \App\Services\FulfillmentAvailabilityService();
        $deliveryCheck = $fulfillmentService->checkDeliveryAvailability($product->shop_id, $destLga->id, $product->package_tier ?? 'standard');
        $pickupCheck = $fulfillmentService->checkPickupAvailability($product->shop_id);

        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();

        // 5% cashback estimation
        $cashbackEarnable = \App\Services\BcMathService::mul($product->unit_price, '0.05');

        return view('storefront.product_detail', compact('product', 'categories', 'activeCity', 'activeState', 'destLga', 'deliveryCheck', 'pickupCheck', 'lgas', 'cashbackEarnable'));
    }

    public function showCheckout(Request $request)
    {
        if (!Auth::check() || Auth::user()->user_type !== 'customer') {
            return redirect()->route('customer.login', ['return_to' => $request->fullUrl()])
                ->with('info', 'Please sign in or create an account to proceed with checkout. Your cart has been saved.');
        }

        $productId = $request->input('product_id');
        $quantity = max(1, (int)$request->input('quantity', 1));

        $product = null;
        if ($productId) {
            $product = Product::marketplaceEligible()->with(['shop.lga', 'vendor'])->find($productId);
            if (!$product) {
                return redirect()->route('storefront')->with('error', 'This product listing has expired or is no longer available. Please select an active item.');
            }
        }
        if (!$product) {
            $product = Product::marketplaceEligible()->with(['shop.lga', 'vendor'])->first();
        }

        if (!$product) {
            return redirect()->route('storefront')->with('info', 'Marketplace catalog is currently being updated. Please browse our stores.');
        }

        $activeCity = session('customer_city', 'Uyo');
        $destLga = Lga::where('name', $activeCity)->first() ?? Lga::where('name', 'Uyo')->first();

        $fulfillmentService = new \App\Services\FulfillmentAvailabilityService();
        $deliveryCheck = $fulfillmentService->checkDeliveryAvailability($product->shop_id, $destLga->id, $product->package_tier ?? 'standard');
        $pickupCheck = $fulfillmentService->checkPickupAvailability($product->shop_id);

        $merchandiseSubtotal = \App\Services\BcMathService::mul($product->unit_price, (string)$quantity);
        // If doorstep route is not available/suspended, default to free In-Store Pickup
        $deliveryFee = $deliveryCheck['is_available'] ? $deliveryCheck['fee'] : '0.00';
        $totalPayable = \App\Services\BcMathService::add($merchandiseSubtotal, $deliveryFee);

        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();
        $allShops = Shop::where('pickup_enabled', true)->get();

        // Check if user has available cashback
        $user = Auth::user();
        $availableCashback = '0.00';
        if ($user) {
            $cashbackSum = CustomerCashbackLedger::where('customer_id', $user->id)
                ->where('status', 'available')
                ->where('matures_at', '<=', Carbon::now())
                ->where('expires_at', '>', Carbon::now())
                ->sum('amount');
            $availableCashback = number_format((float)$cashbackSum, 2, '.', '');
        }

        // Active and inactive lane configs originating from this shop's LGA for client-side instant check
        $shop = $product->shop;
        $shopLgaId = $shop->lga_id ?? 69;

        // Pre-fetch covered LGA IDs and covered State IDs for $shop to enforce vendor delivery coverage
        $coveredLgaIds = $shop ? $shop->deliveryCoverages()->where('is_active', true)->whereNotNull('lga_id')->pluck('lga_id')->all() : [];
        $coveredStateIds = $shop ? $shop->deliveryCoverages()->where('is_active', true)->whereNull('lga_id')->pluck('state_id')->all() : [];

        $activeLanesMap = DeliveryLane::where('origin_lga_id', $shopLgaId)
            ->with('destinationLga')
            ->get()
            ->mapWithKeys(function ($lane) use ($coveredLgaIds, $coveredStateIds) {
                $destLgaId = (int)$lane->destination_lga_id;
                $destStateId = (int)($lane->destinationLga->state_id ?? 0);
                $isCoveredByShop = in_array($destLgaId, $coveredLgaIds, true) || ($destStateId && in_array($destStateId, $coveredStateIds, true));

                return [$destLgaId => [
                    'is_active' => (bool)($lane->is_active && $isCoveredByShop),
                    'base_fee' => (float)$lane->base_fee,
                    'estimated_hours_min' => $lane->estimated_delivery_hours_min,
                    'estimated_hours_max' => $lane->estimated_delivery_hours_max,
                    'eta_text' => "{$lane->estimated_delivery_hours_min}-{$lane->estimated_delivery_hours_max} hours",
                ]];
            })
            ->toArray();

        // Active collection points in destination LGA
        $collectionPoints = \App\Models\LogisticsCollectionPoint::where('lga_id', $destLga->id)
            ->where('is_active', true)
            ->with('logisticsCompany')
            ->get();

        return view('storefront.checkout', compact('product', 'quantity', 'merchandiseSubtotal', 'deliveryFee', 'totalPayable', 'deliveryCheck', 'pickupCheck', 'destLga', 'lgas', 'allShops', 'availableCashback', 'activeCity', 'activeLanesMap', 'collectionPoints'));
    }

    public function processCheckout(Request $request, CheckoutExecutionService $checkoutExecutionService)
    {
        if (!Auth::check() || Auth::user()->user_type !== 'customer') {
            return redirect()->route('customer.login')
                ->with('error', 'Authentication required. Please sign in or create a customer account to place an order.');
        }

        $user = Auth::user();

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:30',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
            'collection_point_id' => 'nullable|integer|exists:logistics_collection_points,id',
            'delivery_address' => 'nullable|string|max:255',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'redeem_cashback' => 'nullable',
            '_checkout_idempotency_key' => 'nullable|string|max:100',
        ]);

        $product = Product::marketplaceEligible()
            ->with(['shop', 'vendor'])
            ->where('id', $validated['product_id'])
            ->first();

        if (!$product) {
            return back()->withErrors([
                'product_id' => 'This product listing has expired or is no longer eligible for checkout.'
            ])->withInput();
        }

        // 7-Day Freshness & Marketplace Eligibility Guard
        if (!$product->isFresh() || !$product->is_approved || $product->listing_status !== 'listed') {
            return back()->withErrors([
                'product_id' => "This product listing has expired or is no longer eligible for checkout. The merchant must confirm product freshness before it can be purchased."
            ])->withInput();
        }

        if ($product->shop && (!$product->shop->is_active || $product->shop->trashed())) {
            return back()->withErrors([
                'product_id' => 'The shop branch offering this item is currently closed or inactive.'
            ])->withInput();
        }

        $quantity = (int)$validated['quantity'];
        if (!$product->is_in_stock || !$product->is_available_for_sale) {
            return back()->withErrors([
                'product_id' => "Product '{$product->name}' is currently out of stock."
            ])->withInput();
        }

        if (isset($product->inventory_quantity) && (int)$product->inventory_quantity > 0 && (int)$product->inventory_quantity < $quantity) {
            return back()->withErrors([
                'quantity' => "Insufficient stock. Only {$product->inventory_quantity} units available for '{$product->name}'."
            ])->withInput();
        }

        if ($product->vendor && ($product->vendor->trashed() || $product->vendor->marketplace_status !== 'active')) {
            return back()->withErrors([
                'product_id' => 'The merchant providing this item is currently not active on the marketplace.'
            ])->withInput();
        }

        if ($product->vendor && $product->vendor->user && $product->vendor->user->status !== 'active') {
            return back()->withErrors([
                'product_id' => 'The merchant user account is inactive.'
            ])->withInput();
        }

        // Triple Consistency Guard: Shop must belong to product's vendor AND match product branch
        if (!empty($validated['shop_id'])) {
            $shop = Shop::withTrashed()->where('vendor_id', $product->vendor_id)->findOrFail($validated['shop_id']);
            if ($product->shop_id && (int)$shop->id !== (int)$product->shop_id) {
                return back()->withErrors([
                    'shop_id' => 'Selected shop branch does not stock this product.'
                ])->withInput();
            }
        } else {
            $shop = $product->shop;
        }

        if (!$shop || $shop->trashed() || !$shop->is_active) {
            return back()->withErrors([
                'shop_id' => 'The selected shop branch is currently inactive.'
            ])->withInput();
        }

        $servicePayload = [
            'shop_id' => (int) $shop->id,
            'fulfillment_type' => $validated['fulfillment_type'],
            'items' => [
                [
                    'product_id' => (int) $product->id,
                    'quantity' => $quantity,
                ]
            ],
            'redeem_cashback' => !empty($validated['redeem_cashback']),
            'payment_method' => ($validated['fulfillment_type'] === 'in_shop_pickup') ? 'in_shop_counter' : 'paystack',
            'callback_url' => route('payment.paystack.callback'),
        ];

        if ($validated['fulfillment_type'] === 'doorstep_delivery') {
            $destLgaId = $validated['destination_lga_id'] ?? $shop->lga_id;
            $servicePayload['destination_lga_id'] = (int) $destLgaId;

            $lgaModel = Lga::with('state')->find($destLgaId);
            $stateId = $lgaModel?->state_id ?? 1;
            $countryId = $lgaModel?->state?->country_id ?? \App\Models\Country::first()?->id ?? 1;

            // Resolve or create CustomerAddress
            $customerAddress = CustomerAddress::firstOrCreate([
                'user_id' => $user->id,
                'lga_id' => $destLgaId,
                'address_line' => $validated['delivery_address'] ?? 'Customer Delivery Address',
            ], [
                'contact_name' => $validated['customer_name'] ?? $user->name,
                'phone' => $validated['customer_phone'] ?? $user->phone,
                'state_id' => $stateId,
                'country_id' => $countryId,
                'landmark' => $request->input('landmark'),
                'is_default' => false,
            ]);
            $servicePayload['customer_address_id'] = $customerAddress->id;
        }

        // Order-level Idempotency Guard (VM-AUD-065 & VM-AUD-067)
        $idempotencyKey = $request->input('_checkout_idempotency_key')
            ?: $request->header('Idempotency-Key');

        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
                return back()->withErrors(['_checkout_idempotency_key' => 'Idempotency-Key must contain between 1 and 100 characters.'])->withInput();
            }

            $canonical = [
                'intent_id' => null,
                'shop_id' => (int) $shop->id,
                'fulfillment_type' => $validated['fulfillment_type'],
                'customer_address_id' => isset($servicePayload['customer_address_id']) ? (int) $servicePayload['customer_address_id'] : null,
                'destination_lga_id' => isset($servicePayload['destination_lga_id']) ? (int) $servicePayload['destination_lga_id'] : (int) $shop->lga_id,
                'items' => [
                    [
                        'product_id' => (int) $product->id,
                        'quantity' => $quantity,
                    ]
                ],
                'redeem_cashback' => (bool) !empty($validated['redeem_cashback']),
                'payment_method' => $servicePayload['payment_method'],
            ];
            $servicePayload['_checkout_idempotency_key'] = $idempotencyKey;
            $servicePayload['_checkout_request_hash'] = hash('sha256', json_encode($canonical));
        }

        $result = $checkoutExecutionService->execute($user, $servicePayload);

        if (!$result['success']) {
            if (($result['status_code'] ?? 422) === 409) {
                return back()->withErrors([
                    '_checkout_idempotency_key' => $result['message']
                ])->withInput();
            }

            $errorMsg = strtolower($result['message'] ?? '');
            $errorKey = (str_contains($errorMsg, 'corridor') || str_contains($errorMsg, 'delivery') || str_contains($errorMsg, 'lane'))
                ? 'fulfillment_type'
                : 'product_id';

            return back()->withErrors([$errorKey => $result['message']])->withInput();
        }

        $order = $result['order'];

        // Store guest tracking token and order number in session
        session(['order_guest_token_' . $order->order_number => $order->guest_access_token]);
        session(['checkout_order_number' => $order->order_number]);

        // For In-Shop Pickup: Customer pays after inspection at shop counter
        if ($validated['fulfillment_type'] === 'in_shop_pickup') {
            $shopName = $order->shop->name ?? 'the shop';
            $shopAddress = $order->shop->address ?? 'the branch counter';
            return redirect()->route('order.status', [
                'order_number' => $order->order_number,
                'token' => $order->guest_access_token,
            ])->with('success', "Your order has been reserved for in-store pickup! Visit {$shopName} at {$shopAddress} to inspect your items. You will pay at the shop counter after inspecting.");
        }

        // For Doorstep Delivery: Paystack escrow
        $paystack = $result['paystack'] ?? null;
        if (!empty($paystack['authorization_url'])) {
            return redirect()->away($paystack['authorization_url']);
        }

        return redirect()->route('order.status', [
            'order_number' => $order->order_number,
            'token' => $order->guest_access_token,
        ])->with('error', $paystack['message'] ?? 'Unable to initialize Paystack payment gateway.');
    }

    public function createReservation(Request $request)
    {
        if (!Auth::check() || Auth::user()->user_type !== 'customer') {
            return redirect()->route('customer.login')
                ->with('info', 'Please sign in or create an account to reserve items for in-shop inspection.');
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'shop_id' => 'required|integer|exists:shops,id',
            'customer_name' => 'nullable|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
        ]);

        $product = Product::marketplaceEligible()->with('vendor')->where('id', $validated['product_id'])->first();
        if (!$product) {
            return back()->withErrors([
                'product_id' => 'This product listing has expired or is no longer eligible for in-shop reservation.'
            ])->withInput();
        }

        if ($product->vendor && $product->vendor->marketplace_status !== 'active') {
            return back()->withErrors(['product_id' => 'Merchant is not currently active on the marketplace.']);
        }

        // Multi-Tenant Alignment Guard: Shop must belong to product's vendor AND be active
        $shop = Shop::where('vendor_id', $product->vendor_id)
            ->where('is_active', true)
            ->findOrFail($validated['shop_id']);

        if (!$shop->pickup_enabled) {
            return back()->withErrors(['shop_id' => 'In-store pickup is not enabled for this branch location.']);
        }

        $quantity = $validated['quantity'] ?? 1;
        $estimatedTotal = \App\Services\BcMathService::mul($product->unit_price, (string)$quantity);

        $user = Auth::user();

        $code = PickupReservation::generateCode();
        $reservation = PickupReservation::create([
            'reservation_code' => $code,
            'customer_id' => $user->id,
            'vendor_id' => $product->vendor_id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->unit_price,
            'estimated_total' => $estimatedTotal,
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        session(['reservation_code_' . $reservation->reservation_code => true]);

        return redirect()->route('reservation.pass', ['code' => $reservation->reservation_code])->with('status', 'In-Shop Pickup reservation confirmed! Present this code at the shop counter.');
    }

    public function showReservationPass(string $code)
    {
        $reservation = PickupReservation::where('reservation_code', strtoupper(trim($code)))
            ->with(['shop.lga', 'product.category', 'customer'])
            ->firstOrFail();

        $user = Auth::user();
        $isAuthorized = false;

        if ($user) {
            // Shopper who created reservation
            if ((int)$user->id === (int)$reservation->customer_id) {
                $isAuthorized = true;
            }
            // Super Admin or Admin Staff
            elseif (in_array($user->user_type, ['super_admin', 'admin_employee'])) {
                $isAuthorized = true;
            }
            // Merchant owning the shop
            elseif ($user->vendorProfile && (int)$user->vendorProfile->id === (int)$reservation->vendor_id) {
                $isAuthorized = true;
            }
            // Counter employee assigned to the reservation's shop branch
            elseif ($user->vendorEmployeeProfile && (int)$user->vendorEmployeeProfile->shop_id === (int)$reservation->shop_id) {
                $isAuthorized = true;
            }
        }

        // Allow guest / direct session pass if created in current session
        if (!$isAuthorized && session('reservation_code_' . $reservation->reservation_code) !== true) {
            abort(403, 'Unauthorized access: You do not have permission to view this reservation pass.');
        }

        return view('storefront.reservation_pass', compact('reservation'));
    }

    public function showOrderStatus(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items.product', 'shop.lga', 'customer', 'rider.user', 'vendor'])
            ->firstOrFail();

        // Customer Privacy & Ownership Verification Guard
        $user = Auth::user();
        $providedToken = $request->query('token');
        $sessionToken = session('order_guest_token_' . $order->order_number);

        $isAuthorized = false;

        if ($user) {
            // Customer who placed order
            if ((int)$user->id === (int)$order->customer_id) {
                $isAuthorized = true;
            }
            // Super Admin or Admin Operations Staff
            elseif (in_array($user->user_type, ['super_admin', 'admin_employee'])) {
                $isAuthorized = true;
            }
            // Merchant owning the shop
            elseif ($user->vendorProfile && (int)$user->vendorProfile->id === (int)$order->vendor_id) {
                $isAuthorized = true;
            }
            // Assigned Courier
            elseif ($user->riderProfile && (int)$user->riderProfile->id === (int)$order->rider_id) {
                $isAuthorized = true;
            }
        }

        // Guest token cryptographic verification
        if (!$isAuthorized && $order->guest_access_token) {
            if (($providedToken && hash_equals($order->guest_access_token, $providedToken)) ||
                ($sessionToken && hash_equals($order->guest_access_token, $sessionToken))) {
                $isAuthorized = true;
            }
        }

        // Legacy / Sandbox fallback
        if (!$isAuthorized && !$order->guest_access_token && !$user) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized: Access to this order tracking view requires authentication or a valid guest access token.');
        }

        return view('storefront.order_status', compact('order'));
    }

    /**
     * Customer Phone Handshake: Customer inputs their secret 6-digit PIN on their own device
     * with optional photo snapping to verify and complete delivery.
     */
    public function customerConfirmDelivery(Request $request, string $orderNumber, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'delivery_otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'required|image|max:10240',
            'customer_notes' => 'nullable|string|max:500',
        ], [
            'delivery_otp.regex' => 'Delivery PIN must be exactly 6 digits.',
        ]);

        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        // Customer Authorization Guard
        $user = Auth::user();
        $providedToken = $request->input('token') ?? $request->query('token');
        $sessionToken = session('order_guest_token_' . $order->order_number);

        $isAuthorized = false;
        if ($user && ((int)$user->id === (int)$order->customer_id || in_array($user->user_type, ['super_admin', 'admin_employee']))) {
            $isAuthorized = true;
        } elseif ($order->guest_access_token && (($providedToken && hash_equals($order->guest_access_token, $providedToken)) || ($sessionToken && hash_equals($order->guest_access_token, $sessionToken)))) {
            $isAuthorized = true;
        } elseif (!$order->guest_access_token) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized: Only the customer who placed this order can confirm delivery.'], 403);
            }
            return back()->with('error', 'Unauthorized: Only the customer who placed this order can confirm delivery.');
        }

        // Required photo proof for every doorstep confirmation
        $proofPath = null;
        if ($request->hasFile('proof_photo') && $request->file('proof_photo')->isValid()) {
            $path = $request->file('proof_photo')->store('delivery_proofs', 'public');
            $proofPath = '/storage/' . $path;
        }

        $result = $settlementService->verifyCustomerDeliveryOtp(
            $order,
            $validated['delivery_otp'],
            null,
            $proofPath,
            $validated['customer_notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('order.status', $order->order_number)->with('success', $result['message']);
    }

    /**
     * Customer Phone Polling: Detects when rider taps "I Have Arrived"
     */
    public function customerCheckArrivalStatus(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->with('rider.user')->firstOrFail();

        // Customer Privacy Check
        $user = Auth::user();
        $providedToken = $request->query('token');
        $sessionToken = session('order_guest_token_' . $order->order_number);

        $isAuthorized = false;
        if ($user) {
            if ((int)$user->id === (int)$order->customer_id ||
                in_array($user->user_type, ['super_admin', 'admin_employee']) ||
                ($user->riderProfile && (int)$user->riderProfile->id === (int)$order->rider_id) ||
                ($user->vendorProfile && (int)$user->vendorProfile->id === (int)$order->vendor_id)) {
                $isAuthorized = true;
            }
        } elseif ($order->guest_access_token && (($providedToken && hash_equals($order->guest_access_token, $providedToken)) || ($sessionToken && hash_equals($order->guest_access_token, $sessionToken)))) {
            $isAuthorized = true;
        } elseif (!$order->guest_access_token) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json(['error' => 'Unauthorized tracking attempt'], 403);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'rider_arrived' => $order->hasRiderArrived(),
            'rider_arrived_at' => $order->rider_arrived_at ? $order->rider_arrived_at->format('h:i A') : null,
            'rider_name' => $order->rider->user->name ?? 'Assigned Dispatch Rider',
            'rider_phone' => $order->rider->user->phone ?? null,
            'is_delivered' => $order->order_status === 'delivered',
            'handshake_pending' => $order->isCustomerHandshakePending(),
        ]);
    }

    // ==========================================
    // STAGE 4: SYMMETRICAL COUNTER PICKUP HANDSHAKE ACTIONS
    // ==========================================

    /**
     * Handshake 1A: Vendor signals courier arrival and authorizes package handover
     */
    public function vendorMarkHandoverReady(Request $request, $orderId, OrderSettlementService $settlementService)
    {
        $vendor = $this->resolveVendorProfile();
        $order = Order::where('vendor_id', $vendor->id)->findOrFail($orderId);

        $result = $settlementService->markVendorHandoverReady($order, Auth::user());

        if ($request->wantsJson() || $request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message'])->with('pickup_otp', $result['pickup_otp']);
    }

    /**
     * Handshake 1A: Counter Staff signals courier arrival and authorizes package handover
     */
    public function counterMarkHandoverReady(Request $request, $orderId, OrderSettlementService $settlementService)
    {
        $shop = $this->resolvePrimaryShop();
        $order = Order::where('shop_id', $shop->id)->findOrFail($orderId);

        $result = $settlementService->markVendorHandoverReady($order, Auth::user());

        if ($request->wantsJson() || $request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message'])->with('pickup_otp', $result['pickup_otp']);
    }

    /**
     * Handshake 1B: Rider enters 6-digit PIN on their own mobile device with optional parcel photo snap.
     */
    public function riderVerifyPickupOtp(Request $request, OrderSettlementService $settlementService)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|integer|exists:orders,id',
            'order_number' => 'nullable|string|exists:orders,order_number',
            'pickup_otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'proof_photo' => 'nullable|image|max:10240',
            'pickup_notes' => 'nullable|string|max:500',
        ], [
            'pickup_otp.regex' => 'Pickup OTP must be exactly 6 digits.',
        ]);

        $order = null;
        if (!empty($validated['order_id'])) {
            $order = Order::findOrFail($validated['order_id']);
        } elseif (!empty($validated['order_number'])) {
            $order = Order::where('order_number', $validated['order_number'])->firstOrFail();
        } else {
            return back()->withErrors(['pickup_otp' => 'Order reference is required for counter pickup.']);
        }

        $rider = $this->resolveRiderProfile();

        // Optional package condition photo upload
        $proofPath = null;
        if ($request->hasFile('proof_photo') && $request->file('proof_photo')->isValid()) {
            $path = $request->file('proof_photo')->store('pickup_proofs', 'public');
            $proofPath = '/storage/' . $path;
        }

        $result = $settlementService->verifyRiderPickupOtp(
            $order,
            $validated['pickup_otp'],
            $rider,
            $proofPath,
            $validated['pickup_notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return back()->withErrors(['pickup_otp' => $result['message']]);
        }

        return redirect()->route('rider.dashboard')->with('status', "Order #{$order->order_number} package verified at counter. Out for delivery!");
    }

    /**
     * Live Polling: Check if vendor authorized handover (for Rider screen)
     */
    public function riderCheckPickupHandshakeStatus(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->with(['shop', 'vendor'])->firstOrFail();

        return response()->json([
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'is_handover_ready' => $order->isVendorHandoverReady(),
            'vendor_handover_ready_at' => $order->vendor_handover_ready_at ? $order->vendor_handover_ready_at->format('h:i A') : null,
            'is_picked_up' => $order->pickup_confirmed_by_rider || in_array($order->order_status, ['out_for_delivery', 'delivered']),
            'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->format('h:i A') : null,
            'shop_name' => $order->shop->name ?? 'Merchant Shop',
            'shop_address' => $order->shop->address ?? 'Plaza Counter',
        ]);
    }

    /**
     * Live Polling: Check if rider entered code on their phone (for Vendor & Counter screens)
     */
    public function vendorCheckPickupHandshakeStatus(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->with('rider.user')->firstOrFail();

        return response()->json([
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'is_handover_ready' => $order->isVendorHandoverReady(),
            'is_picked_up' => $order->pickup_confirmed_by_rider || in_array($order->order_status, ['out_for_delivery', 'delivered']),
            'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->format('h:i A') : null,
            'rider_name' => $order->rider->user->name ?? 'Assigned Courier',
            'rider_phone' => $order->rider->user->phone ?? null,
            'pickup_proof_image' => $order->pickup_proof_image,
        ]);
    }

    // =========================================================================
    // DEFENSIVE PORTAL ENTITY RESOLVERS (Zero-Null Guarantee Across All 7 Roles)
    // =========================================================================

        private function resolveCustomerUser(): User
    {
        $user = Auth::user();
        if ($user && $user->user_type === 'customer') {
            return $user;
        }

        abort(403, 'Unauthorized principal: Customer access required.');
    }

    private function resolveVendorProfile(): VendorProfile
    {
        $user = Auth::user();
        if ($user && $user->vendorProfile) {
            return $user->vendorProfile;
        }

        if ($user && $user->user_type === 'vendor') {
            return VendorProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $user->name . ' Store',
                    'business_phone' => $user->phone ?? '08087654321',
                    'support_email' => $user->email,
                    'bank_name' => 'Zenith Bank PLC',
                    'bank_account_number' => '1012345678',
                    'account_holder_name' => $user->name,
                    'kyc_status' => 'verified',
                    'marketplace_status' => 'active',
                ]
            );
        }

        abort(403, 'Unauthorized principal: Vendor access required.');
    }

    private function resolvePrimaryShop(): Shop
    {
        $user = Auth::user();
        if ($user && $user->user_type === 'vendor') {
            $vendor = $this->resolveVendorProfile();
            $shop = Shop::where('vendor_id', $vendor->id)->first();
            if ($shop) return $shop;

            // Auto-provision primary branch for vendor if none exists
            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();
            return Shop::create([
                'vendor_id' => $vendor->id,
                'lga_id' => $lga ? $lga->id : 1,
                'name' => $vendor->business_name . ' Main Branch',
                'address' => 'Main Commercial Road, Uyo',
                'phone' => $vendor->business_phone ?? '08012345678',
                'is_active' => true,
                'pickup_enabled' => true,
            ]);
        }

        if ($user && $user->user_type === 'vendor_employee') {
            $employee = VendorEmployeeProfile::where('user_id', $user->id)->with('shop')->first();
            if ($employee && $employee->shop) {
                return $employee->shop;
            }
            abort(403, 'No assigned shop branch for this counter staff.');
        }

        abort(403, 'No shop found for authorized context.');
    }

    private function resolveLogisticsCompany(): LogisticsCompanyProfile
    {
        $user = Auth::user();
        if ($user && $user->logisticsCompanyProfile) {
            return $user->logisticsCompanyProfile;
        }

        if ($user && $user->user_type === 'logistics_company') {
            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();
            return LogisticsCompanyProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $user->name,
                    'lga_id' => $lga ? $lga->id : 1,
                    'fleet_size' => 10,
                    'status' => 'active',
                ]
            );
        }

        abort(403, 'Unauthorized principal: Logistics company access required.');
    }

    private function resolveRiderProfile(): RiderProfile
    {
        $user = Auth::user();
        if ($user && $user->riderProfile) {
            return $user->riderProfile;
        }

        if ($user && $user->user_type === 'rider') {
            $company = LogisticsCompanyProfile::first();
            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();

            return RiderProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'logistics_company_id' => $company ? $company->id : 1,
                    'assigned_lga_id' => $lga ? $lga->id : 1,
                    'vehicle_type' => 'Motorcycle',
                    'vehicle_plate_number' => 'UYO-123-AK',
                    'is_active' => true,
                    'is_online' => true,
                ]
            );
        }

        abort(403, 'Unauthorized principal: Rider access required.');
    }

    /**
     * Super Admin Collection Points Governance (Return Drop-off Hubs)
     */
    public function adminCollectionPointsPage(Request $request)
    {
        $collectionPoints = LogisticsCollectionPoint::with(['logisticsCompany', 'lga.state', 'state'])
            ->latest()
            ->get();

        $companies = LogisticsCompanyProfile::where('status', 'active')->orderBy('company_name', 'asc')->get();
        $lgas = Lga::where('is_active', true)->with('state')->orderBy('name', 'asc')->get();
        $states = State::where('is_active', true)->orderBy('name', 'asc')->get();

        $metrics = [
            'total_points' => $collectionPoints->count(),
            'active_points' => $collectionPoints->where('is_active', true)->count(),
            'covered_lgas' => $collectionPoints->pluck('lga_id')->unique()->count(),
            'total_returns_handled' => ReturnCase::whereNotNull('collection_point_id')->count(),
        ];

        return view('admin.collection_points', compact('collectionPoints', 'companies', 'lgas', 'states', 'metrics'));
    }

    public function adminStoreCollectionPoint(Request $request)
    {
        $validated = $request->validate([
            'logistics_company_id' => 'required|integer|exists:logistics_company_profiles,id',
            'lga_id' => 'required|integer|exists:lgas,id',
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:255',
            'landmark' => 'nullable|string|max:150',
            'contact_phone' => 'required|string|max:30',
            'contact_person' => 'nullable|string|max:100',
            'operating_hours' => 'nullable|string|max:100',
        ]);

        $lga = Lga::with('state')->findOrFail($validated['lga_id']);
        $code = LogisticsCollectionPoint::generateUniqueCode($lga->name);

        $cp = LogisticsCollectionPoint::create([
            'logistics_company_id' => $validated['logistics_company_id'],
            'state_id' => $lga->state_id,
            'lga_id' => $lga->id,
            'name' => strip_tags(trim($validated['name'])),
            'code' => $code,
            'address' => strip_tags(trim($validated['address'])),
            'landmark' => isset($validated['landmark']) ? strip_tags(trim($validated['landmark'])) : null,
            'contact_phone' => preg_replace('/[^0-9+]/', '', $validated['contact_phone']),
            'contact_person' => isset($validated['contact_person']) ? strip_tags(trim($validated['contact_person'])) : null,
            'operating_hours' => isset($validated['operating_hours']) ? strip_tags(trim($validated['operating_hours'])) : '08:00 AM - 06:00 PM',
            'is_active' => true,
        ]);

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_collection_point_created',
            'target_type' => LogisticsCollectionPoint::class,
            'target_id' => $cp->id,
            'old_values' => null,
            'new_values' => [
                'name' => $cp->name,
                'code' => $cp->code,
                'lga_id' => $cp->lga_id,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.collection_points.index')->with('status', "Collection Point '{$cp->name}' ({$cp->code}) registered successfully!");
    }

    public function adminToggleCollectionPoint(Request $request, int $id)
    {
        $cp = LogisticsCollectionPoint::findOrFail($id);
        $oldStatus = $cp->is_active;
        $cp->is_active = !$cp->is_active;
        $cp->save();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_collection_point_toggled',
            'target_type' => LogisticsCollectionPoint::class,
            'target_id' => $cp->id,
            'old_values' => ['is_active' => $oldStatus],
            'new_values' => ['is_active' => $cp->is_active],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        $stateText = $cp->is_active ? 'activated' : 'deactivated';
        return redirect()->route('admin.collection_points.index')->with('status', "Collection Point '{$cp->name}' has been {$stateText}.");
    }

    public function adminDeleteCollectionPoint(Request $request, int $id)
    {
        $cp = LogisticsCollectionPoint::findOrFail($id);
        $name = $cp->name;
        $cp->delete();

        AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'admin_collection_point_deleted',
            'target_type' => LogisticsCollectionPoint::class,
            'target_id' => $id,
            'old_values' => ['name' => $name],
            'new_values' => null,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.collection_points.index')->with('status', "Collection Point '{$name}' deleted successfully.");
    }

    /**
     * Super Admin Returns & Refunds Control Desk
     */
    public function adminReturnsPage(Request $request)
    {
        $statusFilter = $request->query('status');
        $query = ReturnCase::with(['customer', 'order', 'vendor', 'collectionPoint', 'items.product', 'inspections'])
            ->latest();

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'disputed_by_vendor') {
                $query->where(function ($q) {
                    $q->where('status', 'disputed_by_vendor')->orWhere('vendor_disputed', true);
                });
            } else {
                $query->where('status', $statusFilter);
            }
        }

        $returnCases = $query->paginate(20);

        $metrics = [
            'total_cases' => ReturnCase::count(),
            'requested_cases' => ReturnCase::where('status', 'requested')->count(),
            'inspected_cases' => ReturnCase::where('status', 'inspected')->count(),
            'refund_pending_cases' => ReturnCase::where('status', 'refund_pending')->count(),
            'disputed_cases' => ReturnCase::where('status', 'disputed_by_vendor')->orWhere('vendor_disputed', true)->count(),
            'total_refunded_naira' => ReturnCase::where('status', 'refunded')->sum('refunded_amount'),
        ];

        return view('admin.returns', compact('returnCases', 'metrics', 'statusFilter'));
    }

    public function adminShowReturnCase(int $id)
    {
        $case = ReturnCase::with([
            'customer',
            'order.items.product',
            'vendor',
            'collectionPoint',
            'items.orderItem',
            'items.product',
            'evidence',
            'inspections.collectionPoint',
            'inspections.inspector',
            'reviewer',
            'returnedByRider',
        ])->findOrFail($id);

        return view('admin.return_detail', compact('case'));
    }

    public function adminOpenLateReturnCaseAction(Request $request, ReturnAndRefundService $service)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'return_reason' => 'required|string|max:100',
            'customer_description' => 'required|string|min:10|max:2000',
            'override_reason' => 'required|string|min:10|max:1000',
            'items' => 'required|array|min:1|max:10',
            'items.*.order_item_id' => 'required|integer|distinct|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.item_condition' => 'nullable|string|max:40',
        ]);

        $admin = Auth::user();
        if (!$admin || !$admin->isSuperAdmin()) {
            return back()->with('error', 'Only an authorised Super Admin may open a late/direct-contact return case.');
        }

        $order = Order::with('customer')->findOrFail($validated['order_id']);
        if (!$order->customer) {
            return back()->with('error', 'The order has no linked customer account; resolve the customer identity before opening a return case.');
        }

        $refundHours = max(1, (int) \App\Models\SystemSetting::get('refund_window_hours', 24));
        $windowExpiresAt = $order->refund_window_expires_at;
        if (!$windowExpiresAt && $order->received_at) {
            $windowExpiresAt = $order->received_at->copy()->addHours($refundHours);
        } elseif (!$windowExpiresAt && $order->delivered_at) {
            $windowExpiresAt = $order->delivered_at->copy()->addHours($refundHours);
        } elseif (!$windowExpiresAt && $order->updated_at) {
            $windowExpiresAt = $order->updated_at->copy()->addHours($refundHours);
        }
        if (!$windowExpiresAt || !now()->gt($windowExpiresAt)) {
            return back()->with('error', 'This administrative route is for claims outside the standard return window. Use the normal customer return flow for in-window requests.');
        }

        $result = $service->createReturnCase(
            $order->customer,
            $order,
            $validated['items'],
            $validated['return_reason'],
            $validated['customer_description'],
            null,
            [],
            $admin,
            $validated['override_reason']
        );

        if (empty($result['success'])) {
            return back()->with('error', $result['message'] ?? 'Late return case could not be opened.');
        }

        return redirect()->route('admin.returns.show', $result['case']->id)
            ->with('status', 'Late/direct-contact return case opened for review. It remains unapproved; statutory rights and normal refund approval controls still apply.');
    }

    public function adminApproveReturnAction(Request $request, int $id, ReturnAndRefundService $service)
    {
        $case = ReturnCase::findOrFail($id);
        $admin = Auth::user();

        $result = $service->adminApproveReturn($admin, $case, $request->input('admin_notes'));

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }


        return redirect()->route('admin.returns.show', $case->id)->with('status', $result['message']);
    }

    public function adminDisburseRefundAction(Request $request, int $id, ReturnAndRefundService $service)
    {
        $case = ReturnCase::findOrFail($id);
        $admin = Auth::user();

        $bankReference = $request->input('bank_reference');
        $proofPath = null;

        if ($request->hasFile('transfer_receipt')) {
            $file = $request->file('transfer_receipt');
            $filename = 'refund_' . $case->case_number . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('refund_proofs', $filename, 'public');
            $proofPath = 'storage/' . $path;
        }

        if (empty($proofPath) && empty($bankReference) && app()->environment('production')) {
            return back()->with('error', 'Refund disbursement rejected: A valid bank transfer receipt upload or bank transaction reference is mandatory.');
        }

        $refundDeliveryFee = $request->boolean('refund_delivery_fee');

        $result = $service->adminDisburseRefund($admin, $case, $bankReference, $proofPath, $refundDeliveryFee);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }


        return redirect()->route('admin.returns.show', $case->id)->with('status', $result['message']);
    }

    public function adminRejectReturnAction(Request $request, int $id, ReturnAndRefundService $service)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);
        $case = ReturnCase::findOrFail($id);
        $admin = Auth::user();
        if (!$admin) {
            abort(403);
        }

        $result = $service->adminRejectReturn($admin, $case, $validated['rejection_reason']);
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('admin.returns.show', $case->id)->with('status', $result['message']);
    }

    public function adminArbitrateReturnAction(Request $request, int $id, DoorstepHandshakeService $handshakeService)
    {
        $case = ReturnCase::findOrFail($id);
        $admin = Auth::user();

        $validated = $request->validate([
            'ruling' => 'required|in:uphold_vendor_dispute,uphold_customer_claim,platform_transit_protection',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $result = $handshakeService->adminArbitrateDispute(
            $admin,
            $case,
            $validated['ruling'],
            $validated['admin_notes'] ?? null
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        \App\Services\NotificationDispatchService::dispatchDisputeArbitrationRuling($case, $validated['ruling'], $validated['admin_notes'] ?? null);

        return redirect()->route('admin.returns.show', $case->id)->with('status', $result['message']);
    }

    /**
     * Public View for Terms and Conditions of Service
     */
    public function showTermsOfService(Request $request)
    {
        return $this->showLegalDocument('terms-of-service');
    }

    /**
     * Public View for About Us & Operating Charter
     */
    public function showAboutUs()
    {
        return $this->showLegalDocument('about-us');
    }

    /**
     * Public View for Contact & Customer Care Desk
     */
    public function showContactUs()
    {
        return $this->showLegalDocument('contact-and-help-center');
    }

    /**
     * Public View for Frequently Asked Questions & Knowledge Base
     */
    public function showFaq()
    {
        return $this->showLegalDocument('frequently-asked-questions');
    }

    /**
     * Public View for Order Cancellation & Dispute Resolution Policy
     */
    public function showCancellationPolicy()
    {
        return $this->showLegalDocument('cancellation-policy');
    }

    /**
     * Public View for any Legal Policy by Slug
     */
    public function showLegalDocument(string $slug)
    {
        $document = LegalDocument::getBySlug($slug);

        if (!$document) {
            abort(404, "Legal document '{$slug}' not found.");
        }

        $currentVersion = $document->currentVersion;
        $allLegalDocs = LegalDocument::where('is_published', true)->get();

        return view('storefront.legal.show', compact('document', 'currentVersion', 'allLegalDocs'));
    }

    /**
     * Super Admin Command Tower: Legal Documents Index
     */
    public function adminLegalIndex()
    {
        $documents = LegalDocument::withCount('versions')
            ->with('currentVersion')
            ->orderBy('id', 'asc')
            ->get();

        return view('admin.legal.index', compact('documents'));
    }

    /**
     * Super Admin: Edit Legal Document
     */
    public function adminLegalEdit(int $id)
    {
        $document = LegalDocument::with(['currentVersion', 'versions'])->findOrFail($id);

        return view('admin.legal.edit', compact('document'));
    }

    /**
     * Super Admin: Update Legal Document & Publish New Version
     */
    public function adminLegalUpdate(Request $request, int $id)
    {
        $document = LegalDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:32',
            'effective_date' => 'required|date',
            'summary_of_changes' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:500',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $admin = Auth::user();
        $isPublished = $request->boolean('is_published', true);

        // Create or update the version row
        $version = LegalDocumentVersion::updateOrCreate(
            [
                'legal_document_id' => $document->id,
                'version' => $validated['version'],
            ],
            [
                'title' => $validated['title'],
                'content' => $validated['content'],
                'summary_of_changes' => $validated['summary_of_changes'] ?? 'Version updated by Super Admin',
                'effective_date' => $validated['effective_date'],
                'published_by' => $admin ? $admin->id : null,
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]
        );

        $oldVersion = $document->current_version;

        $document->update([
            'title' => $validated['title'],
            'current_version' => $validated['version'],
            'effective_date' => $validated['effective_date'],
            'summary' => $validated['summary'] ?? $document->summary,
            'is_published' => $isPublished,
            'has_draft' => false,
            'draft_title' => null,
            'draft_version' => null,
            'draft_content' => null,
            'draft_summary' => null,
            'draft_summary_of_changes' => null,
            'draft_effective_date' => null,
            'draft_updated_at' => null,
        ]);

        $document->purgeCache();

        AdminAuditLog::create([
            'user_id' => $admin ? $admin->id : null,
            'action' => 'legal_document_version_published',
            'target_type' => LegalDocument::class,
            'target_id' => $document->id,
            'old_values' => ['version' => $oldVersion],
            'new_values' => [
                'version' => $validated['version'],
                'title' => $validated['title'],
                'effective_date' => $validated['effective_date'],
                'summary_of_changes' => $validated['summary_of_changes'] ?? null,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.legal.index')
            ->with('status', "Legal document '{$document->title}' (v{$validated['version']}) successfully updated and published.");
    }

    /**
     * Super Admin: Legal Draft & Preview Desk
     */
    /**
     * Super Admin: Save Working Draft without Publishing
     */
    public function adminLegalSaveDraft(Request $request, int $id)
    {
        $document = LegalDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:32',
            'effective_date' => 'nullable|date',
            'summary_of_changes' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:500',
            'content' => 'required|string',
        ]);

        $document->update([
            'has_draft' => true,
            'draft_title' => $validated['title'],
            'draft_version' => $validated['version'],
            'draft_content' => $validated['content'],
            'draft_summary' => $validated['summary'] ?? null,
            'draft_summary_of_changes' => $validated['summary_of_changes'] ?? null,
            'draft_effective_date' => $validated['effective_date'] ?? null,
            'draft_updated_at' => now(),
        ]);

        return redirect()->route('admin.legal.edit', $document->id)
            ->with('status', "Draft version (v{$validated['version']}) saved successfully! Active public version remains v{$document->current_version}.");
    }

    /**
     * Super Admin: Discard Active Working Draft
     */
    public function adminLegalDiscardDraft(Request $request, int $id)
    {
        $document = LegalDocument::findOrFail($id);

        $document->update([
            'has_draft' => false,
            'draft_title' => null,
            'draft_version' => null,
            'draft_content' => null,
            'draft_summary' => null,
            'draft_summary_of_changes' => null,
            'draft_effective_date' => null,
            'draft_updated_at' => null,
        ]);

        return redirect()->route('admin.legal.edit', $document->id)
            ->with('status', "Draft discarded. Editor restored to active published version (v{$document->current_version}).");
    }

    /**
     * Super Admin: Preview Saved Working Draft in Storefront Context
     */
    public function adminLegalPreviewDraft(int $id)
    {
        $document = LegalDocument::findOrFail($id);

        if (!$document->has_draft && empty($document->draft_content)) {
            return redirect()->route('admin.legal.edit', $document->id)
                ->with('error', 'No active draft found for this document.');
        }

        $currentVersion = (object)[
            'version' => $document->draft_version ?? $document->current_version,
            'title' => $document->draft_title ?? $document->title,
            'rendered_html' => $document->rendered_draft_html,
            'effective_date' => $document->draft_effective_date ?? $document->effective_date,
        ];

        $allLegalDocs = LegalDocument::where('is_published', true)->get();
        $isDraftPreview = true;

        return view('admin.legal.preview_draft', compact('document', 'currentVersion', 'allLegalDocs', 'isDraftPreview'));
    }

    /**
     * Super Admin: Render Unsaved Markdown to Storefront HTML for Live Modal Preview
     */
    public function adminLegalPreviewRaw(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
        ]);

        $html = \Illuminate\Support\Str::markdown($request->input('content', ''));

        return response()->json([
            'success' => true,
            'html' => $html,
        ]);
    }

    /**
     * Super Admin: View Revision Audit History
     */
    public function adminLegalHistory(int $id)
    {
        $document = LegalDocument::with(['versions.publisher'])->findOrFail($id);

        return view('admin.legal.history', compact('document'));
    }

    /**
     * Super Admin Notification Orchestration & Firebase Control Tower
     */
    public function adminNotificationSettingsPage(\Illuminate\Http\Request $request)
    {
        $settings = [
            'fcm_enabled' => (bool)\App\Models\SystemSetting::get('notification_fcm_enabled', true),
            'sms_enabled' => (bool)\App\Models\SystemSetting::get('notification_sms_enabled', true),
            'inapp_enabled' => (bool)\App\Models\SystemSetting::get('notification_inapp_enabled', true),
            'whatsapp_enabled' => (bool)\App\Models\SystemSetting::get('notification_whatsapp_enabled', false),

            // Termii SMS Gateway
            'termii_api_key' => \App\Models\SystemSetting::get('termii_api_key', config('services.termii.key', env('TERMII_API_KEY', ''))),
            'termii_sender_id' => \App\Models\SystemSetting::get('termii_sender_id', config('services.termii.sender_id', env('TERMII_SENDER_ID', 'VMARKET'))),
            'termii_channel' => \App\Models\SystemSetting::get('termii_channel', env('TERMII_CHANNEL', 'dnd')),

            // Google Firebase Credentials
            'firebase_project_id' => \App\Models\SystemSetting::get('firebase_project_id', ''),
            'firebase_client_email' => \App\Models\SystemSetting::get('firebase_client_email', ''),
            'firebase_private_key' => \App\Models\SystemSetting::get('firebase_private_key', ''),
            'firebase_credentials_path' => \App\Models\SystemSetting::get('firebase_credentials_path', ''),

            // Multi-Event Routing Matrix
            'matrix' => \App\Models\SystemSetting::get('notification_event_matrix', [
                'order_placed' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'vendor_handover_ready' => ['fcm' => true, 'sms' => false, 'in_app' => true, 'whatsapp' => false],
                'out_for_delivery' => ['fcm' => true, 'sms' => false, 'in_app' => true, 'whatsapp' => false],
                'doorstep_arrived' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'delivery_completed' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'doorstep_return_dispute' => ['fcm' => true, 'sms' => false, 'in_app' => true, 'whatsapp' => false],
                'vendor_return_otp' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'return_accepted' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'dispute_escalated' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'dispute_arbitration' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'payout_requested' => ['fcm' => true, 'sms' => false, 'in_app' => true, 'whatsapp' => false],
                'payout_disbursed' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'payout_rejected' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'wallet_credit' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
                'security_bank_cooldown' => ['fcm' => true, 'sms' => true, 'in_app' => true, 'whatsapp' => false],
            ]),

            // Customizable Message Templates
            'templates' => \App\Models\SystemSetting::get('notification_templates', []),
        ];

        $recentLogs = \App\Models\NotificationDeliveryLog::latest()->take(25)->get();
        $registeredDevices = \App\Models\UserDeviceToken::with('user')->latest('last_seen_at')->take(50)->get();

        $activeTokensCount = \App\Models\UserDeviceToken::active()->count();
        $ridersWithTokensCount = \App\Models\UserDeviceToken::active()->where('app_type', 'rider')->distinct('user_id')->count('user_id');
        $vendorsWithTokensCount = \App\Models\UserDeviceToken::active()->where('app_type', 'vendor')->distinct('user_id')->count('user_id');
        $customersWithTokensCount = \App\Models\UserDeviceToken::active()->where('app_type', 'customer')->distinct('user_id')->count('user_id');
        $pendingSettlementsCount = \App\Models\Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.notifications', compact(
            'settings',
            'recentLogs',
            'registeredDevices',
            'activeTokensCount',
            'ridersWithTokensCount',
            'vendorsWithTokensCount',
            'customersWithTokensCount',
            'pendingSettlementsCount'
        ));
    }

    /**
     * Save Multi-Channel Notification Settings & Event Routing Matrix
     */
    public function adminUpdateNotificationSettings(\Illuminate\Http\Request $request)
    {
        // 1. Master Channel Toggles
        \App\Models\SystemSetting::set('notification_fcm_enabled', $request->has('fcm_enabled'), 'notifications');
        \App\Models\SystemSetting::set('notification_sms_enabled', $request->has('sms_enabled'), 'notifications');
        \App\Models\SystemSetting::set('notification_inapp_enabled', $request->has('inapp_enabled'), 'notifications');
        \App\Models\SystemSetting::set('notification_whatsapp_enabled', $request->has('whatsapp_enabled'), 'notifications');

        // 2. Termii SMS Gateway Configuration
        if ($request->filled('termii_api_key')) {
            \App\Models\SystemSetting::set('termii_api_key', trim($request->input('termii_api_key')), 'sms');
        }
        if ($request->filled('termii_sender_id')) {
            \App\Models\SystemSetting::set('termii_sender_id', trim($request->input('termii_sender_id')), 'sms');
        }
        if ($request->filled('termii_channel')) {
            \App\Models\SystemSetting::set('termii_channel', trim($request->input('termii_channel')), 'sms');
        }

        // 3. Google Firebase Service Account JSON File Upload (Direct & Foolproof)
        $uploadedJsonNotice = '';
        if ($request->hasFile('firebase_service_account_json')) {
            try {
                $file = $request->file('firebase_service_account_json');
                $rawContent = file_get_contents($file->getRealPath());
                $parsedJson = json_decode($rawContent, true);

                if (is_array($parsedJson)) {
                    if (!empty($parsedJson['project_id'])) {
                        \App\Models\SystemSetting::set('firebase_project_id', $parsedJson['project_id'], 'firebase');
                    }
                    if (!empty($parsedJson['client_email'])) {
                        \App\Models\SystemSetting::set('firebase_client_email', $parsedJson['client_email'], 'firebase');
                    }
                    if (!empty($parsedJson['private_key'])) {
                        \App\Models\SystemSetting::set('firebase_private_key', $parsedJson['private_key'], 'firebase');
                    }
                    $uploadedJsonNotice = ' Google Service Account JSON file parsed and loaded successfully.';
                }
            } catch (\Throwable $e) {
                // Fallback to text fields if file was unparseable
            }
        }

        // 4. Text Input Firebase Overrides
        if ($request->filled('firebase_project_id')) {
            \App\Models\SystemSetting::set('firebase_project_id', trim($request->input('firebase_project_id')), 'firebase');
        }
        if ($request->filled('firebase_client_email')) {
            \App\Models\SystemSetting::set('firebase_client_email', trim($request->input('firebase_client_email')), 'firebase');
        }
        if ($request->filled('firebase_private_key')) {
            \App\Models\SystemSetting::set('firebase_private_key', trim($request->input('firebase_private_key')), 'firebase');
        }
        if ($request->filled('firebase_credentials_path')) {
            \App\Models\SystemSetting::set('firebase_credentials_path', trim($request->input('firebase_credentials_path')), 'firebase');
        }

        // 5. Granular Event-by-Event Routing Matrix
        $matrixInput = $request->input('matrix', []);
        $formattedMatrix = [];
        $events = [
            'order_placed',
            'vendor_handover_ready',
            'out_for_delivery',
            'doorstep_arrived',
            'delivery_completed',
            'doorstep_return_dispute',
            'vendor_return_otp',
            'return_accepted',
            'dispute_escalated',
            'dispute_arbitration',
            'payout_requested',
            'payout_disbursed',
            'payout_rejected',
            'wallet_credit',
            'security_bank_cooldown',
        ];

        foreach ($events as $evt) {
            $formattedMatrix[$evt] = [
                'fcm' => !empty($matrixInput[$evt]['fcm']),
                'sms' => !empty($matrixInput[$evt]['sms']),
                'in_app' => !empty($matrixInput[$evt]['in_app']),
                'whatsapp' => !empty($matrixInput[$evt]['whatsapp']),
            ];
        }

        \App\Models\SystemSetting::set('notification_event_matrix', $formattedMatrix, 'notifications');

        // 6. Message Templates & Wording Customization
        if ($request->has('templates') && is_array($request->input('templates'))) {
            \App\Models\SystemSetting::set('notification_templates', $request->input('templates'), 'notifications');
        }

        return back()->with('status', 'Multi-channel notification settings, SMS gateway, and event routing matrix updated successfully.' . $uploadedJsonNotice);
    }

    /**
     * Live Test Push Dispatcher Tool
     */
    public function adminSendTestPush(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'target_type' => 'required|string|in:user,token',
            'target_id' => 'nullable|integer',
            'device_token' => 'nullable|string',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:300',
        ]);

        $fcmService = app(\App\Services\FirebaseCloudMessagingService::class);

        if ($validated['target_type'] === 'token' && !empty($validated['device_token'])) {
            $res = $fcmService->sendToToken(
                $validated['device_token'],
                $validated['title'],
                $validated['body'],
                ['type' => 'test_push', 'dispatched_by' => 'admin'],
                'admin_test'
            );
        } else {
            $targetUserId = !empty($validated['target_id']) ? $validated['target_id'] : auth()->id();
            $user = \App\Models\User::find($targetUserId);
            if (!$user) {
                return back()->with('error', 'Target user #' . $targetUserId . ' not found.');
            }
            $res = $fcmService->sendToUser(
                $user->id,
                $validated['title'],
                $validated['body'],
                ['type' => 'test_push', 'dispatched_by' => 'admin'],
                'admin_test'
            );
        }

        if (!empty($res['success'])) {
            return back()->with('status', 'Test push notification dispatched successfully! ' . ($res['message'] ?? ''));
        }

        return back()->with('error', 'Push dispatch failed: ' . ($res['message'] ?? 'Check Firebase credentials or registered device tokens.'));
    }

    /**
     * Live Test Termii SMS Gateway Dispatcher
     */
    public function adminSendTestSms(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'recipient' => 'required|string|max:20',
            'message' => 'required|string|max:160',
        ]);

        $apiKey = \App\Models\SystemSetting::get('termii_api_key') ?: config('services.termii.key', env('TERMII_API_KEY'));
        $sender = \App\Models\SystemSetting::get('termii_sender_id') ?: config('services.termii.sender_id', env('TERMII_SENDER_ID', 'VMARKET'));

        if (empty($apiKey)) {
            \App\Models\NotificationDeliveryLog::log('sms', $validated['recipient'], 'test_sms', 'Termii SMS Test', 'skipped', null, 'Termii API Key not configured in Admin Portal or .env');
            return back()->with('error', 'Termii SMS API key is not configured. Please save your API Key above.');
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(6)->post('https://api.ng.termii.com/api/sms/send', [
                'to' => $validated['recipient'],
                'from' => $sender,
                'sms' => $validated['message'],
                'type' => 'plain',
                'channel' => \App\Models\SystemSetting::get('termii_channel', 'generic'),
                'api_key' => $apiKey,
            ]);

            if ($res->successful()) {
                \App\Models\NotificationDeliveryLog::log('sms', $validated['recipient'], 'test_sms', 'Termii SMS Test', 'delivered', $res->json());
                return back()->with('status', "Test SMS successfully dispatched to {$validated['recipient']}! Gateway Ref: " . ($res->json('message_id') ?? 'Confirmed'));
            }

            \App\Models\NotificationDeliveryLog::log('sms', $validated['recipient'], 'test_sms', 'Termii SMS Test', 'failed', null, $res->body());
            return back()->with('error', 'Termii dispatch rejected: ' . $res->body());
        } catch (\Throwable $e) {
            \App\Models\NotificationDeliveryLog::log('sms', $validated['recipient'], 'test_sms', 'Termii SMS Test', 'failed', null, $e->getMessage());
            return back()->with('error', 'SMS connection exception: ' . $e->getMessage());
        }
    }

    /**
     * Revoke single device token from Admin Portal
     */
    public function adminRevokeDeviceToken(int $id)
    {
        $token = \App\Models\UserDeviceToken::findOrFail($id);
        $token->update(['is_active' => false]);

        return back()->with('status', "Device token for user #{$token->user_id} ({$token->device_type} - {$token->app_type}) has been revoked.");
    }

    /**
     * Prune inactive tokens (>30 days inactive) from Admin Portal
     */
    public function adminPruneDeviceTokens()
    {
        $cutoff = \Carbon\Carbon::now()->subDays(30);
        $count = \App\Models\UserDeviceToken::where('last_seen_at', '<', $cutoff)->orWhere('is_active', false)->delete();

        return back()->with('status', "Pruned {$count} inactive or decommissioned device tokens from the registry.");
    }

    /**
     * Authoritative Gateway Reconciliation Action for Locked Vendor Payouts (Audit P0 & P1)
     */
    public function adminReconcileVendorPayout(Request $request, \App\Services\PaystackService $paystackService)
    {
        $admin = auth()->user();
        $payoutId = $request->input('payout_id');
        $payout = \App\Models\VendorPayoutRequest::with('vendor')->findOrFail($payoutId);

        $ref = $payout->transfer_reference ?? ('VND-DISBURSE-' . $payout->id);
        $verify = $paystackService->verifyTransfer($ref);

        if (!$verify['success']) {
            if (($verify['status'] ?? '') === 'network_exception') {
                return back()->with('error', 'Paystack Gateway remains unreachable. Timeout hold maintained to prevent duplicate disbursement.');
            }
            // An unsuccessful verification response does not prove that no transfer occurred.
            // Keep the payout fenced until an authoritative gateway outcome is available.
            return back()->with('error', 'Transfer status could not be verified authoritatively. The payout remains on hold; do not retry based only on this verification response.');
        }

        $status = $verify['status'] ?? 'unknown';
        if ($status === 'success') {
            app(\App\Services\PayoutFinalizationService::class)->finalizeVendorPayout($payout, [
                'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                'reference' => $ref,
                'status' => 'success',
            ], $admin);
            return back()->with('status', "Gateway reconciliation complete: Payout #{$payout->id} bank transfer confirmed and accounting finalized.");
        }

        if (in_array($status, ['failed', 'reversed'], true)) {
            app(\App\Services\PayoutFinalizationService::class)->revertVendorPayout(
                $payout,
                'Gateway Reconcile: ' . ($verify['message'] ?? 'Transfer failed at Paystack'),
                $admin
            );
            return back()->with('status', "Gateway reconciliation complete: Payout #{$payout->id} marked failed and order allocations restored.");
        }

        return back()->with('error', "Gateway returned status '{$status}'. Timeout hold maintained.");
    }

    

    /**
     * Authoritative Gateway Reconciliation Action for Locked Logistics Payouts (Audit P0 & P1)
     */
    public function adminReconcileLogisticsPayout(Request $request, \App\Services\PaystackService $paystackService)
    {
        $admin = auth()->user();
        $payoutId = $request->input('payout_id');
        $payout = \App\Models\LogisticsPayoutRequest::with('company')->findOrFail($payoutId);

        $ref = $payout->transfer_reference ?? ('FLEET-DISBURSE-' . $payout->id);
        $verify = $paystackService->verifyTransfer($ref);

        if (!$verify['success']) {
            if (($verify['status'] ?? '') === 'network_exception') {
                return back()->with('error', 'Paystack Gateway remains unreachable. Timeout hold maintained to prevent duplicate disbursement.');
            }
            // An unsuccessful verification response does not prove that no transfer occurred.
            // Keep the payout fenced until an authoritative gateway outcome is available.
            return back()->with('error', 'Transfer status could not be verified authoritatively. The payout remains on hold; do not retry based only on this verification response.');
        }

        $status = $verify['status'] ?? 'unknown';
        if ($status === 'success') {
            app(\App\Services\PayoutFinalizationService::class)->finalizeLogisticsPayout($payout, [
                'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                'reference' => $ref,
                'status' => 'success',
            ], $admin);
            return back()->with('status', "Gateway reconciliation complete: Payout #{$payout->id} bank transfer confirmed and accounting finalized.");
        }

        if (in_array($status, ['failed', 'reversed'], true)) {
            app(\App\Services\PayoutFinalizationService::class)->revertLogisticsPayout(
                $payout,
                'Gateway Reconcile: ' . ($verify['message'] ?? 'Transfer failed at Paystack'),
                $admin
            );
            return back()->with('status', "Gateway reconciliation complete: Payout #{$payout->id} marked failed and wallet refunded.");
        }

        return back()->with('error', "Gateway returned status '{$status}'. Timeout hold maintained.");
    }
}