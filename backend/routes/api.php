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
            Route::get('{order_number}', [CustomerOrderApiController::class, 'show']);
            Route::post('{order_number}/confirm-delivery', [CustomerOrderApiController::class, 'confirmDelivery']);
        });

        // Delivery Rider Terminal
        Route::prefix('rider')->group(function () {
            Route::get('orders/active', [RiderTerminalApiController::class, 'activeOrders']);
            Route::post('orders/verify-pickup-otp', [RiderTerminalApiController::class, 'verifyPickupOtp']);
            Route::post('orders/verify-delivery-otp', [RiderTerminalApiController::class, 'verifyDeliveryOtp']);
        });

        // Vendor App Terminal
        Route::prefix('vendor')->group(function () {
            Route::post('pickup-reservations/verify-inspection', [VendorTerminalApiController::class, 'verifyInspection']);
            Route::post('orders/verify-handover-otp', [VendorTerminalApiController::class, 'verifyHandoverOtp']);
        });
    });
});
