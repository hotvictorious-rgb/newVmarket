@extends('layouts.app')

@section('title', $product->name . ' — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 30px 16px 60px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span>&rsaquo;</span>
        <span>{{ $product->category->name ?? 'Electronics' }}</span>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ Str::limit($product->name, 40) }}</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 36px; background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 32px; box-shadow: var(--vm-shadow-sm);">
        
        <!-- Left Column: Gallery & Verification Badges -->
        <div>
            <div style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-radius: 12px; border: 1.5px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 16px;">
                <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 65%; max-height: 65%; object-fit: contain;">
                
                <span style="position: absolute; top: 14px; left: 14px; background: #0F172A; color: #FFFFFF; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
                    🛡️ ESCROW SECURED
                </span>

                <span style="position: absolute; top: 14px; right: 14px; background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.2); font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
                    ● 7-DAY CERTIFIED FRESH
                </span>
            </div>

            <!-- Trust Features Strip -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; font-size: 12px;">
                    <div style="font-weight: 700; color: #0F172A; margin-bottom: 2px;">🏢 In-Shop Inspection</div>
                    <div style="color: #64748B;">Inspect before payment at {{ $product->shop->name }}</div>
                </div>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; font-size: 12px;">
                    <div style="font-weight: 700; color: #0F172A; margin-bottom: 2px;">⚡ 24h Return Buffer</div>
                    <div style="color: #64748B;">Money held in escrow until OTP verified</div>
                </div>
            </div>
        </div>

        <!-- Right Column: Pricing, Delivery Options, Action Buttons -->
        <div>
            <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                <span style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px;">
                    {{ $product->category->name ?? 'Electronics' }}
                </span>
                <span style="background: #F1F5F9; color: #475569; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 6px;">
                    Package: {{ ucfirst($product->package_tier ?? 'standard') }}
                </span>
            </div>

            <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 14px; line-height: 1.3;">
                {{ $product->name }}
            </h1>

            <div style="font-size: 13px; color: #64748B; margin-bottom: 18px; display: flex; align-items: center; gap: 6px;">
                <span>Merchant:</span>
                <strong style="color: #0F172A;">{{ $product->vendor->business_name ?? 'Victor Tech Innovations' }}</strong>
                <span>&bull;</span>
                <span>Branch: {{ $product->shop->name }} ({{ $product->shop->lga->name ?? 'Uyo' }})</span>
            </div>

            <!-- Price & Cashback Banner -->
            <div style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.08) 100%); border: 1.5px solid var(--vm-gold); border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 12px; color: #64748B; font-weight: 600; display: block; margin-bottom: 2px;">MARKETPLACE RETAIL PRICE</span>
                    <span style="font-size: 28px; font-weight: 900; color: var(--vm-primary);">
                        &#8358;{{ number_format((float)$product->unit_price, 2) }}
                    </span>
                </div>
                <div style="text-align: right;">
                    <span style="background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.25); font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 6px; display: inline-block; margin-bottom: 4px;">
                        🪙 +&#8358;{{ number_format((float)$cashbackEarnable, 2) }} Cashback
                    </span>
                    <div style="font-size: 11px; color: #059669; font-weight: 600;">5% credited 24h after delivery</div>
                </div>
            </div>

            <!-- Directional Corridor Card -->
            <div style="background: #F8FAFC; border: 1px solid var(--vm-border); border-radius: 10px; padding: 16px; margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Delivery to Your Location:</span>
                    <button type="button" class="vm-header-location-btn" style="background: none; border: none; color: var(--vm-primary); font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline;">
                        Change ({{ $activeCity }})
                    </button>
                </div>

                @if($deliveryCheck['is_available'])
                    <div style="display: flex; align-items: center; gap: 10px; color: #1D4ED8; font-weight: 600; font-size: 13.5px; margin-bottom: 6px;">
                        <span>🚚</span>
                        <span>Delivers to {{ $activeCity }}: &#8358;{{ number_format((float)$deliveryCheck['fee'], 2) }}</span>
                        <span style="font-size: 12px; color: #64748B; font-weight: normal;">({{ $deliveryCheck['estimated_time_text'] }})</span>
                    </div>
                @else
                    <div style="display: flex; align-items: center; gap: 10px; color: #D97706; font-weight: 600; font-size: 13.5px; margin-bottom: 6px;">
                        <span>📦</span>
                        <span>Direct lane in transit setup. Nationwide dispatch available.</span>
                    </div>
                @endif

                @if($pickupCheck['is_available'])
                    <div style="display: flex; align-items: center; gap: 10px; color: #059669; font-weight: 600; font-size: 13px;">
                        <span>⚡</span>
                        <span>In-Shop Inspection &amp; Pickup enabled at {{ $product->shop->name }} ({{ $product->shop->address }})</span>
                    </div>
                @endif
            </div>

            <!-- THE 2 PRIMARY REAL-WORLD ACTION BUTTONS -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px;">
                <!-- Action 1: Doorstep Delivery Checkout -->
                <a href="{{ route('checkout', ['product_id' => $product->id, 'quantity' => 1]) }}" style="background: linear-gradient(135deg, #5E17EB, #7C3AED); color: #FFFFFF; text-decoration: none; font-weight: 800; font-size: 14px; padding: 14px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); text-align: center;">
                    <span>🚚 Doorstep Delivery</span>
                </a>

                <!-- Action 2: Inspect First, Pay Later (Pickup Reservation) -->
                <form method="POST" action="{{ route('reservation.create') }}" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="shop_id" value="{{ $product->shop_id }}">
                    <input type="hidden" name="customer_name" value="{{ Auth::user()->name ?? 'Shopper' }}">
                    <input type="hidden" name="customer_phone" value="{{ Auth::user()->phone ?? '08012345678' }}">
                    <button type="submit" style="width: 100%; height: 100%; background: #0F172A; color: var(--vm-gold); border: 1.5px solid var(--vm-gold); font-weight: 800; font-size: 13.5px; padding: 14px 10px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span>🏢 Reserve for In-Shop Pickup</span>
                    </button>
                </form>
            </div>

            <!-- Description -->
            <div style="border-top: 1px solid var(--vm-border); padding-top: 18px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 8px;">Product Description &amp; Verification Note:</h3>
                <p style="font-size: 13px; color: #475569; line-height: 1.6; margin: 0;">
                    {{ $product->description }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection