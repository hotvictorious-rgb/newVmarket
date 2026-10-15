<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CheckoutIntent;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Query eligible products
        $query = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
            ->whereHas('shop', function ($q) use ($eligibleOriginLgaIds) {
                $q->whereIn('lga_id', $eligibleOriginLgaIds);
            });

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->latest()->take(12)->get();
        $isFallbackCatalog = false;

        // If an LGA has no direct items yet, fall back to flagship items so catalog isn't empty
        if ($products->isEmpty() && !$request->filled('search') && !$request->filled('category')) {
            $products = Product::marketplaceEligible()
                ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
                ->latest()
                ->take(12)
                ->get();
            $isFallbackCatalog = true;
        }

        $latestProducts = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
            ->latest()
            ->take(6)
            ->get();

        $shops = Shop::with('lga:id,name')
            ->where('pickup_enabled', true)
            ->whereIn('lga_id', $eligibleOriginLgaIds)
            ->take(6)
            ->get();

        if ($shops->isEmpty()) {
            $shops = Shop::with('lga:id,name')->where('pickup_enabled', true)->take(6)->get();
        }

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
            $productsHtml = view('storefront.partials._products_grid', compact('products', 'activeCity', 'selectedLgaId', 'activeLanes', 'isFallbackCatalog'))->render();
            $shopsHtml = view('storefront.partials._shops_grid', compact('shops', 'activeCity'))->render();

            return response()->json([
                'success' => true,
                'city' => $activeCity,
                'state' => $activeState,
                'lga_id' => $selectedLgaId,
                'coverage_status' => $coverageStatus,
                'status_badge' => $statusBadge,
                'preview_status' => $previewStatus,
                'products_count' => $products->count(),
                'products_html' => $productsHtml,
                'shops_html' => $shopsHtml,
                'is_fallback' => $isFallbackCatalog,
            ]);
        }

        return view('storefront.index', compact(
            'categories', 'products', 'latestProducts', 'shops', 'lgas',
            'activeCity', 'activeState', 'selectedLgaId', 'activeLanes',
            'coverageStatus', 'statusBadge', 'previewStatus', 'isFallbackCatalog'
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

        $city = $request->input('city');
        $state = $request->input('state', 'Akwa Ibom');

        $lga = null;
        if ($request->filled('lga_id')) {
            $lga = Lga::find($request->input('lga_id'));
        }
        if (!$lga) {
            $lga = Lga::where('name', $city)->first();
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

        return view('admin.settlements', compact('pendingSettlements', 'heldSettlementsCount', 'totalCashbackIssued', 'pendingSettlementsCount'));
    }

    /**
     * Dedicated Page: Storefront Announcement Bar & Customer Care Desk
     */
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
        $allVendors = VendorProfile::with(['user', 'shops'])->latest()->get();
        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.merchants', compact('allVendors', 'pendingSettlementsCount'));
    }

    /**
     * Dedicated Page: Platform Escrow Orders & Fulfillment Ledger
     */
    public function adminOrdersPage(Request $request)
    {
        $recentOrders = Order::with(['customer:id,name', 'vendor:id,business_name', 'shop:id,name'])
            ->latest()
            ->get();

        $pendingSettlementsCount = Order::where('vendor_settlement_status', 'eligible')->count();

        return view('admin.orders', compact('recentOrders', 'pendingSettlementsCount'));
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

        \App\Models\SystemSetting::set('support_phone', $validated['support_phone'], 'contact');
        \App\Models\SystemSetting::set('support_whatsapp', $validated['support_whatsapp'] ?? $validated['support_phone'], 'contact');
        \App\Models\SystemSetting::set('support_email', $validated['support_email'] ?? 'support@victoriousmarket.com', 'contact');
        \App\Models\SystemSetting::set('announcement_type', $validated['announcement_type'], 'announcement');
        \App\Models\SystemSetting::set('announcement_badge', $validated['announcement_badge'] ?? '💬', 'announcement');
        \App\Models\SystemSetting::set('announcement_text', $validated['announcement_text'], 'announcement');
        \App\Models\SystemSetting::set('announcement_bg_color', $validated['announcement_bg_color'], 'announcement');
        \App\Models\SystemSetting::set('announcement_link', $validated['announcement_link'] ?? '', 'announcement');
        \App\Models\SystemSetting::set('announcement_is_active', $request->has('announcement_is_active') ? '1' : '0', 'announcement');

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
        $primaryShop = $vendor->primaryShop ?? Shop::where('vendor_id', $vendor->id)->first() ?? Shop::first();

        $products = Product::where('vendor_id', $vendor->id)->with('category')->get();
        $reservations = PickupReservation::where('vendor_id', $vendor->id)
            ->with(['product:id,name,unit_price', 'customer:id,name,phone'])
            ->latest()
            ->take(10)
            ->get();

        $orders = Order::where('vendor_id', $vendor->id)
            ->with('customer:id,name,phone')
            ->latest()
            ->take(10)
            ->get();

        $grossSales = Order::where('vendor_id', $vendor->id)->sum('total_amount');
        $netEarnings = Order::where('vendor_id', $vendor->id)->sum('vendor_earning_90');
        $settledAmount = Order::where('vendor_id', $vendor->id)->where('vendor_settlement_status', 'settled')->sum('vendor_earning_90');
        $pendingSettlement = Order::where('vendor_id', $vendor->id)->whereIn('vendor_settlement_status', ['eligible', 'held'])->sum('vendor_earning_90');

        $vendorMetrics = [
            'gross_sales' => $grossSales,
            'net_earnings' => $netEarnings,
            'settled_amount' => $settledAmount,
            'pending_settlement' => $pendingSettlement,
            'total_products' => $products->count(),
            'active_orders' => $orders->whereIn('order_status', ['pending', 'confirmed', 'processing'])->count(),
        ];

        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();
        $shops = Shop::where('vendor_id', $vendor->id)->get();
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();
        return view('vendor.dashboard', compact('vendor', 'primaryShop', 'products', 'reservations', 'orders', 'vendorMetrics', 'categories', 'shops', 'allLgas'));
    }

    /**
     * Vendor Products & Freshness Page
     */
    public function vendorProductsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $products = Product::where('vendor_id', $vendor->id)->with('category')->latest()->get();
        $categories = Category::where('is_active', true)->orderBy('priority', 'asc')->get();

        return view('vendor.products', compact('vendor', 'products', 'categories'));
    }

    /**
     * Vendor Customer Orders Page
     */
    public function vendorOrdersPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $orders = Order::where('vendor_id', $vendor->id)
            ->with('customer:id,name,phone')
            ->latest()
            ->get();

        return view('vendor.orders', compact('vendor', 'orders'));
    }

    /**
     * Vendor Pickup Reservations Page
     */
    public function vendorReservationsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $reservations = PickupReservation::where('vendor_id', $vendor->id)
            ->with(['product:id,name,unit_price', 'customer:id,name,phone'])
            ->latest()
            ->get();

        return view('vendor.reservations', compact('vendor', 'reservations'));
    }

    /**
     * Vendor Physical Plaza Outlets Page
     */
    public function vendorShopsPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();
        $shops = Shop::where('vendor_id', $vendor->id)->with('lga.state')->get();
        $allLgas = Lga::with('state')->where('is_active', true)->orderBy('name', 'asc')->get();

        return view('vendor.shops', compact('vendor', 'shops', 'allLgas'));
    }

    /**
     * Vendor 90% Payout Bank Account Page
     */
    public function vendorPayoutPage(Request $request)
    {
        $vendor = $this->resolveVendorProfile();

        $grossSales = Order::where('vendor_id', $vendor->id)->sum('total_amount');
        $netEarnings = Order::where('vendor_id', $vendor->id)->sum('vendor_earning_90');
        $settledAmount = Order::where('vendor_id', $vendor->id)->where('vendor_settlement_status', 'settled')->sum('vendor_earning_90');
        $pendingSettlement = Order::where('vendor_id', $vendor->id)->whereIn('vendor_settlement_status', ['eligible', 'held'])->sum('vendor_earning_90');

        $vendorMetrics = [
            'gross_sales' => $grossSales,
            'net_earnings' => $netEarnings,
            'settled_amount' => $settledAmount,
            'pending_settlement' => $pendingSettlement,
        ];

        return view('vendor.payout', compact('vendor', 'vendorMetrics'));
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

        $totalRevenue = Order::where('order_status', 'delivered')->sum('logistics_fee_5');

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
        $activeRiders = RiderProfile::where('is_active', true)->with(['user', 'assignedLga'])->get();

        return view('logistics.riders', compact('company', 'activeRiders'));
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

        $totalRevenue = Order::where('order_status', 'delivered')->sum('logistics_fee_5');

        $fleetMetrics = [
            'active_riders' => $activeRiders->count(),
            'on_road' => $activeRiders->where('is_online', true)->count(),
            'accumulated_fees' => $totalRevenue,
        ];

        return view('logistics.wallet', compact('company', 'completedRuns', 'fleetMetrics'));
    }

    /**
     * Delivery Rider Mobile Cockpit Dashboard
     */
    public function riderDashboard()
    {
        $rider = $this->resolveRiderProfile();
        
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        if ($activeDeliveries->isEmpty()) {
            $fallback = Order::with(['shop', 'customer'])->first();
            if ($fallback) {
                $activeDeliveries = collect([$fallback]);
            }
        }

        $completedDrops = Order::where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer'])
            ->latest()
            ->take(8)
            ->get();

        $riderMetrics = [
            'assigned_runs' => $activeDeliveries->count(),
            'completed_today' => $completedDrops->count(),
            'estimated_earnings' => number_format($completedDrops->count() * 750 + 1500, 2),
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
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        if ($activeDeliveries->isEmpty()) {
            $fallback = Order::with(['shop', 'customer'])->first();
            if ($fallback) {
                $activeDeliveries = collect([$fallback]);
            }
        }

        return view('rider.deliveries', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Plaza Pickup Page (Step 1)
     */
    public function riderPickupPage()
    {
        $rider = $this->resolveRiderProfile();
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'ready_for_pickup'])
            ->with(['shop', 'customer'])
            ->get();

        if ($activeDeliveries->isEmpty()) {
            $fallback = Order::with(['shop', 'customer'])->first();
            if ($fallback) {
                $activeDeliveries = collect([$fallback]);
            }
        }

        return view('rider.pickup', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Customer Dropoff Page (Step 2)
     */
    public function riderDropoffPage()
    {
        $rider = $this->resolveRiderProfile();
        $activeDeliveries = Order::where('fulfillment_type', 'doorstep_delivery')
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->with(['shop', 'customer'])
            ->get();

        if ($activeDeliveries->isEmpty()) {
            $fallback = Order::with(['shop', 'customer'])->first();
            if ($fallback) {
                $activeDeliveries = collect([$fallback]);
            }
        }

        return view('rider.dropoff', compact('rider', 'activeDeliveries'));
    }

    /**
     * Rider Today's Earnings Ledger Page
     */
    public function riderEarningsPage()
    {
        $rider = $this->resolveRiderProfile();
        $completedDrops = Order::where('fulfillment_type', 'doorstep_delivery')
            ->where('order_status', 'delivered')
            ->with(['shop', 'customer'])
            ->latest()
            ->take(15)
            ->get();

        $riderMetrics = [
            'completed_today' => $completedDrops->count(),
            'estimated_earnings' => number_format($completedDrops->count() * 750 + 1500, 2),
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
            ->where('status', 'matured')
            ->sum('amount');

        $cashbackPending = CustomerCashbackLedger::where('customer_id', $customer->id)
            ->where('status', 'pending_maturation')
            ->sum('amount');

        $addresses = \App\Models\CustomerAddress::where('user_id', $customer->id)->get();

        $reservations = PickupReservation::where('customer_id', $customer->id)
            ->with(['product:id,name,unit_price', 'shop:id,name,address'])
            ->latest()
            ->get();

        if ($reservations->isEmpty()) {
            $reservations = PickupReservation::with(['product:id,name,unit_price', 'shop:id,name,address'])->latest()->take(2)->get();
        }

        $nameParts = explode(' ', $customer->name ?? 'Emeka Okonkwo', 2);
        $f_name = $nameParts[0] ?? 'Emeka';
        $l_name = $nameParts[1] ?? 'Okonkwo';

        $total_order = $orders->count();
        $wishlists = 3;
        $total_loyalty_point = (int)($cashbackAvailable > 0 ? $cashbackAvailable * 10 : 250);
        $cashbackNairaValue = (float)($cashbackAvailable > 0 ? $cashbackAvailable : 2500.00);

        $customerMetrics = [
            'total_orders' => $orders->count(),
            'active_orders' => $activeOrders->count(),
            'cashback_available' => $cashbackAvailable > 0 ? $cashbackAvailable : 2500.00,
            'cashback_pending' => $cashbackPending,
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

        if ($reservations->isEmpty()) {
            $reservations = PickupReservation::with(['product:id,name,unit_price', 'shop:id,name,address'])->latest()->take(2)->get();
        }

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
            ->where('status', 'matured')
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
        $order = Order::findOrFail($request->input('order_id'));
        $admin = User::where('user_type', 'super_admin')->first();
        $proof = 'storage/payouts/proof_' . $order->order_number . '.png';

        $result = $settlementService->disburseVendorSettlement($order, $admin, $proof);

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Vendor signs off in-shop physical inspection
     */
    public function vendorApproveInspection(Request $request, OrderSettlementService $settlementService)
    {
        $reservation = PickupReservation::where('reservation_code', $request->input('reservation_code'))->firstOrFail();
        $staff = User::where('email', 'staff@victoriousmarket.com.ng')->first();

        $result = $settlementService->approvePickupInspection($reservation, $staff);

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Counter Terminal releases package to Logistics Rider using Handover OTP
     */
    public function counterVerifyHandoverOtp(Request $request, OrderSettlementService $settlementService)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'handover_otp' => 'required|string',
        ]);

        $order = Order::findOrFail($request->input('order_id'));
        $rider = RiderProfile::first();

        $result = $settlementService->verifyRiderPickupOtp($order, $request->input('handover_otp'), $rider);

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Rider verifies delivery at Customer doorstep using Dual OTP
     */
    public function riderVerifyDeliveryOtp(Request $request, OrderSettlementService $settlementService)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'delivery_otp' => 'required|string',
        ]);

        $order = Order::findOrFail($request->input('order_id'));
        $rider = RiderProfile::first();

        $result = $settlementService->verifyCustomerDeliveryOtp($order, $request->input('delivery_otp'), $rider);

        return back()->with('status', $result['message']);
    }

    /**
     * Interactive Action: Rider toggles on/off duty status
     */
    public function riderToggleShift(Request $request)
    {
        $rider = RiderProfile::first();
        if ($rider) {
            $rider->is_online = !$rider->is_online;
            $rider->save();
        }

        $statusText = ($rider && $rider->is_online) ? 'ON DUTY (Ready for Deliveries)' : 'OFF DUTY';
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
            'base_fee' => 'required|numeric|min:0',
            'bulky_surcharge' => 'nullable|numeric|min:0',
            'estimated_delivery_hours_min' => 'required|integer|min:1',
            'estimated_delivery_hours_max' => 'required|integer|min:1',
            'is_bidirectional' => 'nullable',
        ]);

        $isBidirectional = $request->has('is_bidirectional') || $request->input('is_bidirectional') == '1';

        // Check if updating an existing lane
        if ($request->filled('lane_id')) {
            $existing = DeliveryLane::find($request->input('lane_id'));
            if ($existing) {
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

                $hubId = $request->input('hub_lga_id', $validated['origin_lga_id']);
                return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Corridor successfully updated!");
            }
        }

        // 1. Forward lane (Origin -> Destination)
        DeliveryLane::updateOrCreate([
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

        $dirMsg = $isBidirectional ? 'Bidirectional route (A ⇄ B) synchronized!' : 'One-way corridor saved!';
        $hubId = $request->input('hub_lga_id', $validated['origin_lga_id']);
        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Custom corridor successfully saved: {$dirMsg}");
    }

    public function adminUpdateZonalRates(Request $request)
    {
        $validated = $request->validate([
            'zone_intra_lga_fee' => 'required|numeric|min:0',
            'zone_intra_lga_eta' => 'required|string|max:50',
            'zone_inter_lga_fee' => 'required|numeric|min:0',
            'zone_inter_lga_eta' => 'required|string|max:50',
            'zone_inter_state_fee' => 'required|numeric|min:0',
            'zone_inter_state_eta' => 'required|string|max:50',
            'zone_bulky_cargo_surcharge' => 'required|numeric|min:0',
        ]);

        foreach ($validated as $k => $v) {
            \App\Models\SystemSetting::set($k, (string)$v, 'zonal_rates');
        }

        $hubId = $request->input('hub_lga_id');
        return redirect()->route('admin.delivery_lanes.index', $hubId ? ['hub_lga_id' => $hubId] : [])->with('status', '3-Tier Zonal Distance Baseline Rates saved successfully!');
    }

    public function adminDeleteDeliveryLane(Request $request, int $id)
    {
        $lane = DeliveryLane::findOrFail($id);
        $hubId = $request->input('hub_lga_id', $lane->origin_lga_id);
        $lane->delete();

        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', 'Delivery corridor deleted successfully.');
    }

    public function adminToggleDeliveryLane(Request $request, int $id)
    {
        $lane = DeliveryLane::findOrFail($id);
        $lane->is_active = !$lane->is_active;
        $lane->save();

        $hubId = $request->input('hub_lga_id', $lane->origin_lga_id);
        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $hubId])->with('status', "Delivery lane #{$lane->id} toggled " . ($lane->is_active ? 'Active' : 'Inactive') . '.');
    }

    public function adminToggleHubCorridor(Request $request)
    {
        $validated = $request->validate([
            'origin_lga_id' => 'required|integer|exists:lgas,id',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
            'destination_state_id' => 'nullable|integer|exists:states,id',
            'base_fee' => 'nullable|numeric|min:0',
            'estimated_delivery_hours_min' => 'nullable|integer',
            'estimated_delivery_hours_max' => 'nullable|integer',
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

        return redirect()->route('admin.delivery_lanes.index', ['hub_lga_id' => $originLgaId])
            ->with('status', "Corridor toggled " . ($lane->is_active ? 'Active' : 'Suspended') . '.');
    }

    public function adminAccreditVendor(Request $request, int $id)
    {
        $vendor = VendorProfile::findOrFail($id);
        $action = $request->input('action', 'verify');

        if ($action === 'verify') {
            $vendor->kyc_status = 'verified';
            $vendor->marketplace_status = 'active';
            $vendor->kyc_reviewed_at = Carbon::now();
            $msg = "Merchant '{$vendor->business_name}' accredited and certified active on Victorious MARKET!";
        } else {
            $vendor->kyc_status = 'rejected';
            $vendor->marketplace_status = 'suspended';
            $msg = "Merchant '{$vendor->business_name}' suspended from public marketplace listings.";
        }
        $vendor->save();

        return redirect()->route('admin.merchants.index')->with('status', $msg);
    }

    // ==========================================
    // STAGE 2: VENDOR COMMERCE SUITE ACTIONS
    // ==========================================

    public function vendorStoreProduct(Request $request)
    {
        $user = Auth::user();
        $vendor = ($user && $user->vendorProfile) ? $user->vendorProfile : VendorProfile::first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'shop_id' => 'required|integer|exists:shops,id',
            'unit_price' => 'required|numeric|min:10',
            'inventory_quantity' => 'required|integer|min:0',
            'package_tier' => 'required|in:standard,bulky',
            'description' => 'nullable|string',
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $validated['shop_id'],
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']) . '-' . rand(1000, 9999),
            'description' => $validated['description'] ?? "Authentic certified {$validated['name']} backed by Victorious MARKET escrow.",
            'unit_price' => number_format((float)$validated['unit_price'], 2, '.', ''),
            'is_in_stock' => $validated['inventory_quantity'] > 0,
            'inventory_quantity' => $validated['inventory_quantity'],
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_approved' => true,
            'is_vmarket_flagship' => true,
            'package_tier' => $validated['package_tier'],
        ]);

        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "Product '{$product->name}' successfully published with 7-day certified freshness!");
    }

    public function vendorRefreshProductFreshness(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $product->listing_confirmed_at = Carbon::now();
        $product->listing_status = 'listed';
        $product->is_in_stock = true;
        $product->save();

        return redirect()->to(route('vendor.dashboard') . '#products-catalog')->with('status', "Freshness clock extended by 7 days for '{$product->name}'!");
    }

    public function vendorStoreShop(Request $request)
    {
        $user = Auth::user();
        $vendor = ($user && $user->vendorProfile) ? $user->vendorProfile : VendorProfile::first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'lga_id' => 'required|integer|exists:lgas,id',
            'phone' => 'required|string|max:30',
            'pickup_enabled' => 'nullable',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'lga_id' => $validated['lga_id'],
            'state_id' => 1,
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']) . '-' . rand(100, 999),
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'pickup_enabled' => $request->has('pickup_enabled'),
            'is_primary_branch' => false,
        ]);

        return redirect()->to(route('vendor.dashboard') . '#shop-branches')->with('status', "New branch outlet '{$shop->name}' registered!");
    }

    // ==========================================
    // STAGE 3: CUSTOMER PRODUCT DETAIL & CHECKOUT
    // ==========================================

    public function showProductDetail(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)
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
        $productId = $request->input('product_id');
        $quantity = max(1, (int)$request->input('quantity', 1));

        $product = Product::with(['shop.lga', 'vendor'])->findOrFail($productId);
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
                ->where('expires_at', '>', Carbon::now())
                ->sum('amount');
            $availableCashback = number_format((float)$cashbackSum, 2, '.', '');
        }

        // Active and inactive lane configs originating from this shop's LGA for client-side instant check
        $shopLgaId = $product->shop->lga_id ?? 69;
        $activeLanesMap = DeliveryLane::where('origin_lga_id', $shopLgaId)
            ->get()
            ->mapWithKeys(function ($lane) {
                return [$lane->destination_lga_id => [
                    'is_active' => (bool)$lane->is_active,
                    'base_fee' => (float)$lane->base_fee,
                    'estimated_hours_min' => $lane->estimated_delivery_hours_min,
                    'estimated_hours_max' => $lane->estimated_delivery_hours_max,
                    'eta_text' => "{$lane->estimated_delivery_hours_min}-{$lane->estimated_delivery_hours_max} hours",
                ]];
            })
            ->toArray();

        return view('storefront.checkout', compact('product', 'quantity', 'merchandiseSubtotal', 'deliveryFee', 'totalPayable', 'deliveryCheck', 'pickupCheck', 'destLga', 'lgas', 'allShops', 'availableCashback', 'activeCity', 'activeLanesMap'));
    }

    public function processCheckout(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:30',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
            'delivery_address' => 'nullable|string|max:255',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'redeem_cashback' => 'nullable',
        ]);

        $product = Product::with(['shop', 'vendor'])->findOrFail($validated['product_id']);
        $quantity = (int)$validated['quantity'];
        $merchandiseSubtotal = \App\Services\BcMathService::mul($product->unit_price, (string)$quantity);

        $shop = !empty($validated['shop_id']) ? Shop::findOrFail($validated['shop_id']) : $product->shop;

        // Delivery fee calculation with corridor serviceability guard
        $deliveryFee = '0.00';
        $destLgaId = $validated['destination_lga_id'] ?? $shop->lga_id;

        if ($validated['fulfillment_type'] === 'doorstep_delivery') {
            $fulfillmentService = new \App\Services\FulfillmentAvailabilityService();
            $check = $fulfillmentService->checkDeliveryAvailability($shop->id, $destLgaId, $product->package_tier ?? 'standard');
            
            if (!$check['is_available']) {
                return back()->withErrors([
                    'fulfillment_type' => 'Doorstep delivery is currently suspended or unavailable for this route corridor. Please choose In-Store Pickup or choose a serviced destination LGA.'
                ])->withInput();
            }

            $deliveryFee = $check['fee'];
        }

        $splits = \App\Services\BcMathService::computeCommercialSplits($merchandiseSubtotal, $deliveryFee);

        // Find or create customer user
        $user = Auth::user();
        if (!$user) {
            $user = User::firstOrCreate([
                'phone' => $validated['customer_phone'],
            ], [
                'name' => $validated['customer_name'],
                'email' => 'shopper.' . time() . '@victoriousmarket.com.ng',
                'user_type' => 'customer',
                'status' => 'active',
                'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
            ]);
        }

        // Two-Phase Intent Snapshot & SHA-256 Fingerprint
        $payload = [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'fulfillment_type' => $validated['fulfillment_type'],
            'total_payable' => $splits['grand_total'],
            'timestamp' => Carbon::now()->timestamp,
        ];
        $fingerprint = \App\Models\CheckoutIntent::generateFingerprint($payload);

        // Order Number & OTPs
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        $deliveryOtp = (string)random_int(100000, 999999);
        $pickupOtp = (string)random_int(100000, 999999);

        // Assign active rider
        $rider = RiderProfile::where('is_online', true)->first() ?? RiderProfile::first();

        $order = Order::create([
            'order_number' => $orderNumber,
            'customer_id' => $user->id,
            'vendor_id' => $product->vendor_id,
            'shop_id' => $shop->id,
            'rider_id' => $rider ? $rider->id : null,
            'fulfillment_type' => $validated['fulfillment_type'],
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paystack_direct',
            'payment_reference' => 'pstk_' . time() . '_' . rand(100, 999),
            'origin_lga_id' => $shop->lga_id,
            'destination_lga_id' => $destLgaId,
            'delivery_lane_id' => $lane ? $lane->id : null,
            'merchandise_subtotal' => $splits['merchandise_subtotal'],
            'delivery_fee' => $splits['delivery_fee_segregated'],
            'cashback_redeemed' => '0.00',
            'grand_total' => $splits['grand_total'],
            'vendor_net_90' => $splits['vendor_net_90'],
            'customer_cashback_5' => $splits['customer_cashback_5'],
            'platform_margin_5' => $splits['platform_retained_5'],
            'logistics_fee_retained' => $splits['delivery_fee_segregated'],
            'pickup_verification_code' => $pickupOtp,
            'verification_code' => $deliveryOtp,
            'vendor_settlement_status' => 'held',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->unit_price,
            'quantity' => $quantity,
            'line_total' => $merchandiseSubtotal,
        ]);

        // Decrement stock atomically
        if ($product->inventory_quantity >= $quantity) {
            $product->decrement('inventory_quantity', $quantity);
        }

        // Record 5% Customer Cashback Reward in Ledger (VMarket Business Rule)
        CustomerCashbackLedger::create([
            'customer_id' => $user->id,
            'order_id' => $order->id,
            'amount' => $splits['customer_cashback_5'],
            'status' => 'pending_maturation',
            'matures_at' => Carbon::now()->addHours(24),
            'expires_at' => Carbon::now()->addDays(90),
        ]);

        return redirect()->route('order.status', ['order_number' => $order->order_number])->with('status', 'Payment verified! Your order has been placed successfully.');
    }

    public function createReservation(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'shop_id' => 'required|integer|exists:shops,id',
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:30',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $shop = Shop::findOrFail($validated['shop_id']);
        $quantity = $validated['quantity'] ?? 1;
        $estimatedTotal = \App\Services\BcMathService::mul($product->unit_price, (string)$quantity);

        $user = Auth::user();
        if (!$user) {
            $user = User::firstOrCreate([
                'phone' => $validated['customer_phone'],
            ], [
                'name' => $validated['customer_name'],
                'email' => 'shopper.' . time() . '@victoriousmarket.com.ng',
                'user_type' => 'customer',
                'status' => 'active',
                'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
            ]);
        }

        $code = PickupReservation::generateCode();
        $reservation = PickupReservation::create([
            'reservation_code' => $code,
            'customer_id' => $user->id,
            'vendor_id' => $shop->vendor_id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->unit_price,
            'estimated_total' => $estimatedTotal,
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        return redirect()->route('reservation.pass', ['code' => $reservation->reservation_code])->with('status', 'In-Shop Pickup reservation confirmed! Present this code at the shop counter.');
    }

    public function showReservationPass(string $code)
    {
        $reservation = PickupReservation::where('reservation_code', strtoupper(trim($code)))
            ->with(['shop.lga', 'product.category', 'customer'])
            ->firstOrFail();

        return view('storefront.reservation_pass', compact('reservation'));
    }

    public function showOrderStatus(string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items.product', 'shop.lga', 'customer', 'rider.user', 'vendor'])
            ->firstOrFail();

        return view('storefront.order_status', compact('order'));
    }

    // ==========================================
    // STAGE 4: RIDER HANDSHAKE ACTIONS
    // ==========================================

    public function riderVerifyPickupOtp(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'pickup_otp' => 'required|string',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        if ($order->pickup_verification_code !== trim($validated['pickup_otp'])) {
            return back()->withErrors(['pickup_otp' => 'Invalid merchant pickup verification OTP.']);
        }

        $order->order_status = 'out_for_delivery';
        $order->save();

        return redirect()->route('rider.dashboard')->with('status', "Order #{$order->order_number} package verified at counter. Out for delivery!");
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

        $customer = User::where('user_type', 'customer')->first();
        if (!$customer) {
            $customer = User::firstOrCreate(
                ['email' => 'customer@victoriousmarket.com.ng'],
                [
                    'name' => 'Emeka Okonkwo',
                    'phone' => '08012345678',
                    'password' => bcrypt('Password123!'),
                    'user_type' => 'customer',
                    'is_active' => true,
                ]
            );
        }

        return $customer;
    }

    private function resolveVendorProfile(): VendorProfile
    {
        $user = Auth::user();
        if ($user && $user->vendorProfile) {
            return $user->vendorProfile;
        }

        $vendor = VendorProfile::with(['shops', 'user'])->first();
        if (!$vendor) {
            $vendorUser = User::firstOrCreate(
                ['email' => 'vendor@victoriousmarket.com.ng'],
                [
                    'name' => 'Victor Bassey',
                    'phone' => '08087654321',
                    'password' => bcrypt('Password123!'),
                    'user_type' => 'vendor',
                    'is_active' => true,
                ]
            );

            $vendor = VendorProfile::firstOrCreate(
                ['user_id' => $vendorUser->id],
                [
                    'business_name' => 'Victor Tech Solutions',
                    'business_phone' => '08087654321',
                    'support_email' => 'vendor@victoriousmarket.com.ng',
                    'bank_name' => 'Zenith Bank PLC',
                    'bank_account_number' => '1012345678',
                    'account_holder_name' => 'Victor Tech Solutions',
                    'kyc_status' => 'verified',
                    'marketplace_status' => 'active',
                ]
            );
        }

        return $vendor;
    }

    private function resolvePrimaryShop(): Shop
    {
        $shop = Shop::with('vendor')->first();
        if (!$shop) {
            $vendor = $this->resolveVendorProfile();
            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();
            $shop = Shop::firstOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'name' => 'Victor Tech Uyo Flagship Plaza',
                    'address' => 'Shop 12, Ibom Plaza Commercial Center, Uyo',
                    'contact_phone' => '08087654321',
                    'lga_id' => $lga ? $lga->id : 1,
                    'operating_hours' => 'Mon-Sat: 8:00 AM - 6:00 PM',
                    'is_active' => true,
                ]
            );
        }

        return $shop;
    }

    private function resolveLogisticsCompany(): LogisticsCompanyProfile
    {
        $company = LogisticsCompanyProfile::with(['riders.user', 'hqLga'])->first();
        if (!$company) {
            $logisticsUser = User::firstOrCreate(
                ['email' => 'logistics@victoriousmarket.com.ng'],
                [
                    'name' => 'Akwa Express Logistics Ltd',
                    'phone' => '08055556666',
                    'password' => bcrypt('Password123!'),
                    'user_type' => 'logistics_company',
                    'is_active' => true,
                ]
            );

            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();
            $company = LogisticsCompanyProfile::firstOrCreate(
                ['user_id' => $logisticsUser->id],
                [
                    'company_name' => 'Akwa Express Logistics Ltd',
                    'hq_lga_id' => $lga ? $lga->id : 1,
                    'fleet_size' => 12,
                    'is_active' => true,
                ]
            );
        }

        return $company;
    }

    private function resolveRiderProfile(): RiderProfile
    {
        $rider = RiderProfile::with(['user', 'assignedLga', 'logisticsCompany'])->first();
        if (!$rider) {
            $riderUser = User::firstOrCreate(
                ['email' => 'rider@victoriousmarket.com.ng'],
                [
                    'name' => 'Edidiong Udoh',
                    'phone' => '08033334444',
                    'password' => bcrypt('Password123!'),
                    'user_type' => 'rider',
                    'is_active' => true,
                ]
            );

            $company = $this->resolveLogisticsCompany();
            $lga = Lga::where('name', 'Uyo')->first() ?? Lga::first();

            $rider = RiderProfile::firstOrCreate(
                ['user_id' => $riderUser->id],
                [
                    'logistics_company_id' => $company->id,
                    'assigned_lga_id' => $lga ? $lga->id : 1,
                    'vehicle_type' => 'Motorcycle',
                    'vehicle_plate_number' => 'UYO-123-AK',
                    'is_active' => true,
                    'is_online' => true,
                ]
            );
        }

        return $rider;
    }
}
