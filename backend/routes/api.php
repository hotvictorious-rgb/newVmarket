<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BannerApiController;
use App\Http\Controllers\Api\CheckoutAndReservationApiController;
use App\Http\Controllers\Api\CustomerAddressApiController;
use App\Http\Controllers\Api\CustomerOrderApiController;
use App\Http\Controllers\Api\FulfillmentApiController;
use App\Http\Controllers\Api\GeographyApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\RiderTerminalApiController;
use App\Http\Controllers\Api\SystemConfigApiController;
use App\Http\Controllers\Api\VendorTerminalApiController;
use App\Http\Controllers\Web\PaystackPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 0. Remote System & Mobile App Dynamic Configuration (Public)
    Route::get('config', [SystemConfigApiController::class, 'show']);

    // 1. Canonical Geography (Public)
    Route::prefix('geography')->group(function () {
        Route::get('countries', [GeographyApiController::class, 'countries']);
        Route::get('states/{country_id}', [GeographyApiController::class, 'states']);
        Route::get('lgas/{state_id}', [GeographyApiController::class, 'lgas']);
    });

    // 2. Fulfillment Availability & Lane Calculation (Public)
    Route::post('fulfillment/availability', [FulfillmentApiController::class, 'checkAvailability']);

    // 3. Catalog & Products (Public)
    Route::get('categories', [ProductApiController::class, 'categories']);
    Route::get('products', [ProductApiController::class, 'index']);
    Route::get('products/{id}', [ProductApiController::class, 'show']);

    // 4. Storefront & Mobile Dynamic Banners (Public)
    Route::get('banners', [BannerApiController::class, 'index']);

    // 5. Pickup Reservation Lookup (Public)
    Route::get('pickup-reservations/{code}', [CheckoutAndReservationApiController::class, 'showPickupReservation']);

    // 6. Authentication
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthApiController::class, 'register']);
        Route::post('login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');
    });

    // Paystack Webhook (Public, Cryptographically Verified via HMAC-SHA512)
    Route::post('payment/paystack/webhook', [PaystackPaymentController::class, 'handleWebhook']);

    // 7. Authenticated Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthApiController::class, 'me']);

        // Customer Address Book
        Route::prefix('customer/address')->group(function () {
            Route::get('list', [CustomerAddressApiController::class, 'index']);
            Route::post('add', [CustomerAddressApiController::class, 'store']);
        });

        // Checkout Intents & In-Shop Reservations
        Route::post('checkout/intent', [CheckoutAndReservationApiController::class, 'createIntent']);
        Route::post('pickup-reservations/create', [CheckoutAndReservationApiController::class, 'createPickupReservation']);
        Route::get('customer/cashback', [CheckoutAndReservationApiController::class, 'cashbackBalance']);

        // Customer Orders & 5-Stage Live Handshake Tracking
        Route::prefix('customer/orders')->group(function () {
            Route::get('', [CustomerOrderApiController::class, 'index']);
            Route::post('checkout', [CustomerOrderApiController::class, 'executeCheckout']);
            Route::post('verify-payment', [CustomerOrderApiController::class, 'verifyPayment']);
            Route::get('{order_number}', [CustomerOrderApiController::class, 'show']);
            Route::post('{order_number}/confirm-delivery', [CustomerOrderApiController::class, 'confirmDelivery']);
        });

        // Delivery Rider Terminal
        Route::prefix('rider')->group(function () {
            Route::get('dashboard', [RiderTerminalApiController::class, 'dashboard']);
            Route::post('toggle-duty', [RiderTerminalApiController::class, 'toggleDuty']);
            Route::get('orders/active', [RiderTerminalApiController::class, 'activeOrders']);
            Route::get('orders/history', [RiderTerminalApiController::class, 'history']);
            Route::post('orders/mark-arrived', [RiderTerminalApiController::class, 'markArrived']);
            Route::post('orders/verify-pickup-otp', [RiderTerminalApiController::class, 'verifyPickupOtp']);
            Route::post('orders/verify-delivery-otp', [RiderTerminalApiController::class, 'verifyDeliveryOtp']);
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

            // ₦0.00 Inspection Reservations
            Route::get('pickup-reservations', [VendorTerminalApiController::class, 'pickupReservations']);
            Route::post('pickup-reservations/verify-inspection', [VendorTerminalApiController::class, 'verifyInspection']);

            // Products & 7-Day Freshness Governance
            Route::get('products', [VendorTerminalApiController::class, 'products']);
            Route::post('products', [VendorTerminalApiController::class, 'storeProduct']);
            Route::post('products/{id}/freshness', [VendorTerminalApiController::class, 'refreshFreshness']);
            Route::post('products/{id}/toggle-stock', [VendorTerminalApiController::class, 'toggleStock']);

            // Financial Ledger, Payouts & 48h Cooldown
            Route::get('payouts', [VendorTerminalApiController::class, 'payouts']);
            Route::post('bank/update', [VendorTerminalApiController::class, 'updateBank']);
        });
    });
});
