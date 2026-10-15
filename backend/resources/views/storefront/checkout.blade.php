@extends('layouts.app')

@section('title', 'Secure Checkout — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 30px 16px 80px; max-width: 1040px;">

    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">Secure Checkout</span>
    </div>

    <!-- Main Title -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🔒</span> 
            <span>Secure Escrow Checkout</span>
        </h1>
        <div style="display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; border: 1px solid rgba(5, 150, 105, 0.2); padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #059669;">
            <span>🛡️</span> Zero-Risk Escrow Protection
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; align-items: start;">
        
        <!-- Left Column: 3-Step Checkout Sequence (Mockup Screens 10, 11, 12 Parity) -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <form method="POST" action="{{ route('checkout.process') }}" id="vmCheckoutForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="{{ $quantity }}">

                <!-- ========================================== -->
                <!-- STEP 1: CONTACT & DELIVERY ADDRESS (SCREEN 11) -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 20px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">1</span>
                        <span>Contact &amp; Delivery Address (Akwa Ibom)</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Full Name *</label>
                            <input type="text" name="customer_name" value="{{ Auth::user()->name ?? 'Shopper' }}" required placeholder="e.g. John Doe" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Phone Number (For OTP) *</label>
                            <input type="tel" name="customer_phone" value="{{ Auth::user()->phone ?? '08012345678' }}" required placeholder="e.g. 08012345678" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Destination LGA & Street Address -->
                    <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Select LGA *</label>
                            <select name="destination_lga_id" id="vmDestLgaSelect" onchange="vmHandleDestLgaChange(this.value)" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box; cursor: pointer;">
                                @foreach($lgas ?? [] as $lga)
                                    <option value="{{ $lga->id }}" {{ ($lga->id == $destLga->id) ? 'selected' : '' }}>
                                        {{ $lga->name }} — {{ $lga->state->name ?? 'Akwa Ibom' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Street &amp; House Number *</label>
                            <input type="text" name="delivery_address" placeholder="e.g. 12, Uyo Road, Uyo" value="12, Uyo Road, Uyo" required style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Landmark Assistance Field (Nigerian Market Optimization) -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #64748B; margin-bottom: 5px;">
                            Nearest Landmark / Bus Stop <span style="color: #94A3B8;">(Helps dispatch riders locate you swiftly)</span>
                        </label>
                        <input type="text" name="landmark" placeholder="e.g. Opposite Ibom Plaza / Near Champion Breweries" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- STEP 2: FULFILLMENT METHOD (SCREEN 10 & 12 PARITY) -->
                <!-- ========================================== -->
                @php
                    $isDeliveryAvailable = $deliveryCheck['is_available'] ?? true;
                @endphp

                @if($errors->has('fulfillment_type'))
                    <div style="background: #FEF2F2; border: 1.5px solid #F87171; color: #991B1B; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span>
                        <span>{{ $errors->first('fulfillment_type') }}</span>
                    </div>
                @endif

                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 20px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">2</span>
                            <span>Delivery or In-Store Pickup Choice</span>
                        </div>
                        <div id="vmCorridorStatusBadge">
                            @if($isDeliveryAvailable)
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">
                                    🟢 Serviced Corridor ({{ $product->shop->lga->name ?? 'Origin' }} &rarr; <span id="vmDestLgaName">{{ $destLga->name }}</span>)
                                </span>
                            @else
                                <span style="background: #FEF3C7; color: #D97706; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">
                                    ⚠️ Route Suspended (<span id="vmDestLgaName">{{ $destLga->name }}</span>)
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Dynamic Warning Banner for Suspended Corridors -->
                    <div id="vmSuspendedAlert" style="display: {{ $isDeliveryAvailable ? 'none' : 'flex' }}; align-items: flex-start; gap: 10px; background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 12.5px; color: #92400E; line-height: 1.45;">
                        <span style="font-size: 18px;">⚠️</span>
                        <div>
                            <strong>Doorstep delivery is currently suspended for this destination LGA.</strong><br>
                            <span>Platform logistics along this corridor is temporarily offline. Your order has been automatically switched to <strong>guaranteed In-Store Counter Pickup at &#8358;0.00</strong>. You can inspect the item physically before OTP release.</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <!-- In-Store Pickup Card -->
                        <label id="vmCardPickup" style="border: {{ $isDeliveryAvailable ? '1.5px solid #CBD5E1' : '2px solid #5E17EB' }}; background: {{ $isDeliveryAvailable ? '#FFFFFF' : '#F5F3FF' }}; border-radius: 12px; padding: 16px; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <input type="radio" name="fulfillment_type" id="vmRadioPickup" value="in_shop_pickup" {{ $isDeliveryAvailable ? '' : 'checked' }} onchange="vmSelectFulfillment('pickup')" style="accent-color: #5E17EB; width: 18px; height: 18px;">
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">FREE</span>
                            </div>
                            <strong style="display: block; font-size: 14px; color: #0F172A; margin-bottom: 3px;">🏢 In-Store Pickup</strong>
                            <span style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">Collect &amp; inspect physically from verified shop counter at {{ $product->shop->name }}</span>
                        </label>

                        <!-- Doorstep Delivery Card -->
                        <label id="vmCardDelivery" style="border: {{ $isDeliveryAvailable ? '2px solid #5E17EB' : '1.5px dashed #CBD5E1' }}; background: {{ $isDeliveryAvailable ? '#F5F3FF' : '#F8FAFC' }}; opacity: {{ $isDeliveryAvailable ? '1' : '0.6' }}; cursor: {{ $isDeliveryAvailable ? 'pointer' : 'not-allowed' }}; border-radius: 12px; padding: 16px; display: block; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <input type="radio" name="fulfillment_type" id="vmRadioDelivery" value="doorstep_delivery" {{ $isDeliveryAvailable ? 'checked' : 'disabled' }} onchange="vmSelectFulfillment('delivery')" style="accent-color: #5E17EB; width: 18px; height: 18px;">
                                <span id="vmDeliveryFeeBadge" style="background: {{ $isDeliveryAvailable ? 'rgba(94, 23, 235, 0.1)' : '#F1F5F9' }}; color: {{ $isDeliveryAvailable ? '#5E17EB' : '#94A3B8' }}; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">
                                    {{ $isDeliveryAvailable ? '&#8358;' . number_format((float)$deliveryFee, 0) : 'UNAVAILABLE' }}
                                </span>
                            </div>
                            @php
                                $isCrossLga = ($product->shop->lga_id !== $destLga->id);
                            @endphp
                            <strong id="vmDeliveryTitle" style="display: block; font-size: 14px; color: {{ $isDeliveryAvailable ? '#5E17EB' : '#64748B' }}; margin-bottom: 3px;">
                                {{ $isCrossLga ? '📍 LGA Collection Point' : '🚚 Municipal Doorstep Delivery' }}
                            </strong>
                            <span id="vmDeliveryDesc" style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">
                                @if(!$isDeliveryAvailable)
                                    Delivery route currently suspended. In-Store Pickup active.
                                @elseif($isCrossLga)
                                    Transit fleet delivers to company Collection Point in {{ $destLga->name }}. Collect using your secret 6-digit PIN.
                                @else
                                    Delivered directly to your door with Handshake OTP verification.
                                @endif
                            </span>
                        </label>
                    </div>

                    <!-- Collection Point Selector (Inter-LGA / Inter-State) -->
                    <div id="vmCollectionPointFields" style="display: {{ ($isDeliveryAvailable && $isCrossLga && isset($collectionPoints) && count($collectionPoints) > 0) ? 'block' : 'none' }}; background: #EEF2FF; border: 1.5px solid #6366F1; border-radius: 12px; padding: 16px; margin-top: 10px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 800; color: #1E1B4B;">
                                📍 Choose Accredited Collection Point in {{ $destLga->name }} *
                            </label>
                            <span class="dash-badge dash-badge-purple" style="font-size: 10px; font-weight: 700;">
                                Single-Company Transit
                            </span>
                        </div>
                        <select name="collection_point_id" id="vmCollectionPointSelect" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #818CF8; background: #FFF; font-size: 13.5px; color: #0F172A; font-weight: 600; outline: none;">
                            @foreach($collectionPoints ?? [] as $cp)
                                <option value="{{ $cp->id }}">
                                    {{ $cp->name }} &mdash; {{ $cp->address }} ({{ $cp->logisticsCompany->company_name ?? 'Logistics Partner' }})
                                </option>
                            @endforeach
                        </select>
                        <div style="font-size: 11.5px; color: #4338CA; margin-top: 8px; line-height: 1.4;">
                            🛡️ <strong>Collection Point Protocol:</strong> Your courier picks up directly from the merchant's physical shop. Line-haul transit brings the package to this designated station in {{ $destLga->name }}, where you inspect and collect using your secret 6-digit delivery PIN.
                        </div>
                    </div>

                    <!-- Pickup Shop Select Drawer -->
                    <div id="vmPickupFields" style="display: {{ $isDeliveryAvailable ? 'none' : 'block' }}; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; margin-top: 10px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Select Branch Counter for Handover Inspection *</label>
                        <select name="shop_id" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #FFF; font-size: 13.5px; color: #0F172A;">
                            @foreach($allShops ?? [] as $sh)
                                <option value="{{ $sh->id }}" {{ $sh->id == $product->shop_id ? 'selected' : '' }}>{{ $sh->name }} &mdash; {{ $sh->address }} ({{ $sh->lga->name ?? 'Uyo' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- STEP 3: PAYMENT METHOD (SCREEN 12) -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 6px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">3</span>
                        <span>Select Paystack Payment Channel</span>
                    </div>
                    <p style="font-size: 12px; color: #64748B; margin: 0 0 16px 34px;">
                        All payments are processed exclusively and securely through <strong>Paystack</strong> (Nigeria’s leading PCI-DSS Level 1 certified gateway).
                    </p>

                    <!-- Payment Options Grid -->
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px;">
                        
                        <!-- Option A: Cards (Visa, Mastercard, Verve via Paystack) -->
                        <label class="vm-pay-option active" style="display: flex; align-items: center; justify-content: space-between; border: 1.5px solid #5E17EB; background: #F5F3FF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="cards" checked style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: block;">💳 Paystack Debit Card</strong>
                                    <span style="font-size: 11.5px; color: #64748B;">Mastercard, Visa, Verve (Instant verification)</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #5E17EB; background: rgba(94, 23, 235, 0.08); padding: 2px 8px; border-radius: 4px;">FASTEST</span>
                        </label>

                        <!-- Option B: Direct Bank Transfer via Paystack -->
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #CBD5E1; background: #FFFFFF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="bank_transfer" style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: block;">🏦 Paystack Bank Transfer</strong>
                                    <span style="font-size: 11.5px; color: #64748B;">Dedicated virtual account via OPay, PalmPay, Kuda, GTBank</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #059669; background: #ECFDF5; padding: 2px 8px; border-radius: 4px;">AUTO-CONFIRM</span>
                        </label>

                        <!-- Option C: USSD via Paystack -->
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #CBD5E1; background: #FFFFFF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="ussd" style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: block;">📱 Paystack USSD Banking</strong>
                                    <span style="font-size: 11.5px; color: #64748B;">*737#, *919#, *966# and all Nigerian banks</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; color: #64748B; background: #F1F5F9; padding: 2px 8px; border-radius: 4px;">NO DATA NEEDED</span>
                        </label>

                        <!-- Option D: 5% Cashback Wallet Balance -->
                        @if((float)$availableCashback > 0)
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1.5px solid #10B981; background: #ECFDF5; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="checkbox" name="redeem_cashback" value="1" style="accent-color: #10B981; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #059669; display: block;">🪙 Redeem 5% Cashback Balance</strong>
                                    <span style="font-size: 11.5px; color: #047857;">You have &#8358;{{ number_format((float)$availableCashback, 2) }} available</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #059669;">DISCOUNT</span>
                        </label>
                        @endif

                    </div>

                    <!-- Escrow Trust Guarantee Banner -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 14px; font-size: 11.5px; color: #64748B; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">🛡️</span>
                        <span><strong>Paystack Escrow Assurance:</strong> Your money is held securely until you inspect your items or confirm handover with your secret OTP.</span>
                    </div>
                </div>

                <!-- SUBMIT BUTTON (MOCKUP SCREEN 12 PARITY) -->
                <button type="submit" id="vmPayNowBtn" style="width: 100%; background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; border: none; font-weight: 900; font-size: 16px; padding: 16px 20px; border-radius: 12px; cursor: pointer; box-shadow: 0 4px 16px rgba(255, 215, 0, 0.4); display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.15s ease;">
                    <span>🔒 Pay &#8358;{{ number_format((float)$totalPayable, 2) }} via Paystack &amp; Secure Order</span>
                    <span style="font-size: 18px;">&rarr;</span>
                </button>

                <div style="text-align: center; margin-top: 12px; font-size: 11.5px; color: #64748B; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>🔒</span>
                    <span>100% Processed by Paystack • PCI-DSS Level 1 Certified • Zero Card Details Stored on Server</span>
                </div>
            </form>
        </div>

        <!-- Right Column: Order Summary Card (Mockup Screen 9 & 12 Parity) -->
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 24px; box-shadow: var(--vm-shadow-xs); position: sticky; top: 90px;">
            <div style="font-weight: 800; font-size: 16px; color: #0F172A; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <span>Order Summary</span>
                <span style="font-size: 12px; background: #F1F5F9; color: #64748B; padding: 2px 8px; border-radius: 999px;">1 Item</span>
            </div>

            <!-- Single Item Row -->
            <div style="display: flex; gap: 14px; margin-bottom: 20px; align-items: center;">
                <div style="width: 60px; height: 60px; background: #F8FAFC; border-radius: 10px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                </div>
                <div style="flex: 1; min-width: 0;">
                    <strong style="font-size: 14px; color: #0F172A; display: block; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $product->name }}
                    </strong>
                    <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                        <span>🏬 {{ $product->shop->name ?? 'Verified Merchant' }}</span> &bull; <span>Qty: {{ $quantity }}</span>
                    </div>
                    <strong style="font-size: 13.5px; color: #5E17EB; display: block; margin-top: 4px;">
                        &#8358;{{ number_format((float)$product->unit_price, 2) }}
                    </strong>
                </div>
            </div>

            <!-- Price Breakdown -->
            <div style="border-top: 1px solid #F1F5F9; padding-top: 16px; font-size: 13.5px; display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; color: #64748B;">
                    <span>Merchandise Subtotal:</span>
                    <strong style="color: #0F172A;" id="vmSummarySubtotal">&#8358;{{ number_format((float)$merchandiseSubtotal, 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; color: #64748B;">
                    <span>Delivery Fee:</span>
                    <strong style="color: #0F172A;" id="vmSummaryDeliveryFee">&#8358;{{ number_format((float)$deliveryFee, 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; color: #059669; font-weight: 700; background: #ECFDF5; padding: 6px 10px; border-radius: 6px;">
                    <span>🪙 5% Cashback Reward:</span>
                    <span>+&#8358;{{ number_format((float)$merchandiseSubtotal * 0.05, 2) }}</span>
                </div>
                <div style="border-top: 2px dashed #E2E8F0; padding-top: 14px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 18px; font-weight: 900; color: #5E17EB;">
                    <span>Total Amount:</span>
                    <span id="vmSummaryGrandTotal">&#8358;{{ number_format((float)$totalPayable, 2) }}</span>
                </div>
            </div>

            <!-- Trust Badge -->
            <div style="margin-top: 22px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; font-size: 12px; color: #475569; line-height: 1.5;">
                <div style="font-weight: 700; color: #0F172A; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <span>⚡</span> <span>Handshake OTP Security</span>
                </div>
                Your delivery rider will ask for your secret 6-digit OTP code before handing over the package. Never share it until you have inspected the items.
            </div>
        </div>
    </div>
</div>

<script>
const activeLanesMap = @json($activeLanesMap ?? []);
const shopOriginLgaId = {{ $product->shop->lga_id ?? 69 }};
const subtotal = {{ (float)$merchandiseSubtotal }};
let currentDeliveryFee = {{ (float)$deliveryFee }};

function vmSelectFulfillment(type) {
    const cardPickup = document.getElementById('vmCardPickup');
    const cardDelivery = document.getElementById('vmCardDelivery');
    const radioPickup = document.getElementById('vmRadioPickup');
    const radioDelivery = document.getElementById('vmRadioDelivery');
    const pickupFields = document.getElementById('vmPickupFields');
    const deliveryFeeEl = document.getElementById('vmSummaryDeliveryFee');
    const grandTotalEl = document.getElementById('vmSummaryGrandTotal');
    const payBtn = document.getElementById('vmPayNowBtn');

    if (type === 'pickup') {
        if (radioPickup) radioPickup.checked = true;
        if (cardPickup) {
            cardPickup.style.borderColor = '#5E17EB';
            cardPickup.style.background = '#F5F3FF';
        }
        if (cardDelivery) {
            cardDelivery.style.borderColor = '#CBD5E1';
            cardDelivery.style.background = radioDelivery && radioDelivery.disabled ? '#F8FAFC' : '#FFFFFF';
        }
        if (pickupFields) pickupFields.style.display = 'block';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦0.00 (Free Pickup)';
        const newTotal = subtotal;
        if (grandTotalEl) grandTotalEl.textContent = '₦' + newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        if (payBtn) payBtn.innerHTML = `<span>🔒 Pay ₦${newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })} via Paystack &amp; Secure Order</span> <span style="font-size: 18px;">&rarr;</span>`;
    } else {
        if (radioDelivery && radioDelivery.disabled) return;
        if (radioDelivery) radioDelivery.checked = true;
        if (cardDelivery) {
            cardDelivery.style.borderColor = '#5E17EB';
            cardDelivery.style.background = '#F5F3FF';
        }
        if (cardPickup) {
            cardPickup.style.borderColor = '#CBD5E1';
            cardPickup.style.background = '#FFFFFF';
        }
        if (pickupFields) pickupFields.style.display = 'none';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦' + currentDeliveryFee.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        const newTotal = subtotal + currentDeliveryFee;
        if (grandTotalEl) grandTotalEl.textContent = '₦' + newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        if (payBtn) payBtn.innerHTML = `<span>🔒 Pay ₦${newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })} via Paystack &amp; Secure Order</span> <span style="font-size: 18px;">&rarr;</span>`;
    }
}

function vmHandleDestLgaChange(destLgaId) {
    destLgaId = parseInt(destLgaId);
    const selectEl = document.getElementById('vmDestLgaSelect');
    const selectedOptionText = selectEl ? selectEl.options[selectEl.selectedIndex].text.split('—')[0].trim() : '';
    const nameSpan = document.getElementById('vmDestLgaName');
    if (nameSpan) nameSpan.textContent = selectedOptionText;

    const cardDelivery = document.getElementById('vmCardDelivery');
    const radioDelivery = document.getElementById('vmRadioDelivery');
    const feeBadge = document.getElementById('vmDeliveryFeeBadge');
    const deliveryTitle = document.getElementById('vmDeliveryTitle');
    const deliveryDesc = document.getElementById('vmDeliveryDesc');
    const suspendedAlert = document.getElementById('vmSuspendedAlert');
    const statusBadge = document.getElementById('vmCorridorStatusBadge');

    let isServiced = true;
    let fee = 1000.0;
    let eta = 'Same-day 2-4 hrs';

    if (activeLanesMap[destLgaId] !== undefined) {
        if (!activeLanesMap[destLgaId].is_active) {
            isServiced = false;
        } else {
            isServiced = true;
            fee = activeLanesMap[destLgaId].base_fee;
            eta = activeLanesMap[destLgaId].eta_text || 'Standard dispatch';
        }
    } else {
        // Fallback zonal distance
        if (destLgaId === shopOriginLgaId) {
            fee = 500.0;
            eta = '2-4 hours';
        } else {
            fee = 1000.0;
            eta = '24-48 hours';
        }
    }

    if (!isServiced) {
        // Corridor is turned off / suspended
        if (suspendedAlert) suspendedAlert.style.display = 'flex';
        if (statusBadge) statusBadge.innerHTML = `<span style="background: #FEF3C7; color: #D97706; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">⚠️ Route Suspended (${selectedOptionText})</span>`;
        if (radioDelivery) {
            radioDelivery.disabled = true;
            radioDelivery.checked = false;
        }
        if (cardDelivery) {
            cardDelivery.style.opacity = '0.6';
            cardDelivery.style.cursor = 'not-allowed';
            cardDelivery.style.border = '1.5px dashed #CBD5E1';
            cardDelivery.style.background = '#F8FAFC';
        }
        if (feeBadge) {
            feeBadge.textContent = 'UNAVAILABLE';
            feeBadge.style.background = '#F1F5F9';
            feeBadge.style.color = '#94A3B8';
        }
        if (deliveryTitle) deliveryTitle.style.color = '#64748B';
        if (deliveryDesc) deliveryDesc.textContent = 'Doorstep route currently suspended. In-Store Pickup active.';

        // Seamless auto-switch to guaranteed free in-shop pickup
        currentDeliveryFee = 0.0;
        vmSelectFulfillment('pickup');
    } else {
        // Corridor is active & serviced
        if (suspendedAlert) suspendedAlert.style.display = 'none';
        if (statusBadge) statusBadge.innerHTML = `<span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">🟢 Serviced Corridor ({{ $product->shop->lga->name ?? 'Origin' }} &rarr; ${selectedOptionText})</span>`;
        if (radioDelivery) {
            radioDelivery.disabled = false;
        }
        if (cardDelivery) {
            cardDelivery.style.opacity = '1';
            cardDelivery.style.cursor = 'pointer';
        }
        if (feeBadge) {
            feeBadge.textContent = '₦' + fee.toLocaleString('en-NG', { minimumFractionDigits: 0 });
            feeBadge.style.background = 'rgba(94, 23, 235, 0.1)';
            feeBadge.style.color = '#5E17EB';
        }
        if (deliveryTitle) deliveryTitle.style.color = '#5E17EB';
        if (deliveryDesc) deliveryDesc.textContent = `Delivered directly to your door (${eta}) with Handshake OTP verification`;

        currentDeliveryFee = fee;
        const radioPickup = document.getElementById('vmRadioPickup');
        if (!radioPickup || !radioPickup.checked) {
            vmSelectFulfillment('delivery');
        }
    }
}

// Payment Option Selection Styling
document.querySelectorAll('.vm-pay-option').forEach(option => {
    option.addEventListener('click', function () {
        document.querySelectorAll('.vm-pay-option').forEach(opt => {
            if (opt.querySelector('input[type="radio"]')) {
                opt.style.borderColor = '#CBD5E1';
                opt.style.background = '#FFFFFF';
            }
        });
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            this.style.borderColor = '#5E17EB';
            this.style.background = '#F5F3FF';
        }
    });
});
</script>
@endsection