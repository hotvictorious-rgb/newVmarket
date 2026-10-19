<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BannerApiController;
use App\Http\Controllers\Api\CheckoutAndReservationApiController;
use App\Http\Controllers\Api\CustomerAddressApiController;
use App\Http\Controllers\Api\CustomerOrderApiController;
use App\Http\Controllers\Api\CustomerReviewApiController;
use App\Http\Controllers\Api\FulfillmentApiController;
use App\Http\Controllers\Api\GeographyApiController;
use App\Http\Controllers\Api\LegalApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\RiderTerminalApiController;
use App\Http\Controllers\Api\SystemConfigApiController;
use App\Http\Controllers\Api\VendorTerminalApiController;
use App\Http\Controllers\Web\PaystackPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 0. Remote System & Mobile App Dynamic Configuration (Public)
    Route::get('config', [SystemConfigApiController::class, 'show']);

    // 0.1 Legal & Regulatory Compliance (Public)
    Route::get('legal', [LegalApiController::class, 'index']);
    Route::get('legal/{slug}', [LegalApiController::class, 'show']);

    // 1. Canonical Geography (Public)
    Route::prefix('geography')->group(function () {
        Route::get('countries', [GeographyApiController::class, 'countries']);
        Route::get('states/{country_id}', [GeographyApiController::class, 'states']);
        Route::get('lgas/{state_id}', [GeographyApiController::class, 'lgas']);
    });

    // 2. Fulfillment Availability & Lane Calculation (Public)
    Route::post('fulfillment/availability', [FulfillmentApiController::class, 'checkAvailability']);
    Route::post('fulfillment/delivery-fee', [FulfillmentApiController::class, 'deliveryFee']);

    // 3. Catalog & Products (Public)
    Route::get('categories', [ProductApiController::class, 'categories']);
    Route::get('shops', [ProductApiController::class, 'shops']);
    Route::get('products', [ProductApiController::class, 'index']);
    Route::get('products/{id}', [ProductApiController::class, 'show']);
    Route::get('products/{id}/reviews', [CustomerReviewApiController::class, 'indexForProduct']);

    // 4. Storefront & Mobile Dynamic Banners (Public)
    Route::get('banners', [BannerApiController::class, 'index']);

    // 5. Pickup Reservation Lookup & Order Tracking (Public / Hybrid)
    Route::get('pickup-reservations/{code}', [CheckoutAndReservationApiController::class, 'showPickupReservation']);
    Route::get('order/track', [CustomerOrderApiController::class, 'trackOrder']);
    Route::get('order/track-order', [CustomerOrderApiController::class, 'trackOrder']);

    // 6. Authentication
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthApiController::class, 'register']);
        Route::post('login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');
    });

    // Paystack Webhook (Public, Cryptographically Verified via HMAC-SHA512)
    Route::post('payment/paystack/webhook', [PaystackPaymentController::class, 'handleWebhook']);

    // 7. Authenticated Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // Unified Multi-Channel Notification & FCM Token Registry
        Route::prefix('notifications')->group(function () {
            Route::post('device-token', [\App\Http\Controllers\Api\DeviceTokenApiController::class, 'registerToken']);
            Route::delete('device-token', [\App\Http\Controllers\Api\DeviceTokenApiController::class, 'revokeToken']);
            Route::get('in-app', [\App\Http\Controllers\Api\DeviceTokenApiController::class, 'listInApp']);
            Route::post('mark-read/{id?}', [\App\Http\Controllers\Api\DeviceTokenApiController::class, 'markAsRead']);
        });
        // Customer Profile & Session
        Route::get('auth/me', [AuthApiController::class, 'me']);
        Route::get('customer/info', [AuthApiController::class, 'me']);
        Route::post('auth/logout', [AuthApiController::class, 'logout']);
        Route::post('customer/update-profile', [AuthApiController::class, 'updateProfile']);

        // Customer Address Book (Full CRUD)
        Route::prefix('customer/address')->group(function () {
            Route::get('list', [CustomerAddressApiController::class, 'index']);
            Route::post('add', [CustomerAddressApiController::class, 'store']);
            Route::post('update/{id?}', [CustomerAddressApiController::class, 'update']);
            Route::match(['delete', 'post'], '{id?}', [CustomerAddressApiController::class, 'destroy']);
            Route::delete('delete/{id?}', [CustomerAddressApiController::class, 'destroy']);
        });

        // Checkout Intents & Delivery Checkout
        Route::post('checkout/intent', [CheckoutAndReservationApiController::class, 'createIntent']);

        // Victorious Cashback 5% Reward Ledger API (API Contract Sec 5.3)
        Route::get('cashback/summary', [CheckoutAndReservationApiController::class, 'cashbackSummary']);
        Route::get('customer/cashback/summary', [CheckoutAndReservationApiController::class, 'cashbackSummary']);
        Route::get('cashback/list', [CheckoutAndReservationApiController::class, 'cashbackList']);
        Route::get('customer/cashback/list', [CheckoutAndReservationApiController::class, 'cashbackList']);
        Route::get('customer/cashback', [CheckoutAndReservationApiController::class, 'cashbackSummary']);

        // In-Shop Inspection & Pickup Reservations (Inspect First, Pay Later Engine)
        Route::prefix('customer/pickup-reservations')->group(function () {
            Route::get('', [CheckoutAndReservationApiController::class, 'listPickupReservations']);
            Route::post('', [CheckoutAndReservationApiController::class, 'createPickupReservation']);
            Route::get('{code}', [CheckoutAndReservationApiController::class, 'showPickupReservation']);
            Route::post('{code}/pay', [CheckoutAndReservationApiController::class, 'payPickupReservation']);
            Route::post('{code}/cancel', [CheckoutAndReservationApiController::class, 'cancelPickupReservation']);
        });
        Route::post('pickup-reservations/create', [CheckoutAndReservationApiController::class, 'createPickupReservation']);

        // Customer Orders, Handover & 24h Post-Delivery Disputes
                // Customer Verified Product Reviews (Only Delivered, Non-Returned Purchases)
        Route::post('customer/reviews', [CustomerReviewApiController::class, 'store']);
        Route::get('customer/reviews/my-reviews', [CustomerReviewApiController::class, 'myReviews']);
        Route::prefix('customer/orders')->group(function () {
            Route::get('', [CustomerOrderApiController::class, 'index']);
            Route::post('checkout', [CustomerOrderApiController::class, 'executeCheckout']);
            Route::post('verify-payment', [CustomerOrderApiController::class, 'verifyPayment']);
            Route::get('{order_number}', [CustomerOrderApiController::class, 'show']);
            Route::post('{order_number}/confirm-delivery', [CustomerOrderApiController::class, 'confirmDelivery']);
            Route::post('{order_number}/dispute', [CustomerOrderApiController::class, 'createDispute']);
            Route::get('{order_number}/dispute', [CustomerOrderApiController::class, 'getDispute']);
        });

        // Customer Delivery Handshake Aliases
        Route::post('customer/order/confirm-doorstep-delivery', [CustomerOrderApiController::class, 'confirmDeliveryByBody']);
        Route::post('customer/order/confirm-inshop-pickup', [CustomerOrderApiController::class, 'confirmDeliveryByBody']);

        // Customer Item-Level Returns & Collection Points (24h Configurable Window)
        Route::get('customer/wallet', [CustomerOrderApiController::class, 'walletSummary']);
        Route::post('customer/orders/reorder-rejected-items', [CustomerOrderApiController::class, 'reorderRejectedItems']);
        Route::prefix('customer/returns')->group(function () {
            Route::get('', [CustomerOrderApiController::class, 'listReturnCases']);
            Route::post('create', [CustomerOrderApiController::class, 'createReturnCase']);
            Route::post('reorder', [CustomerOrderApiController::class, 'reorderRejectedItems']);
            Route::get('{case_number}', [CustomerOrderApiController::class, 'showReturnCase']);
        });

        // Delivery Rider Terminal
        Route::prefix('rider')->group(function () {
            Route::post('device-token', [\App\Http\Controllers\Api\DeviceTokenApiController::class, 'registerToken']);
            Route::get('dashboard', [RiderTerminalApiController::class, 'dashboard']);
            Route::post('toggle-duty', [RiderTerminalApiController::class, 'toggleDuty']);
            Route::get('orders/active', [RiderTerminalApiController::class, 'activeOrders']);
            Route::get('orders/history', [RiderTerminalApiController::class, 'history']);
            Route::get('returns/active', [RiderTerminalApiController::class, 'activeReturns']);
            Route::post('orders/mark-arrived', [RiderTerminalApiController::class, 'markArrived']);
            Route::post('orders/delivery-attempt-failed', [RiderTerminalApiController::class, 'recordDeliveryAttemptFailed']);
            Route::post('orders/verify-pickup-otp', [RiderTerminalApiController::class, 'verifyPickupOtp']);
            Route::post('orders/verify-delivery-otp', [RiderTerminalApiController::class, 'verifyDeliveryOtp']);
            Route::post('orders/doorstep-handover', [RiderTerminalApiController::class, 'doorstepHandover']);
            Route::post('orders/verify-vendor-return-otp', [RiderTerminalApiController::class, 'verifyVendorReturnOtp']);
            Route::post('returns/verify-vendor-otp', [RiderTerminalApiController::class, 'verifyVendorReturnOtp']);
        });

        // Sovereign Vendor App Terminal
        Route::prefix('vendor')->group(function () {
            // Dashboard & FinTech Metrics
            Route::get('dashboard', [VendorTerminalApiController::class, 'dashboard']);

            // Orders & Handover Workflow
            Route::get('orders', [VendorTerminalApiController::class, 'orders']);
            Route::get('orders/{id}', [VendorTerminalApiController::class, 'showOrder']);
            Route::post('orders/{id}/ready-for-handover', [VendorTerminalApiController::class, 'markHandoverReady']);
            Route::post('orders/verify-handover-otp', [VendorTerminalApiController::class, 'verifyHandoverOtp']);

            // Inbound Returns & Counter Dispute
            Route::get('returns', [VendorTerminalApiController::class, 'returns']);
            Route::post('returns/{case_number}/dispute', [VendorTerminalApiController::class, 'disputeReturn']);

            // ₦0.00 Inspection Reservations
            Route::get('pickup-reservations', [VendorTerminalApiController::class, 'pickupReservations']);
            Route::post('pickup-reservations/verify-inspection', [VendorTerminalApiController::class, 'verifyInspection']);

            // Products & 7-Day Freshness Governance
            Route::get('products', [VendorTerminalApiController::class, 'products']);
            Route::post('products', [VendorTerminalApiController::class, 'storeProduct']);
            Route::post('products/{id}', [VendorTerminalApiController::class, 'updateProduct']);
            Route::post('products/{id}/freshness', [VendorTerminalApiController::class, 'refreshFreshness']);
            Route::post('products/{id}/toggle-stock', [VendorTerminalApiController::class, 'toggleStock']);

            // Financial Ledger, Payouts & 48h Cooldown
            Route::get('payouts', [VendorTerminalApiController::class, 'payouts']);
            Route::post('payouts', [VendorTerminalApiController::class, 'requestPayout']);
            Route::post('bank/update', [VendorTerminalApiController::class, 'updateBank']);

            // Vendor-Managed Delivery Personnel & Riders
            Route::get('riders', [VendorTerminalApiController::class, 'riders']);
            Route::post('riders', [VendorTerminalApiController::class, 'storeRider']);
            Route::patch('riders/{id}/status', [VendorTerminalApiController::class, 'updateRiderStatus']);
            Route::post('orders/{id}/assign-rider', [VendorTerminalApiController::class, 'assignRiderToOrder']);
        });
    });
});
