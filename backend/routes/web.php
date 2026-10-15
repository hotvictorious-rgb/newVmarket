<?php

use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\WebAuthController;
use Illuminate\Support\Facades\Route;

// 1. Customer Storefront
Route::get('/', [PortalController::class, 'storefront'])->name('storefront');
Route::post('/set-location', [PortalController::class, 'setLocation'])->name('set.location');

// ==========================================
// THE 7 DISTINCT LOGIN PORTALS
// ==========================================

// Portal 1: Customer Shopper Login & Registration
Route::get('/login', [WebAuthController::class, 'showCustomerLogin'])->name('login');
Route::get('/customer/login', [WebAuthController::class, 'showCustomerLogin'])->name('customer.login');
Route::post('/customer/login', [WebAuthController::class, 'loginCustomer'])->name('customer.login.post');

Route::get('/register', [WebAuthController::class, 'showCustomerRegister'])->name('register');
Route::get('/customer/register', [WebAuthController::class, 'showCustomerRegister'])->name('customer.register');
Route::post('/customer/register', [WebAuthController::class, 'registerCustomer'])->name('customer.register.post');

// Google OAuth & Identity Services
Route::get('/auth/google/redirect', [WebAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [WebAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::post('/auth/google/one-tap', [WebAuthController::class, 'handleGoogleOneTap'])->name('auth.google.one_tap');

// Portal 2: Super Admin Command Gate
Route::get('/admin/login', [WebAuthController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin/login', [WebAuthController::class, 'loginAdmin'])->name('admin.login.post');

// Portal 3: Admin Staff (Finance, Dispatch, Support)
Route::get('/admin-staff/login', [WebAuthController::class, 'showAdminStaffLogin'])->name('admin.staff.login');
Route::post('/admin-staff/login', [WebAuthController::class, 'loginAdminStaff'])->name('admin.staff.login.post');

// Portal 4: Vendor Merchant Owner Desk
Route::get('/vendor/login', [WebAuthController::class, 'showVendorLogin'])->name('vendor.login');
Route::post('/vendor/login', [WebAuthController::class, 'loginVendor'])->name('vendor.login.post');

// Portal 5: In-Shop Branch Counter Staff Terminal
Route::get('/counter/login', [WebAuthController::class, 'showCounterLogin'])->name('counter.login');
Route::post('/counter/login', [WebAuthController::class, 'loginCounter'])->name('counter.login.post');

// Portal 6: Logistics Company Fleet Desk
Route::get('/logistics/login', [WebAuthController::class, 'showLogisticsLogin'])->name('logistics.login');
Route::post('/logistics/login', [WebAuthController::class, 'loginLogistics'])->name('logistics.login.post');

// Portal 7: Delivery Rider Status Portal
Route::get('/rider/login', [WebAuthController::class, 'showRiderLogin'])->name('rider.login');
Route::post('/rider/login', [WebAuthController::class, 'loginRider'])->name('rider.login.post');

// Logout Route
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');


// ==========================================
// THE 7 DISTINCT ROLE DASHBOARDS
// ==========================================

// 1. Super Admin Command Tower
Route::prefix('admin')->group(function () {
    Route::get('/', [PortalController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::get('/delivery-lanes', [PortalController::class, 'adminDeliveryLanesPage'])->name('admin.delivery_lanes.index');
    Route::get('/settlements', [PortalController::class, 'adminSettlementsPage'])->name('admin.settlements.index');
    Route::get('/announcements', [PortalController::class, 'adminAnnouncementsPage'])->name('admin.announcements.index');
    Route::get('/merchants', [PortalController::class, 'adminMerchantsPage'])->name('admin.merchants.index');
    Route::get('/orders', [PortalController::class, 'adminOrdersPage'])->name('admin.orders.index');

    Route::post('/disburse-settlement', [PortalController::class, 'adminDisburseSettlement'])->name('admin.disburse.settlement');
    Route::post('/settings/announcement', [PortalController::class, 'adminUpdateAnnouncementSettings'])->name('admin.settings.announcement');
});

// 2. Admin Staff (Support, Moderation, Operations)
Route::prefix('admin-staff')->group(function () {
    Route::get('/', [PortalController::class, 'adminStaffDashboard'])->name('admin.staff.dashboard');
    Route::get('/moderation', [PortalController::class, 'adminStaffModerationPage'])->name('admin.staff.moderation.index');
    Route::get('/orders', [PortalController::class, 'adminStaffOrdersPage'])->name('admin.staff.orders.index');
    Route::get('/inspections', [PortalController::class, 'adminStaffInspectionsPage'])->name('admin.staff.inspections.index');
    Route::get('/logs', [PortalController::class, 'adminStaffLogsPage'])->name('admin.staff.logs.index');
});

// 3. Vendor Merchant Desk
Route::prefix('vendor')->group(function () {
    Route::get('/', [PortalController::class, 'vendorDashboard'])->name('vendor.dashboard');
    Route::get('/products', [PortalController::class, 'vendorProductsPage'])->name('vendor.products.index');
    Route::get('/orders', [PortalController::class, 'vendorOrdersPage'])->name('vendor.orders.index');
    Route::get('/reservations', [PortalController::class, 'vendorReservationsPage'])->name('vendor.reservations.index');
    Route::get('/shops', [PortalController::class, 'vendorShopsPage'])->name('vendor.shops.index');
    Route::get('/payout-account', [PortalController::class, 'vendorPayoutPage'])->name('vendor.payout.index');

    Route::post('/approve-inspection', [PortalController::class, 'vendorApproveInspection'])->name('vendor.approve.inspection');
});

// 4. In-Shop Branch Counter Staff Terminal
Route::prefix('counter')->group(function () {
    Route::get('/', [PortalController::class, 'counterDashboard'])->name('counter.dashboard');
    Route::get('/inspections', [PortalController::class, 'counterInspectionsPage'])->name('counter.inspections.index');
    Route::get('/handovers', [PortalController::class, 'counterHandoversPage'])->name('counter.handovers.index');
    Route::get('/records', [PortalController::class, 'counterRecordsPage'])->name('counter.records.index');

    Route::post('/verify-handover-otp', [PortalController::class, 'counterVerifyHandoverOtp'])->name('counter.verify.handover.otp');
});

// 5. Logistics Fleet Operations Desk
Route::prefix('logistics')->group(function () {
    Route::get('/', [PortalController::class, 'logisticsDashboard'])->name('logistics.dashboard');
    Route::get('/riders', [PortalController::class, 'logisticsRidersPage'])->name('logistics.riders.index');
    Route::get('/waybills', [PortalController::class, 'logisticsWaybillsPage'])->name('logistics.waybills.index');
    Route::get('/wallet', [PortalController::class, 'logisticsWalletPage'])->name('logistics.wallet.index');
});

// 6. Delivery Rider Dispatch Cockpit
Route::prefix('rider')->group(function () {
    Route::get('/', [PortalController::class, 'riderDashboard'])->name('rider.dashboard');
    Route::get('/deliveries', [PortalController::class, 'riderDeliveriesPage'])->name('rider.deliveries.index');
    Route::get('/pickup', [PortalController::class, 'riderPickupPage'])->name('rider.pickup.index');
    Route::get('/dropoff', [PortalController::class, 'riderDropoffPage'])->name('rider.dropoff.index');
    Route::get('/earnings', [PortalController::class, 'riderEarningsPage'])->name('rider.earnings.index');

    Route::post('/verify-delivery-otp', [PortalController::class, 'riderVerifyDeliveryOtp'])->name('rider.verify.delivery.otp');
    Route::post('/toggle-shift', [PortalController::class, 'riderToggleShift'])->name('rider.toggle.shift');
});

// 7. Customer Shopper Portal
Route::prefix('customer')->group(function () {
    Route::get('/', [PortalController::class, 'customerDashboard'])->name('customer.dashboard');
    Route::get('/orders', [PortalController::class, 'customerOrdersPage'])->name('customer.orders.index');
    Route::get('/reservations', [PortalController::class, 'customerReservationsPage'])->name('customer.reservations.index');
    Route::get('/cashback', [PortalController::class, 'customerCashbackPage'])->name('customer.cashback.index');
    Route::get('/addresses', [PortalController::class, 'customerAddressesPage'])->name('customer.addresses.index');
});



// ==========================================
// SEQUENTIAL STAGE ROUTES
// ==========================================

// Stage 1: Super Admin Command Routes
Route::prefix('admin')->group(function () {
    Route::post('/delivery-lanes', [PortalController::class, 'adminStoreDeliveryLane'])->name('admin.delivery_lanes.store');
    Route::post('/delivery-lanes/zonal-rates', [PortalController::class, 'adminUpdateZonalRates'])->name('admin.delivery_lanes.zonal');
    Route::delete('/delivery-lanes/{id}', [PortalController::class, 'adminDeleteDeliveryLane'])->name('admin.delivery_lanes.delete');
    Route::post('/delivery-lanes/{id}/toggle', [PortalController::class, 'adminToggleDeliveryLane'])->name('admin.delivery_lanes.toggle');
    Route::post('/vendors/{id}/accredit', [PortalController::class, 'adminAccreditVendor'])->name('admin.vendors.accredit');
});

// Stage 2: Vendor Commerce Desk Routes
Route::prefix('vendor')->group(function () {
    Route::post('/products', [PortalController::class, 'vendorStoreProduct'])->name('vendor.products.store');
    Route::post('/products/{id}/refresh', [PortalController::class, 'vendorRefreshProductFreshness'])->name('vendor.products.refresh');
    Route::post('/shops', [PortalController::class, 'vendorStoreShop'])->name('vendor.shops.store');
});

// Stage 3: Customer Storefront, Product Detail, Checkout & Tracking Routes
Route::get('/product/{slug}', [PortalController::class, 'showProductDetail'])->name('product.detail');
Route::get('/checkout', [PortalController::class, 'showCheckout'])->name('checkout');
Route::post('/checkout/process', [PortalController::class, 'processCheckout'])->name('checkout.process');
Route::post('/reservation/create', [PortalController::class, 'createReservation'])->name('reservation.create');
Route::get('/reservation/{code}', [PortalController::class, 'showReservationPass'])->name('reservation.pass');
Route::get('/order/{order_number}', [PortalController::class, 'showOrderStatus'])->name('order.status');

// Stage 4: Rider Merchant Pickup Route
Route::prefix('rider')->group(function () {
    Route::post('/verify-pickup-otp', [PortalController::class, 'riderVerifyPickupOtp'])->name('rider.verify.pickup.otp');
});
