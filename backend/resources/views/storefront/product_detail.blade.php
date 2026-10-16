@extends('layouts.app')

@section('title', $product->name . ' — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 30px 16px 60px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span>&rsaquo;</span>
        <a href="{{ route('storefront', ['category' => $product->category->slug ?? 'electronics-gadgets']) }}" style="color: #64748B; text-decoration: none; font-weight: 500;">{{ $product->category->name ?? 'Electronics' }}</a>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ Str::limit($product->name, 40) }}</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 36px; background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 32px; box-shadow: var(--vm-shadow-sm);">
        
        <!-- Left Column: Gallery & Verification Badges -->
        <div>
            <div style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-radius: 12px; border: 1.5px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 16px;">
                <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                
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
            <!-- Rating & Category Row (Mockup Screen 8 Parity) -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="{{ route('storefront', ['category' => $product->category->slug ?? 'electronics-gadgets']) }}" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; text-decoration: none;">
                        {{ $product->category->name ?? 'Electronics' }}
                    </a>
                    @if($product->condition === 'uk_used')
                        <span style="background: #EEF2FF; color: #4338CA; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px;">
                            🇬🇧 UK Used
                        </span>
                    @else
                        <span style="background: #ECFDF5; color: #047857; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px;">
                            ✨ Brand New
                        </span>
                    @endif
                    <span style="background: {{ in_array($product->package_tier, ['keke', 'bulky']) ? '#FEF3C7' : '#E0F2FE' }}; color: {{ in_array($product->package_tier, ['keke', 'bulky']) ? '#B45309' : '#0369A1' }}; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px;">
                        {{ in_array($product->package_tier, ['keke', 'bulky']) ? '🛺 Keke Cargo' : '🏍️ Bike Delivery' }}
                    </span>
                </div>
                <div style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: #64748B;">
                    <span style="color: #F59E0B; font-size: 15px;">★</span>
                    <strong style="color: #0F172A; font-weight: 800;">4.7</strong>
                    <span>(124 reviews)</span>
                </div>
            </div>

            <h1 style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 0 0 10px; line-height: 1.25;">
                {{ $product->name }}
            </h1>

            <div style="font-size: 13px; color: #64748B; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span>Verified Merchant:</span>
                <a href="{{ route('shop.storefront', $product->shop->slug ?? $product->shop_id) }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 700;">
                    {{ $product->vendor->business_name ?? $product->shop->name }}
                </a>
                <span>&bull;</span>
                <a href="{{ route('shop.storefront', $product->shop->slug ?? $product->shop_id) }}" style="color: #475569; text-decoration: none; font-weight: 600;">
                    Branch: {{ $product->shop->name }} ({{ $product->shop->lga->name ?? 'Uyo' }}) 🏬
                </a>
            </div>

            <!-- Price & Cashback Banner (Mockup Screen 8 Parity) -->
            <div style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.08) 100%); border: 1.5px solid var(--vm-gold); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 11.5px; color: #64748B; font-weight: 700; display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.4px;">MARKETPLACE RETAIL PRICE</span>
                    <div style="display: flex; align-items: baseline; gap: 10px;">
                        <span style="font-size: 30px; font-weight: 900; color: var(--vm-primary);">
                            &#8358;{{ number_format((float)$product->unit_price, 2) }}
                        </span>
                        @if($product->slash_price && (float)$product->slash_price > (float)$product->unit_price)
                            <span style="font-size: 16px; color: #94A3B8; text-decoration: line-through; font-weight: 600;">
                                &#8358;{{ number_format((float)$product->slash_price, 2) }}
                            </span>
                            @if($product->discount_percent)
                                <span style="background: #FEF2F2; color: #DC2626; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 4px;">
                                    -{{ $product->discount_percent }}% OFF
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.25); font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 6px; display: inline-block; margin-bottom: 4px;">
                        🪙 +&#8358;{{ number_format((float)$cashbackEarnable, 2) }} Cashback
                    </span>
                    <div style="font-size: 11px; color: #059669; font-weight: 600;">5% credited 24h after delivery</div>
                </div>
            </div>

            <!-- Feature Spec Pills (Mockup Screen 8 Parity) -->
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <span>🏷️</span> {{ $product->condition_label }}
                </div>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <span>🛡️</span> {{ $product->warranty_label }}
                </div>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <span>🚚</span> {{ $product->package_tier_label }}
                </div>
            </div>

            <!-- Stock Status & Quantity Stepper (Mockup Screen 8 Parity) -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 12px 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                    <strong style="color: #0F172A; font-size: 13.5px;">In Stock</strong>
                    <span style="font-size: 12px; color: #64748B;">(Verified at {{ $product->shop->name }})</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 12.5px; font-weight: 600; color: #475569;">Quantity:</span>
                    <div style="display: inline-flex; align-items: center; border: 1.5px solid #CBD5E1; border-radius: 8px; background: #FFFFFF; overflow: hidden;">
                        <button type="button" onclick="const i = document.getElementById('vmProductQtyInput'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1;" style="width: 32px; height: 32px; border: none; background: #F1F5F9; font-weight: 800; cursor: pointer; color: #334155;">-</button>
                        <input type="number" id="vmProductQtyInput" value="1" min="1" max="{{ max(1, $product->inventory_quantity ?? 1) }}" style="width: 40px; height: 32px; border: none; text-align: center; font-size: 13.5px; font-weight: 800; color: #0F172A; outline: none;">
                        <button type="button" onclick="const i = document.getElementById('vmProductQtyInput'); const m = parseInt(i.getAttribute('max')) || 99; if (parseInt(i.value) < m) i.value = parseInt(i.value) + 1;" style="width: 32px; height: 32px; border: none; background: #F1F5F9; font-weight: 800; cursor: pointer; color: #334155;">+</button>
                    </div>
                    @if(($product->inventory_quantity ?? 0) <= 5 && ($product->inventory_quantity ?? 0) > 0)
                        <span style="font-size: 11px; color: #DC2626; font-weight: 700; margin-left: 4px;">Only {{ $product->inventory_quantity }} left!</span>
                    @endif
                </div>
            </div>

            <!-- Directional Corridor Card (Mockup Screen 3 Parity) -->
            <div style="background: #F8FAFC; border: 1.5px solid var(--vm-border); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Fulfillment to Your Location:</span>
                    <button type="button" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal();" style="background: none; border: none; color: var(--vm-primary); font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">
                        Change ({{ $activeCity }}) 📍
                    </button>
                </div>

                @if($deliveryCheck['is_available'])
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #1D4ED8; font-weight: 700; font-size: 13.5px;">
                            <span>🚚</span>
                            <span>Doorstep to {{ $activeCity }}: &#8358;{{ number_format((float)$deliveryCheck['fee'], 2) }}</span>
                        </div>
                        <span style="background: #DBEAFE; color: #1E40AF; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                            ⏱️ {{ $deliveryCheck['estimated_time_text'] }}
                        </span>
                    </div>
                @else
                    <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #B45309; font-weight: 700; font-size: 13px; margin-bottom: 2px;">
                            <span>⚠️</span>
                            <span>Route Suspended: {{ $product->shop->lga->name ?? 'Origin' }} &rarr; {{ $activeCity }}</span>
                        </div>
                        <p style="font-size: 11.5px; color: #92400E; margin: 0; line-height: 1.4;">
                            Doorstep delivery along this corridor is temporarily offline. You can still order for <strong>guaranteed free In-Store Counter Pickup</strong>!
                        </p>
                    </div>
                @endif

                @if($pickupCheck['is_available'])
                    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #065F46; font-size: 12.5px; font-weight: 600;">
                            <span>🏢</span>
                            <span>In-Store Inspection: <strong>FREE (&#8358;0.00)</strong> at {{ $product->shop->name }}</span>
                        </div>
                        <span style="background: #D1FAE5; color: #047857; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px;">
                            Zero Risk
                        </span>
                    </div>
                @endif
            </div>

            <!-- THE 2 PRIMARY CALL TO ACTION BUTTONS (Mockup Screen 8 Parity) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <!-- Action 1: Add to Cart (Purple) -->
                <button type="button" class="vm-btn-add-cart" 
                        data-id="{{ $product->id }}" 
                        data-name="{{ $product->name }}" 
                        data-price="{{ $product->unit_price }}" 
                        data-image="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}"
                        data-shop="{{ $product->shop->name ?? 'Verified Merchant' }}" 
                        data-lga="{{ $product->shop->lga->name ?? 'Uyo' }}" 
                        data-slug="{{ $product->slug }}"
                        style="background: #5E17EB; color: #FFFFFF; border: none; font-weight: 800; font-size: 14px; padding: 14px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); transition: transform 0.15s ease;">
                    <span>🛒 Add to Cart</span>
                </button>

                <!-- Action 2: Buy Now (Yellow/Gold with Customer Account Interception) -->
                <a href="{{ route('checkout', ['product_id' => $product->id, 'quantity' => 1]) }}" 
                   id="vmProductBuyNowBtn"
                   onclick="
                       const q = document.getElementById('vmProductQtyInput') ? document.getElementById('vmProductQtyInput').value : 1;
                       const targetUrl = '{{ route('checkout') }}?product_id={{ $product->id }}&quantity=' + q;
                       if (!window.vmIsCustomerAuth) {
                           event.preventDefault();
                           if (window.vmOpenAuthModal) {
                               window.vmOpenAuthModal({
                                   action: 'checkout',
                                   redirectUrl: targetUrl,
                                   title: 'Sign In to Buy Now',
                                   message: 'Only registered customers can complete checkout, receive 5% cashback, and track delivery with OTP.'
                               });
                           } else {
                               window.location.href = '/login?return_to=' + encodeURIComponent(targetUrl);
                           }
                           return false;
                       }
                       this.href = targetUrl;
                   " 
                   style="background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; text-decoration: none; font-weight: 800; font-size: 14px; padding: 14px 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35); text-align: center; transition: transform 0.15s ease;">
                    <span>⚡ Buy Now</span>
                </a>
            </div>

            <!-- Action 3: Inspect First, Pay Later (Pickup Reservation with Auth Shield) -->
            <form method="POST" action="{{ route('reservation.create') }}" id="vmReservationForm" style="margin: 0 0 20px;"
                  onsubmit="
                      if (!window.vmIsCustomerAuth) {
                          event.preventDefault();
                          if (window.vmOpenAuthModal) {
                              window.vmOpenAuthModal({
                                  action: 'reservation',
                                  title: 'Sign In to Reserve Item',
                                  message: 'Sign in or create an account so your 24-hour physical shop inspection pass is officially tied to your phone number.'
                              });
                          } else {
                              window.location.href = '/login';
                          }
                          return false;
                      }
                  ">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="shop_id" value="{{ $product->shop_id }}">
                <input type="hidden" name="customer_name" value="{{ Auth::user()->name ?? '' }}">
                <input type="hidden" name="customer_phone" value="{{ Auth::user()->phone ?? '' }}">
                <button type="submit" style="width: 100%; background: #F8FAFC; color: #0F172A; border: 1.5px solid #CBD5E1; font-weight: 700; font-size: 13px; padding: 11px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.15s ease;">
                    <span>🏢 Reserve for Free In-Shop Inspection</span>
                </button>
            </form>

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