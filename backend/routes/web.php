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

// Portal 1: Customer Shopper Login
Route::get('/login', [WebAuthController::class, 'showCustomerLogin'])->name('login');
Route::get('/customer/login', [WebAuthController::class, 'showCustomerLogin'])->name('customer.login');
Route::post('/customer/login', [WebAuthController::class, 'loginCustomer'])->name('customer.login.post');

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
    Route::post('/disburse-settlement', [PortalController::class, 'adminDisburseSettlement'])->name('admin.disburse.settlement');
    Route::post('/settings/announcement', [PortalController::class, 'adminUpdateAnnouncementSettings'])->name('admin.settings.announcement');
});

// 2. Admin Staff (Support, Moderation, Operations)
Route::prefix('admin-staff')->group(function () {
    Route::get('/', [PortalController::class, 'adminStaffDashboard'])->name('admin.staff.dashboard');
});

// 3. Vendor Merchant Desk
Route::prefix('vendor')->group(function () {
    Route::get('/', [PortalController::class, 'vendorDashboard'])->name('vendor.dashboard');
    Route::post('/approve-inspection', [PortalController::class, 'vendorApproveInspection'])->name('vendor.approve.inspection');
});

// 4. In-Shop Branch Counter Staff Terminal
Route::prefix('counter')->group(function () {
    Route::get('/', [PortalController::class, 'counterDashboard'])->name('counter.dashboard');
    Route::post('/verify-handover-otp', [PortalController::class, 'counterVerifyHandoverOtp'])->name('counter.verify.handover.otp');
});

// 5. Logistics Fleet Operations Desk
Route::prefix('logistics')->group(function () {
    Route::get('/', [PortalController::class, 'logisticsDashboard'])->name('logistics.dashboard');
});

// 6. Delivery Rider Dispatch Cockpit
Route::prefix('rider')->group(function () {
    Route::get('/', [PortalController::class, 'riderDashboard'])->name('rider.dashboard');
    Route::post('/verify-delivery-otp', [PortalController::class, 'riderVerifyDeliveryOtp'])->name('rider.verify.delivery.otp');
    Route::post('/toggle-shift', [PortalController::class, 'riderToggleShift'])->name('rider.toggle.shift');
});

// 7. Customer Shopper Portal
Route::prefix('customer')->group(function () {
    Route::get('/', [PortalController::class, 'customerDashboard'])->name('customer.dashboard');
});

