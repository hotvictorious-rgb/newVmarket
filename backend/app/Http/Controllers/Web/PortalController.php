<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
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
        
        $query = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug']);

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
        $latestProducts = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug'])
            ->latest()
            ->take(6)
            ->get();

        $shops = Shop::with('lga:id,name')
            ->where('pickup_enabled', true)
            ->take(6)
            ->get();

        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();
        $activeCity = session('customer_city', 'Uyo');
        $activeState = session('customer_state', 'Akwa Ibom');

        return view('storefront.index', compact('categories', 'products', 'latestProducts', 'shops', 'lgas', 'activeCity', 'activeState'));
    }

    /**
     * Set Customer Active LGA Location
     */
    public function setLocation(Request $request)
    {
        $request->validate([
            'city' => 'required|string',
            'state' => 'nullable|string',
        ]);

        session([
            'customer_city' => $request->input('city'),
            'customer_state' => $request->input('state', 'Akwa Ibom'),
        ]);

        return response()->json([
            'success' => true,
            'city' => session('customer_city'),
            'state' => session('customer_state'),
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

        $deliveryLanes = DeliveryLane::with(['originLga', 'destinationLga'])
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

        return view('admin.dashboard', compact('metrics', 'pendingSettlements', 'recentOrders', 'deliveryLanes', 'announcementSettings'));
    }

    /**
     * Super Admin Announcement & Contact Settings Update
     */
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
     * Vendor Merchant Business Dashboard
     */
    public function vendorDashboard(Request $request)
    {
        $user = Auth::user();
        $vendor = ($user && $user->vendorProfile) ? $user->vendorProfile : VendorProfile::with(['shops', 'user'])->first();
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

        return view('vendor.dashboard', compact('vendor', 'primaryShop', 'products', 'reservations', 'orders', 'vendorMetrics'));
    }

    /**
     * Branch Counter Staff Terminal Dashboard
     */
    public function counterDashboard()
    {
        $shop = Shop::with('vendor')->first();
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
     * Logistics Company Operations Dashboard
     */
    public function logisticsDashboard()
    {
        $company = LogisticsCompanyProfile::with(['riders.user', 'hqLga'])->first();
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
     * Delivery Rider Mobile Cockpit Dashboard
     */
    public function riderDashboard()
    {
        $rider = RiderProfile::with(['user', 'assignedLga', 'logisticsCompany'])->first();
        
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
     * Customer Shopper Account & Rewards Dashboard (Legacy theme_vmarket Parity)
     */
    public function customerDashboard()
    {
        $user = Auth::user();
        $customer = ($user && $user->user_type === 'customer') ? $user : User::where('user_type', 'customer')->first();

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
}
