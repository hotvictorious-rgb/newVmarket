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
                            <select name="destination_lga_id" id="vmDestLgaSelect" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box; cursor: pointer;">
                                @foreach($lgas ?? [] as $lga)
                                    <option value="{{ $lga->id }}" {{ ($lga->name == $activeCity || $lga->id == $destLga->id) ? 'selected' : '' }}>
                                        {{ $lga->name }} — Akwa Ibom
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
                <!-- STEP 2: FULFILLMENT METHOD (SCREEN 10) -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 20px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">2</span>
                        <span>Delivery or In-Store Pickup Choice</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <!-- In-Store Pickup Card -->
                        <label id="vmCardPickup" style="border: 1.5px solid #CBD5E1; background: #FFFFFF; border-radius: 12px; padding: 16px; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <input type="radio" name="fulfillment_type" value="in_shop_pickup" onchange="vmSelectFulfillment('pickup')" style="accent-color: #5E17EB; width: 18px; height: 18px;">
                                <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">FREE</span>
                            </div>
                            <strong style="display: block; font-size: 14px; color: #0F172A; margin-bottom: 3px;">🏢 In-Store Pickup</strong>
                            <span style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">Collect &amp; inspect physically from verified shop counter</span>
                        </label>

                        <!-- Doorstep Delivery Card -->
                        <label id="vmCardDelivery" style="border: 2px solid #5E17EB; background: #F5F3FF; border-radius: 12px; padding: 16px; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <input type="radio" name="fulfillment_type" value="doorstep_delivery" checked onchange="vmSelectFulfillment('delivery')" style="accent-color: #5E17EB; width: 18px; height: 18px;">
                                <span style="background: rgba(94, 23, 235, 0.1); color: #5E17EB; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">&#8358;{{ number_format((float)$deliveryFee, 0) }}</span>
                            </div>
                            <strong style="display: block; font-size: 14px; color: #5E17EB; margin-bottom: 3px;">🚚 Doorstep Delivery</strong>
                            <span style="font-size: 12px; color: #64748B; line-height: 1.4; display: block;">Delivered directly to your door with Handshake OTP verification</span>
                        </label>
                    </div>

                    <!-- Pickup Shop Select Drawer -->
                    <div id="vmPickupFields" style="display: none; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; margin-top: 10px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">Select Branch Counter for Handover Inspection *</label>
                        <select name="shop_id" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #FFF; font-size: 13.5px; color: #0F172A;">
                            @foreach($allShops ?? [] as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }} — {{ $sh->address }} ({{ $sh->lga->name ?? 'Uyo' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- STEP 3: PAYMENT METHOD (SCREEN 12) -->
                <!-- ========================================== -->
                <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 22px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
                    <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5E17EB; color: #FFF; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">3</span>
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
                    <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
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
function vmSelectFulfillment(type) {
    const cardPickup = document.getElementById('vmCardPickup');
    const cardDelivery = document.getElementById('vmCardDelivery');
    const pickupFields = document.getElementById('vmPickupFields');
    const deliveryFeeEl = document.getElementById('vmSummaryDeliveryFee');
    const grandTotalEl = document.getElementById('vmSummaryGrandTotal');
    const payBtn = document.getElementById('vmPayNowBtn');

    const subtotal = {{ (float)$merchandiseSubtotal }};
    const baseDeliveryFee = {{ (float)$deliveryFee }};

    if (type === 'pickup') {
        cardPickup.style.borderColor = '#5E17EB';
        cardPickup.style.background = '#F5F3FF';
        cardDelivery.style.borderColor = '#CBD5E1';
        cardDelivery.style.background = '#FFFFFF';
        pickupFields.style.display = 'block';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦0.00 (Free Pickup)';
        const newTotal = subtotal;
        if (grandTotalEl) grandTotalEl.textContent = '₦' + newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        if (payBtn) payBtn.innerHTML = `<span>🔒 Pay ₦${newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })} &amp; Secure Order</span> <span style="font-size: 18px;">&rarr;</span>`;
    } else {
        cardDelivery.style.borderColor = '#5E17EB';
        cardDelivery.style.background = '#F5F3FF';
        cardPickup.style.borderColor = '#CBD5E1';
        cardPickup.style.background = '#FFFFFF';
        pickupFields.style.display = 'none';

        if (deliveryFeeEl) deliveryFeeEl.textContent = '₦' + baseDeliveryFee.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        const newTotal = subtotal + baseDeliveryFee;
        if (grandTotalEl) grandTotalEl.textContent = '₦' + newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 });
        if (payBtn) payBtn.innerHTML = `<span>🔒 Pay ₦${newTotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })} &amp; Secure Order</span> <span style="font-size: 18px;">&rarr;</span>`;
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