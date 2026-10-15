@extends('layouts.app')

@section('title', 'Secure Checkout — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 30px 16px 60px;">
    <h1 style="font-size: 22px; font-weight: 800; color: #0F172A; margin: 0 0 20px; display: flex; align-items: center; gap: 10px;">
        <span>🔒</span> Secure Escrow-Protected Checkout
    </h1>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: start;">
        
        <!-- Left: Checkout Inputs Form -->
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 26px; box-shadow: var(--vm-shadow-xs);">
            <form method="POST" action="{{ route('checkout.process') }}" id="vmCheckoutForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="{{ $quantity }}">

                <!-- Step 1: Customer Contact -->
                <div style="margin-bottom: 24px;">
                    <div style="font-weight: 700; font-size: 14px; color: #0F172A; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: var(--vm-primary); color: #FFF; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">1</span>
                        <span>Contact Information</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Full Name *</label>
                            <input type="text" name="customer_name" value="{{ Auth::user()->name ?? 'Shopper' }}" required style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--vm-border);">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Phone Number (For Handover OTP) *</label>
                            <input type="text" name="customer_phone" value="{{ Auth::user()->phone ?? '08012345678' }}" required style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--vm-border);">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Fulfillment Method -->
                <div style="margin-bottom: 24px;">
                    <div style="font-weight: 700; font-size: 14px; color: #0F172A; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: var(--vm-primary); color: #FFF; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">2</span>
                        <span>Choose Fulfillment Method</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <label style="border: 2px solid var(--vm-primary); background: #F5F3FF; border-radius: 10px; padding: 14px; cursor: pointer; display: block;">
                            <input type="radio" name="fulfillment_type" value="doorstep_delivery" checked onchange="toggleFulfillment('delivery')">
                            <strong style="display: block; margin-top: 4px; font-size: 13.5px; color: var(--vm-primary);">🚚 Doorstep Delivery</strong>
                            <span style="font-size: 11.5px; color: #64748B;">Delivered by accredited rider</span>
                        </label>
                        <label style="border: 1px solid var(--vm-border); background: #FFF; border-radius: 10px; padding: 14px; cursor: pointer; display: block;">
                            <input type="radio" name="fulfillment_type" value="in_shop_pickup" onchange="toggleFulfillment('pickup')">
                            <strong style="display: block; margin-top: 4px; font-size: 13.5px; color: #0F172A;">🏢 In-Shop Pickup</strong>
                            <span style="font-size: 11.5px; color: #64748B;">Collect at physical counter (₦0 Fee)</span>
                        </label>
                    </div>

                    <!-- Doorstep Address Fields -->
                    <div id="vmDeliveryFields">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Destination LGA *</label>
                                <select name="destination_lga_id" style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--vm-border); background: #FFF;">
                                    @foreach($lgas ?? [] as $lga)
                                        <option value="{{ $lga->id }}" {{ $lga->name == $activeCity ? 'selected' : '' }}>{{ $lga->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Street & House Number *</label>
                                <input type="text" name="delivery_address" placeholder="e.g. 15 Wellington Bassey Way, Uyo" style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--vm-border);">
                            </div>
                        </div>
                    </div>

                    <!-- Pickup Shop Select -->
                    <div id="vmPickupFields" style="display: none;">
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Select Inspection &amp; Pickup Counter *</label>
                        <select name="shop_id" style="width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid var(--vm-border); background: #FFF;">
                            @foreach($allShops ?? [] as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->address }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #5E17EB, #7C3AED); color: #FFF; border: none; font-weight: 800; font-size: 15px; padding: 14px; border-radius: 10px; cursor: pointer; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35);">
                    🔒 Pay &#8358;{{ number_format((float)$totalPayable, 2) }} &amp; Secure Order in Escrow
                </button>
            </form>
        </div>

        <!-- Right: Order Summary Card -->
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 24px; box-shadow: var(--vm-shadow-xs);">
            <div style="font-weight: 800; font-size: 15px; color: #0F172A; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9; padding-bottom: 10px;">
                Order Summary (1 Item)
            </div>

            <div style="display: flex; gap: 12px; margin-bottom: 18px; align-items: center;">
                <div style="width: 55px; height: 55px; background: #F8FAFC; border-radius: 8px; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                </div>
                <div style="flex: 1;">
                    <strong style="font-size: 13.5px; color: #0F172A; display: block; line-height: 1.3;">{{ $product->name }}</strong>
                    <span style="font-size: 12px; color: #64748B;">Qty: {{ $quantity }} &bull; &#8358;{{ number_format((float)$product->unit_price, 2) }}</span>
                </div>
            </div>

            <!-- Mathematical Breakdown -->
            <div style="border-top: 1px solid #F1F5F9; padding-top: 14px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #64748B;">
                    <span>Merchandise Subtotal:</span>
                    <strong style="color: #0F172A;">&#8358;{{ number_format((float)$merchandiseSubtotal, 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #64748B;">
                    <span>Directional Delivery Fee:</span>
                    <strong style="color: #0F172A;">&#8358;{{ number_format((float)$deliveryFee, 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: #059669; font-weight: 600;">
                    <span>5% Cashback Reward (24h maturation):</span>
                    <span>+&#8358;{{ number_format((float)$merchandiseSubtotal * 0.05, 2) }}</span>
                </div>
                <div style="border-top: 2px dashed #E2E8F0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 16px; font-weight: 900; color: var(--vm-primary);">
                    <span>Total Payable:</span>
                    <span>&#8358;{{ number_format((float)$totalPayable, 2) }}</span>
                </div>
            </div>

            <!-- Security Assurance Note -->
            <div style="margin-top: 20px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; font-size: 11.5px; color: #64748B; line-height: 1.5;">
                🛡️ <strong>Zero-Risk Escrow:</strong> Your payment is held safely by Victorious Market until you physically receive and verify your item using your secret 6-digit delivery OTP.
            </div>
        </div>
    </div>
</div>

<script>
function toggleFulfillment(type) {
    if (type === 'delivery') {
        document.getElementById('vmDeliveryFields').style.display = 'block';
        document.getElementById('vmPickupFields').style.display = 'none';
    } else {
        document.getElementById('vmDeliveryFields').style.display = 'none';
        document.getElementById('vmPickupFields').style.display = 'block';
    }
}
</script>
@endsection