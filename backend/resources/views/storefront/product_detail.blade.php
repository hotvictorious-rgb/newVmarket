@extends('layouts.app')

@section('title', $product->name . ' — Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 24px 16px 80px; max-width: 1200px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span style="opacity: 0.5;">&rsaquo;</span>
        <a href="{{ route('storefront', ['category' => $product->category->slug ?? 'electronics-gadgets']) }}" style="color: #64748B; text-decoration: none; font-weight: 500;">{{ $product->category->name ?? 'Electronics' }}</a>
        <span style="opacity: 0.5;">&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ Str::limit($product->name, 40) }}</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 36px; background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 18px; padding: 32px; box-shadow: var(--vm-shadow-sm);">
        
        <!-- Left Column: Gallery & Verification Badges -->
        <div>
            <div style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-radius: 14px; border: 1.5px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 16px; overflow: hidden;">
                <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 82%; max-height: 82%; object-fit: contain;">
                
                <span style="position: absolute; top: 14px; left: 14px; background: #0F172A; color: #FFFFFF; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FFD700" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>ESCROW SECURED</span>
                </span>

                <span style="position: absolute; top: 14px; right: 14px; background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.25); font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10B981;"></span>
                    <span>7-DAY CERTIFIED FRESH</span>
                </span>
            </div>

            <!-- Trust Features Strip -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; font-size: 12px;">
                    <div style="font-weight: 700; color: #0F172A; margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--vm-primary)" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <span>Uyo Local Dispatch</span>
                    </div>
                    <div style="color: #64748B; font-size: 11px; line-height: 1.35;">Products are sourced from approved Uyo suppliers and dispatched by Victorious MARKET.</div>
                </div>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; font-size: 12px;">
                    <div style="font-weight: 700; color: #0F172A; margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>24h Return Buffer</span>
                    </div>
                    <div style="color: #64748B; font-size: 11px; line-height: 1.35;">Funds locked in Paystack escrow until doorstep handshake OTP</div>
                </div>
            </div>
        </div>

        <!-- Right Column: Pricing, Delivery Options, Action Buttons -->
        <div>
            <!-- Rating & Category Row -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <a href="{{ route('storefront', ['category' => $product->category->slug ?? 'electronics-gadgets']) }}" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; text-decoration: none;">
                        {{ $product->category->name ?? 'Electronics' }}
                    </a>
                    @if($product->condition === 'uk_used')
                        <span style="background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>UK Used (Grade A)</span>
                        </span>
                    @else
                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <span>Brand New</span>
                        </span>
                    @endif
                    <span style="background: {{ in_array($product->package_tier, ['keke', 'bulky']) ? '#FEF3C7' : '#E0F2FE' }}; color: {{ in_array($product->package_tier, ['keke', 'bulky']) ? '#B45309' : '#0369A1' }}; border: 1px solid {{ in_array($product->package_tier, ['keke', 'bulky']) ? '#FDE68A' : '#BAE6FD' }}; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                        @if(in_array($product->package_tier, ['keke', 'bulky']))
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span>Keke Cargo</span>
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-3l-3 6.5h8.5z"/></svg>
                            <span>Bike Delivery</span>
                        @endif
                    </span>
                </div>
                <div style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: #64748B;">
                    <span style="color: #F59E0B; display: inline-flex; align-items: center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </span>
                    <strong style="color: #0F172A; font-weight: 800;">4.7</strong>
                    <span>(124 reviews)</span>
                </div>
            </div>

            <!-- Product Title -->
            <h1 style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 0 0 10px; line-height: 1.25; letter-spacing: -0.3px;">
                {{ $product->name }}
            </h1>

            <!-- Authoritative Retailer Entity & Central Dispatch Hub -->
            <div style="font-size: 13px; color: #64748B; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 12px;">
                <span style="display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/></svg>
                    <span>Retailer: <strong style="color: var(--vm-primary);">Victorious MARKET</strong></span>
                </span>
                <span style="opacity: 0.4;">•</span>
                <span style="display: inline-flex; align-items: center; gap: 5px; color: #334155;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284C7" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Dispatch Origin: <strong style="color: #0284C7;">Uyo Central Hub, Akwa Ibom</strong></span>
                </span>
                @if($product->shop && $product->shop->slug)
                    <span style="opacity: 0.4;">•</span>
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #64748B;">
                        <span>Sourcing Partner: <a href="{{ route('shop.storefront', $product->shop->slug) }}" style="color: #64748B; text-decoration: none; font-weight: 600;">{{ $product->shop->name }}</a></span>
                    </span>
                @endif
            </div>

            <!-- Price & Cashback Banner -->
            <div style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.04) 0%, rgba(255, 215, 0, 0.08) 100%); border: 1.5px solid var(--vm-gold); border-radius: 14px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 11px; color: #64748B; font-weight: 800; display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px;">MARKETPLACE RETAIL PRICE</span>
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
                    <span style="background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.25); font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 4px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                        <span>+&#8358;{{ number_format((float)$cashbackEarnable, 2) }} Cashback</span>
                    </span>
                    <div style="font-size: 11px; color: #059669; font-weight: 600;">5% credited 24h after delivery</div>
                </div>
            </div>

            <!-- Feature Spec Pills -->
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <span>{{ $product->condition_label }}</span>
                </div>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>{{ $product->warranty_label }}</span>
                </div>
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 999px; padding: 5px 12px; font-size: 12px; font-weight: 700; color: #1E293B;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>{{ $product->package_tier_label }}</span>
                </div>
            </div>

            @php
                $isProductInStock = (bool)$product->is_in_stock && $product->is_available_for_sale;
            @endphp

            <!-- Stock Status & Quantity Stepper -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 12px 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    @if($isProductInStock)
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        <strong style="color: #0F172A; font-size: 13.5px;">In Stock</strong>
                        <span style="font-size: 12px; color: #64748B;">(Verified Central Stock)</span>
                    @else
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #EF4444; display: inline-block;"></span>
                        <strong style="color: #DC2626; font-size: 13.5px;">Out of Stock</strong>
                        <span style="font-size: 12px; color: #64748B;">(Currently unavailable)</span>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 12.5px; font-weight: 600; color: #475569;">Quantity:</span>
                    <div style="display: inline-flex; align-items: center; border: 1.5px solid #CBD5E1; border-radius: 8px; background: #FFFFFF; overflow: hidden;">
                        <button type="button" onclick="const i = document.getElementById('vmProductQtyInput'); if (parseInt(i.value) > 1) i.value = parseInt(i.value) - 1;" style="width: 32px; height: 32px; border: none; background: #F1F5F9; font-weight: 800; cursor: pointer; color: #334155;">-</button>
                        <input type="number" id="vmProductQtyInput" value="1" min="1" max="99" style="width: 40px; height: 32px; border: none; text-align: center; font-size: 13.5px; font-weight: 800; color: #0F172A; outline: none;">
                        <button type="button" onclick="const i = document.getElementById('vmProductQtyInput'); const m = parseInt(i.getAttribute('max')) || 99; if (parseInt(i.value) < m) i.value = parseInt(i.value) + 1;" style="width: 32px; height: 32px; border: none; background: #F1F5F9; font-weight: 800; cursor: pointer; color: #334155;">+</button>
                    </div>
                </div>
            </div>

            <!-- Directional Corridor Fulfillment Card -->
            <div style="background: #F8FAFC; border: 1.5px solid var(--vm-border); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 11.5px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>Delivering to:</span>
                    </span>
                    <button type="button" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal();" style="background: none; border: none; color: var(--vm-primary); font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">
                        Change Destination ({{ $activeCity }})
                    </button>
                </div>

                @if($deliveryCheck['is_available'])
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #1D4ED8; font-weight: 700; font-size: 13.5px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span>{{ strtolower($activeCity) === 'uyo' ? 'Doorstep to' : 'Regional Delivery to' }} {{ $activeCity }}: &#8358;{{ number_format((float)$deliveryCheck['fee'], 2) }}</span>
                        </div>
                        <span style="background: #DBEAFE; color: #1E40AF; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>{{ $deliveryCheck['estimated_time_text'] }}</span>
                        </span>
                    </div>
                @else
                    <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 6px; color: #B45309; font-weight: 700; font-size: 13px; margin-bottom: 2px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>Route Opening Soon: Uyo Central Hub &rarr; {{ $activeCity }}</span>
                        </div>
                        <p style="font-size: 11.5px; color: #92400E; margin: 0; line-height: 1.4;">
                            Delivery to this destination is currently being onboarded. Prefer pickup? Contact Victorious MARKET support to coordinate verified collection at our Uyo Central Hub.
                        </p>
                    </div>
                @endif

                <div style="background: #F1F5F9; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 12px; font-size: 12px; color: #475569; line-height: 1.4;">
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #1E293B; margin-bottom: 3px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <span>Support-Assisted Pickup Policy</span>
                    </div>
                    <span>Prefer pickup? Please call or WhatsApp Victorious MARKET support to ask whether pickup can be arranged for your complete order at our Uyo Central Hub.</span>
                </div>
            </div>

            <!-- THE 3 PRIMARY CALL TO ACTION BUTTONS -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                @if($isProductInStock)
                    <!-- Action 1: Add to Cart (Purple) -->
                    <button type="button" class="vm-btn-add-cart" 
                            data-id="{{ $product->id }}" 
                            data-name="{{ $product->name }}" 
                            data-price="{{ $product->unit_price }}" 
                            data-image="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}"
                            data-shop="Victorious MARKET" 
                            data-lga="Uyo" 
                            data-slug="{{ $product->slug }}"
                            style="background: #5E17EB; color: #FFFFFF; border: none; font-weight: 800; font-size: 14px; padding: 14px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); transition: transform 0.15s ease;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span>Add to Cart</span>
                    </button>

                    <!-- Action 2: Buy Now (Gold with Customer Account Interception) -->
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
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Buy Now &amp; Escrow Secure</span>
                    </a>
                @else
                    <!-- Out of Stock Disabled State -->
                    <button type="button" disabled
                            style="background: #94A3B8; color: #FFFFFF; border: none; font-weight: 800; font-size: 14px; padding: 14px 18px; border-radius: 10px; cursor: not-allowed; display: flex; align-items: center; justify-content: center; gap: 8px; opacity: 0.7;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Out of Stock</span>
                    </button>
                    <button type="button" disabled
                            style="background: #E2E8F0; color: #64748B; border: none; font-weight: 800; font-size: 14px; padding: 14px 18px; border-radius: 10px; cursor: not-allowed; display: flex; align-items: center; justify-content: center; gap: 8px; opacity: 0.7;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Currently Unavailable</span>
                    </button>
                @endif
            </div>

            <!-- Supplier pickup is not a public self-service flow. Exceptions are centrally approved. -->
            <div style="margin: 0 0 20px; padding: 12px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; color: #475569; font-size: 12.5px; line-height: 1.5;">
                Victorious MARKET sources from approved suppliers in Uyo. Standard orders are paid through the platform and delivered within Uyo. Any pickup exception must be arranged directly with Victorious MARKET support; supplier shop details are not published here.
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