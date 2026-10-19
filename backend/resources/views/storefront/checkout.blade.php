@extends('layouts.app')

@section('title', 'Secure Checkout — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 24px 16px 80px; max-width: 1040px;">

    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span style="opacity: 0.5;">&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">Secure Checkout</span>
    </div>

    <!-- Main Title & Trust Assurance -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0; display: flex; align-items: center; gap: 10px; letter-spacing: -0.3px;">
            <span style="color: var(--vm-primary); display: inline-flex; align-items: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span> 
            <span>Secure Escrow Checkout</span>
        </h1>
        <div style="display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; border: 1px solid rgba(5, 150, 105, 0.25); padding: 5px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #059669;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Zero-Risk Escrow Protection</span>
        </div>
    </div>

    @php
        $availableQty = $product->available_quantity;
        $isOutOfStock = (!$product->is_in_stock || !$product->is_available_for_sale);
    @endphp

    @if($isOutOfStock)
        <!-- Out of Stock Guard Banner -->
        <div style="background: #FEF2F2; border: 1.5px solid #F87171; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 14px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);">
            <div style="color: #DC2626; flex-shrink: 0; margin-top: 2px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <strong style="font-size: 15px; color: #991B1B; display: block; margin-bottom: 4px;">Item Currently Out of Stock</strong>
                <p style="font-size: 13px; color: #B91C1C; margin: 0; line-height: 1.5;">
                    All units of <strong>{{ $product->name }}</strong> have been allocated to confirmed orders, marked out of stock by the vendor, or temporarily reserved in active 30-minute checkout holds. Purchasing is temporarily disabled until stock is replenished.
                </p>
            </div>
        </div>
    @else
        <!-- 30-Minute Stock Hold & Escrow Guarantee Banner -->
        <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="color: #16A34A; flex-shrink: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div style="font-size: 12.5px; color: #166534; line-height: 1.4;">
                    <strong>30-Minute Guaranteed Inventory Hold:</strong> Submitting your order reserves your units for 30 minutes while you complete Paystack payment.
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: #DCFCE7; color: #15803D; font-size: 11.5px; font-weight: 800; padding: 3px 10px; border-radius: 999px;">
                    In Stock &bull; Available to Order
                </span>
            </div>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; align-items: start;">
        
        <!-- Left Column: 3-Step Checkout Sequence -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <form method="POST" action="{{ route('checkout.process') }}" id="vmCheckoutForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="{{ $quantity }}">

                <!-- ========================================== -->
                <!-- STEP 1: CONTACT & DELIVERY ADDRESS -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 20px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">1</span>
                        <span>Contact &amp; Delivery Address (Akwa Ibom)</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Full Name *</label>
                            <input type="text" name="customer_name" value="{{ Auth::user()->name ?? '' }}" required placeholder="e.g. John Doe" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Phone Number (For OTP) *</label>
                            <input type="tel" name="customer_phone" value="{{ Auth::user()->phone ?? '' }}" required placeholder="e.g. 08012345678" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Destination LGA & Street Address -->
                    <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Select LGA *</label>
                            <select name="destination_lga_id" id="vmDestLgaSelect" onchange="vmHandleDestLgaChange(this.value)" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box; cursor: pointer;">
                                @foreach($lgas ?? [] as $lga)
                                    <option value="{{ $lga->id }}" {{ ($lga->id == $destLga->id) ? 'selected' : '' }}>
                                        {{ $lga->name }} &mdash; {{ $lga->state->name ?? 'Akwa Ibom' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">Street &amp; House Number *</label>
                            <input type="text" name="delivery_address" placeholder="e.g. 12, Uyo Road, Uyo" value="12, Uyo Road, Uyo" required style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Landmark Assistance Field -->
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #64748B; margin-bottom: 5px;">
                            Nearest Landmark / Bus Stop <span style="color: #94A3B8;">(Helps dispatch couriers locate you swiftly)</span>
                        </label>
                        <input type="text" name="landmark" placeholder="e.g. Opposite Ibom Plaza / Near Champion Breweries" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- STEP 2: FULFILLMENT METHOD -->
                <!-- ========================================== -->
                @php
                    $isDeliveryAvailable = $deliveryCheck['is_available'] ?? true;
                    $isCrossLga = ($product->shop->lga_id !== $destLga->id);
                @endphp

                @if($errors->has('fulfillment_type'))
                    <div style="background: #FEF2F2; border: 1.5px solid #F87171; color: #991B1B; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
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
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #059669;"></span>
                                    <span>Serviced Corridor ({{ $product->shop->lga->name ?? 'Origin' }} &rarr; <span id="vmDestLgaName">{{ $destLga->name }}</span>)</span>
                                </span>
                            @else
                                <span style="background: #FEF3C7; color: #D97706; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <span>Route Suspended (<span id="vmDestLgaName">{{ $destLga->name }}</span>)</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Dynamic Warning Banner for Suspended Corridors -->
                    <div id="vmSuspendedAlert" style="display: {{ $isDeliveryAvailable ? 'none' : 'flex' }}; align-items: flex-start; gap: 10px; background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 12.5px; color: #92400E; line-height: 1.45;">
                        <div style="color: #D97706; flex-shrink: 0; margin-top: 1px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
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
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">FREE (&#8358;0.00)</span>
                            </div>
                            <strong style="display: flex; align-items: center; gap: 6px; font-size: 14px; color: #0F172A; margin-bottom: 3px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <span>In-Shop Counter Pickup</span>
                            </strong>
                            <span style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">Collect &amp; inspect physically at {{ $product->shop->name }} counter. Pay &#8358;0 online upfront; hand over OTP only upon approval.</span>
                        </label>

                        <!-- Doorstep Delivery Card -->
                        <label id="vmCardDelivery" style="border: {{ $isDeliveryAvailable ? '2px solid #5E17EB' : '1.5px dashed #CBD5E1' }}; background: {{ $isDeliveryAvailable ? '#F5F3FF' : '#F8FAFC' }}; opacity: {{ $isDeliveryAvailable ? '1' : '0.6' }}; cursor: {{ $isDeliveryAvailable ? 'pointer' : 'not-allowed' }}; border-radius: 12px; padding: 16px; display: block; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <input type="radio" name="fulfillment_type" id="vmRadioDelivery" value="doorstep_delivery" {{ $isDeliveryAvailable ? 'checked' : 'disabled' }} onchange="vmSelectFulfillment('delivery')" style="accent-color: #5E17EB; width: 18px; height: 18px;">
                                <span id="vmDeliveryFeeBadge" style="background: {{ $isDeliveryAvailable ? 'rgba(94, 23, 235, 0.1)' : '#F1F5F9' }}; color: {{ $isDeliveryAvailable ? '#5E17EB' : '#94A3B8' }}; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">
                                    {{ $isDeliveryAvailable ? '&#8358;' . number_format((float)$deliveryFee, 0) : 'UNAVAILABLE' }}
                                </span>
                            </div>
                            <strong id="vmDeliveryTitle" style="display: flex; align-items: center; gap: 6px; font-size: 14px; color: {{ $isDeliveryAvailable ? '#5E17EB' : '#64748B' }}; margin-bottom: 3px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <span>Vendor Direct Doorstep Delivery</span>
                            </strong>
                            <span id="vmDeliveryDesc" style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">
                                @if(!$isDeliveryAvailable)
                                    Delivery route currently suspended for this destination. In-Shop Pickup active.
                                @else
                                    Delivered directly to your door with courier Handshake OTP security.
                                @endif
                            </span>
                        </label>
                    </div>

                    <!-- Optional Accredited Delivery Station / Collection Hub (Inter-LGA / Line-Haul) -->
                    <div id="vmCollectionPointFields" style="display: {{ ($isDeliveryAvailable && $isCrossLga && isset($collectionPoints) && count($collectionPoints) > 0) ? 'block' : 'none' }}; background: #EEF2FF; border: 1.5px solid #6366F1; border-radius: 12px; padding: 16px; margin-top: 10px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 800; color: #1E1B4B;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span>Accredited Delivery Hub in {{ $destLga->name }} (Optional)</span>
                            </label>
                            <span style="font-size: 10px; font-weight: 700; background: #E0E7FF; color: #4338CA; padding: 2px 8px; border-radius: 6px;">
                                Station Hub Available
                            </span>
                        </div>
                        <select name="collection_point_id" id="vmCollectionPointSelect" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #818CF8; background: #FFF; font-size: 13.5px; color: #0F172A; font-weight: 600; outline: none;">
                            <option value="">Direct Doorstep Handover to Street Address (Default)</option>
                            @foreach($collectionPoints ?? [] as $cp)
                                <option value="{{ $cp->id }}">
                                    {{ $cp->name }} &mdash; {{ $cp->address }} (Station)
                                </option>
                            @endforeach
                        </select>
                        <div style="font-size: 11.5px; color: #4338CA; margin-top: 8px; line-height: 1.4; display: flex; align-items: flex-start; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span><strong>Delivery Hub Option:</strong> Direct doorstep delivery will bring the package straight to your address. Alternatively, select a station hub above if you prefer to collect it in person with your secret 6-digit OTP.</span>
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
                <!-- STEP 3: PAYMENT METHOD -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 6px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">3</span>
                        <span id="vmStep3Title">Select Paystack Payment Channel</span>
                    </div>
                    <p id="vmStep3Subtitle" style="font-size: 12px; color: #64748B; margin: 0 0 16px 34px;">
                        All delivery payments are processed exclusively and securely through <strong>Paystack</strong> (Nigeria’s leading PCI-DSS Level 1 certified gateway).
                    </p>

                    <!-- In-Shop Pickup Inspection Notice (Shown only when In-Shop Pickup is selected) -->
                    <div id="vmPickupPaymentNotice" style="display: none; background: #EEF2FF; border: 1.5px solid #C7D2FE; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <div style="color: #4F46E5; flex-shrink: 0; margin-top: 2px;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <strong style="font-size: 14px; color: #1E1B4B; display: block; margin-bottom: 4px;">&#8358;0.00 Online Payment Upfront &mdash; Inspect First at Merchant Counter</strong>
                                <p style="font-size: 12px; color: #3730A3; margin: 0; line-height: 1.5;">
                                    When picking up in person, you do not pay online upfront. Visit <strong>{{ $product->shop->name ?? 'the shop' }}</strong>, physically inspect and verify your items at the counter, and pay directly after you are 100% satisfied.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Options Grid (for Doorstep Delivery) -->
                    <div id="vmPaymentGatewayOptions" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px;">
                        
                        <!-- Option A: Cards (Visa, Mastercard, Verve via Paystack) -->
                        <label class="vm-pay-option active" style="display: flex; align-items: center; justify-content: space-between; border: 1.5px solid #5E17EB; background: #F5F3FF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="cards" checked style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        <span>Paystack Debit Card</span>
                                    </strong>
                                    <span style="font-size: 11.5px; color: #64748B;">Mastercard, Visa, Verve (Instant online settlement)</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #5E17EB; background: rgba(94, 23, 235, 0.08); padding: 3px 8px; border-radius: 4px;">FASTEST</span>
                        </label>

                        <!-- Option B: Direct Bank Transfer via Paystack -->
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #CBD5E1; background: #FFFFFF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="bank_transfer" style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 10v11"/><path d="M19 10v11"/><path d="M9 10v11"/><path d="M15 10v11"/><path d="M12 2L2 7h20L12 2z"/></svg>
                                        <span>Paystack Bank Transfer</span>
                                    </strong>
                                    <span style="font-size: 11.5px; color: #64748B;">Dedicated virtual account via OPay, PalmPay, Kuda, GTBank</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #059669; background: #ECFDF5; padding: 3px 8px; border-radius: 4px;">AUTO-CONFIRM</span>
                        </label>

                        <!-- Option C: USSD via Paystack -->
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #CBD5E1; background: #FFFFFF; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="radio" name="payment_method_choice" value="ussd" style="accent-color: #5E17EB; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Paystack USSD Banking</span>
                                    </strong>
                                    <span style="font-size: 11.5px; color: #64748B;">*737#, *919#, *966# and all Nigerian commercial banks</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; color: #64748B; background: #F1F5F9; padding: 3px 8px; border-radius: 4px;">NO DATA NEEDED</span>
                        </label>

                        <!-- Option D: 5% Cashback Wallet Balance -->
                        @if((float)$availableCashback > 0)
                        <label class="vm-pay-option" style="display: flex; align-items: center; justify-content: space-between; border: 1.5px solid #10B981; background: #ECFDF5; border-radius: 10px; padding: 12px 16px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="checkbox" name="redeem_cashback" value="1" style="accent-color: #10B981; width: 16px; height: 16px;">
                                <div>
                                    <strong style="font-size: 13.5px; color: #059669; display: flex; align-items: center; gap: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                                        <span>Redeem 5% Cashback Balance</span>
                                    </strong>
                                    <span style="font-size: 11.5px; color: #047857;">You have &#8358;{{ number_format((float)$availableCashback, 2) }} available</span>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #059669;">DISCOUNT</span>
                        </label>
                        @endif

                    </div>

                    <!-- Escrow Trust Guarantee Banner -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px 14px; font-size: 11.5px; color: #64748B; display: flex; align-items: center; gap: 10px;">
                        <div style="color: var(--vm-primary); flex-shrink: 0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <span><strong>Paystack Escrow Assurance:</strong> Your money is held securely until you inspect your items or confirm handover with your secret OTP.</span>
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
                <button type="submit" id="vmPayNowBtn" {{ $isOutOfStock ? 'disabled' : '' }} style="width: 100%; background: {{ $isOutOfStock ? '#E2E8F0' : 'linear-gradient(135deg, #FFD700 0%, #F59E0B 100%)' }}; color: {{ $isOutOfStock ? '#94A3B8' : '#0F172A' }}; border: none; font-weight: 900; font-size: 16px; padding: 16px 20px; border-radius: 12px; cursor: {{ $isOutOfStock ? 'not-allowed' : 'pointer' }}; box-shadow: {{ $isOutOfStock ? 'none' : '0 4px 16px rgba(255, 215, 0, 0.4)' }}; display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.15s ease;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span>
                        @if($isOutOfStock)
                            Out of Stock &mdash; Cannot Proceed to Payment
                        @else
                            Pay &#8358;{{ number_format((float)$totalPayable, 2) }} via Paystack &amp; Secure Order
                        @endif
                    </span>
                    @if(!$isOutOfStock)
                        <span style="font-size: 18px;">&rarr;</span>
                    @endif
                </button>

                <div style="text-align: center; margin-top: 12px; font-size: 11.5px; color: #64748B; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span>100% Processed by Paystack &bull; PCI-DSS Level 1 Certified &bull; Zero Card Details Stored on Server</span>
                </div>
            </form>
        </div>

        <!-- Right Column: Order Summary Card -->
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 24px; box-shadow: var(--vm-shadow-xs); position: sticky; top: 90px;">
            <div style="font-weight: 800; font-size: 16px; color: #0F172A; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <span>Order Summary</span>
                <span id="vmSummaryItemCount" style="font-size: 12px; background: #F1F5F9; color: #64748B; padding: 2px 8px; border-radius: 999px;">1 Item</span>
            </div>

            <!-- Items List Container (Synced with vmCart if multi-item) -->
            <div id="vmCheckoutItemsList">
                <!-- Single Item Row -->
                <div style="display: flex; gap: 14px; margin-bottom: 20px; align-items: center;">
                    <div style="width: 60px; height: 60px; background: #F8FAFC; border-radius: 10px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                        <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <strong style="font-size: 14px; color: #0F172A; display: block; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $product->name }}
                        </strong>
                        <div style="font-size: 12px; color: #64748B; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <span>{{ $product->shop->name ?? 'Verified Merchant' }}</span>
                            </span>
                            &bull; <span>Qty: {{ $quantity }}</span>
                            &bull;
                            @if($isOutOfStock)
                                <span style="color: #DC2626; font-weight: 700; background: #FEE2E2; padding: 1px 6px; border-radius: 4px; font-size: 11px;">Out of Stock</span>
                            @else
                                <span style="color: #059669; font-weight: 700; background: #DCFCE7; padding: 1px 6px; border-radius: 4px; font-size: 11px;">In Stock &bull; Available to Order</span>
                            @endif
                        </div>
                        <strong style="font-size: 13.5px; color: #5E17EB; display: block; margin-top: 4px;">
                            &#8358;{{ number_format((float)$product->unit_price, 2) }}
                        </strong>
                    </div>
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

                @php
                    $cashbackReward = (float)$merchandiseSubtotal * 0.05;
                @endphp
                <!-- 5% Loyalty Cashback Reward -->
                <div style="display: flex; justify-content: space-between; color: #047857; font-weight: 700; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border: 1px solid #A7F3D0; padding: 10px 12px; border-radius: 8px; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                        <span>5% Customer Cashback:</span>
                    </span>
                    <span style="font-size: 14px; font-weight: 800;">+&#8358;{{ number_format($cashbackReward, 2) }}</span>
                </div>
                <div style="font-size: 11px; color: #059669; margin-top: -4px; padding-left: 2px;">
                    &#10003; 5% calculated on eligible merchandise (excl. delivery) &bull; Rewarded to customer wallet on delivery OTP confirmation
                </div>

                <div style="border-top: 2px dashed #E2E8F0; padding-top: 14px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 18px; font-weight: 900; color: #5E17EB;">
                    <span>Total Amount:</span>
                    <span id="vmSummaryGrandTotal">&#8358;{{ number_format((float)$totalPayable, 2) }}</span>
                </div>
            </div>

            <!-- Trust Badge -->
            <div style="margin-top: 22px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; font-size: 12px; color: #475569; line-height: 1.5;">
                <div style="font-weight: 700; color: #0F172A; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--vm-primary)" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Handshake OTP Security</span>
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
const isItemOutOfStock = {{ $isOutOfStock ? 'true' : 'false' }};

function vmSelectFulfillment(type) {
    const cardPickup = document.getElementById('vmCardPickup');
    const cardDelivery = document.getElementById('vmCardDelivery');
    const radioPickup = document.getElementById('vmRadioPickup');
    const radioDelivery = document.getElementById('vmRadioDelivery');
    const pickupFields = document.getElementById('vmPickupFields');
    const cpFields = document.getElementById('vmCollectionPointFields');
    const deliveryFeeEl = document.getElementById('vmSummaryDeliveryFee');
    const grandTotalEl = document.getElementById('vmSummaryGrandTotal');
    const payBtn = document.getElementById('vmPayNowBtn');
    const gatewayOptions = document.getElementById('vmPaymentGatewayOptions');
    const pickupNotice = document.getElementById('vmPickupPaymentNotice');
    const step3Title = document.getElementById('vmStep3Title');
    const step3Subtitle = document.getElementById('vmStep3Subtitle');

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
        if (cpFields) cpFields.style.display = 'none';
        if (gatewayOptions) gatewayOptions.style.display = 'none';
        if (pickupNotice) pickupNotice.style.display = 'block';
        if (step3Title) step3Title.textContent = 'In-Store Inspection & Counter Payment';
        if (step3Subtitle) step3Subtitle.textContent = 'No online payment required today. Inspect your items and pay at the shop counter.';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦0.00 (Free Pickup)';
        if (grandTotalEl) grandTotalEl.textContent = '₦0.00 (Pay at Shop Counter)';
        if (payBtn) {
            if (isItemOutOfStock) {
                payBtn.disabled = true;
                payBtn.style.cursor = 'not-allowed';
                payBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <span>Out of Stock &mdash; Cannot Proceed</span>`;
            } else {
                payBtn.disabled = false;
                payBtn.style.cursor = 'pointer';
                payBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg> <span>Reserve for Free &mdash; Inspect &amp; Pay at Shop Counter</span> <span style="font-size: 18px;">&rarr;</span>`;
            }
        }
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
        if (cpFields) {
            const destLgaEl = document.getElementById('vmDestLgaSelect');
            const destLgaVal = destLgaEl ? parseInt(destLgaEl.value) : 0;
            cpFields.style.display = (destLgaVal !== shopOriginLgaId) ? 'block' : 'none';
        }
        if (gatewayOptions) gatewayOptions.style.display = 'flex';
        if (pickupNotice) pickupNotice.style.display = 'none';
        if (step3Title) step3Title.textContent = 'Select Paystack Payment Channel';
        if (step3Subtitle) step3Subtitle.textContent = 'All delivery payments are processed exclusively and securely through Paystack.';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦' + currentDeliveryFee.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        const newTotal = subtotal + currentDeliveryFee;
        if (grandTotalEl) grandTotalEl.textContent = '₦' + newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        if (payBtn) {
            if (isItemOutOfStock) {
                payBtn.disabled = true;
                payBtn.style.cursor = 'not-allowed';
                payBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <span>Out of Stock &mdash; Cannot Proceed</span>`;
            } else {
                payBtn.disabled = false;
                payBtn.style.cursor = 'pointer';
                payBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> <span>Pay ₦${newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })} via Paystack &amp; Secure Order</span> <span style="font-size: 18px;">&rarr;</span>`;
            }
        }
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
    const cpFields = document.getElementById('vmCollectionPointFields');

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
        if (statusBadge) statusBadge.innerHTML = `<span style="background: #FEF3C7; color: #D97706; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Route Suspended (${selectedOptionText})</span>`;
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
        if (cpFields) cpFields.style.display = 'none';

        // Seamless auto-switch to guaranteed free in-shop pickup
        currentDeliveryFee = 0.0;
        vmSelectFulfillment('pickup');
    } else {
        // Corridor is active & serviced
        if (suspendedAlert) suspendedAlert.style.display = 'none';
        if (statusBadge) statusBadge.innerHTML = `<span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #059669;"></span> Serviced Corridor ({{ $product->shop->lga->name ?? 'Origin' }} &rarr; ${selectedOptionText})</span>`;
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
        } else {
            if (cpFields) cpFields.style.display = 'none';
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

// Multi-Item Cart to Checkout Synchronization
try {
    const rawCart = localStorage.getItem('vm_cart_items_v1');
    const cartItems = rawCart ? JSON.parse(rawCart) : [];
    if (Array.isArray(cartItems) && cartItems.length > 1) {
        const itemsContainer = document.getElementById('vmCheckoutItemsList');
        const countBadge = document.getElementById('vmSummaryItemCount');
        if (itemsContainer) {
            let html = '<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; max-height: 280px; overflow-y: auto; padding-right: 4px;">';
            cartItems.forEach(item => {
                const lineTotal = item.unit_price * (item.quantity || 1);
                html += `
                    <div style="display: flex; gap: 12px; align-items: center; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px;">
                        <div style="width: 48px; height: 48px; background: #FFFFFF; border-radius: 8px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                            <img src="${item.image}" alt="${item.name}" style="max-width: 85%; max-height: 85%; object-fit: contain;">
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <strong style="font-size: 13px; color: #0F172A; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${item.name}</strong>
                            <div style="font-size: 11px; color: #64748B; display: flex; align-items: center; gap: 4px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                <span>${item.shop_name}</span> &bull; <span>Qty: ${item.quantity}</span>
                            </div>
                            <strong style="font-size: 12.5px; color: #5E17EB;">₦${lineTotal.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            itemsContainer.innerHTML = html;
        }
        if (countBadge) countBadge.textContent = cartItems.length + ' Items';
    }
} catch (e) {
    console.error('Error synchronizing multi-item cart to checkout', e);
}

// Clear cart on successful checkout form submission
const checkoutForm = document.getElementById('vmCheckoutForm');
if (checkoutForm) {
    checkoutForm.addEventListener('submit', function() {
        if (window.vmCart) window.vmCart.clear();
    });
}
</script>
@endsection