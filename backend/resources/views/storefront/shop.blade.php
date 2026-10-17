@extends('layouts.app')

@section('title', $shop->name . ' — Official Verified Storefront | Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 24px 16px 80px; max-width: 1200px;">

    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span style="opacity: 0.5;">&rsaquo;</span>
        <a href="{{ route('storefront') }}#merchants" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Verified Merchants</a>
        <span style="opacity: 0.5;">&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ $shop->name }}</span>
    </div>

    <!-- 1. Dedicated Vendor Hero Header Card -->
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 50%, #1E293B 100%); border-radius: 18px; padding: 32px 28px; color: #FFFFFF; margin-bottom: 24px; box-shadow: 0 14px 34px -10px rgba(15, 23, 42, 0.45); border: 1px solid rgba(255, 255, 255, 0.08); position: relative; overflow: hidden;">
        
        <!-- Subtle Ambient Lighting -->
        <div style="position: absolute; right: -50px; top: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(94, 23, 235, 0.25) 0%, transparent 70%); pointer-events: none;"></div>
        <div style="position: absolute; left: 20%; bottom: -60px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(255, 215, 0, 0.12) 0%, transparent 70%); pointer-events: none;"></div>

        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; position: relative; z-index: 2;">
            
            <!-- Left: Logo & Physical Store Identity -->
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div style="width: 88px; height: 88px; border-radius: 16px; background: #FFFFFF; border: 2.5px solid #FFD700; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.35);">
                    <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <div>
                    <!-- Shop Branch Title & Verification Badge -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
                        <h1 style="font-size: 24px; font-weight: 800; color: #FFFFFF; margin: 0; line-height: 1.25; letter-spacing: -0.3px;">
                            {{ $shop->name }}
                        </h1>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.4px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            <span>ACCREDITED MERCHANT</span>
                        </span>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255, 255, 255, 0.12); color: #F1F5F9; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.4px; border: 1px solid rgba(255, 255, 255, 0.15);">
                            <span>VERIFIED OUTLET</span>
                        </span>
                    </div>

                    <!-- Parent Merchant Entity & Physical LGA Address Binding -->
                    <div style="font-size: 13.5px; color: #CBD5E1; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                            <span>Merchant: <strong style="color: #F8FAFC;">{{ $shop->vendor->business_name ?? 'Victorious Verified Partner' }}</strong></span>
                        </span>
                        <span style="opacity: 0.4;">•</span>
                        <span style="display: inline-flex; align-items: center; gap: 6px; color: #F1F5F9;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>{{ $shop->address }}, <strong style="color: #38BDF8;">{{ $shop->lga->name ?? 'Uyo' }} LGA</strong>, Akwa Ibom</span>
                        </span>
                    </div>

                    <!-- Live Operating Hours & Counter Readiness -->
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 12px;">
                        <span style="background: {{ $isOpenNow ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $isOpenNow ? '#34D399' : '#F87171' }}; border: 1px solid {{ $isOpenNow ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)' }}; padding: 3px 10px; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                            <span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $isOpenNow ? '#34D399' : '#F87171' }}; box-shadow: 0 0 8px {{ $isOpenNow ? '#34D399' : '#F87171' }};"></span>
                            <span>{{ $isOpenNow ? 'Open Now for Inspection' : 'Store Currently Closed' }}</span>
                        </span>

                        <span style="display: inline-flex; align-items: center; gap: 6px; color: #94A3B8; font-weight: 500;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>Store Operating Hours: {{ substr($shop->pickup_opening_time ?? '08:00', 0, 5) }} &ndash; {{ substr($shop->pickup_closing_time ?? '18:00', 0, 5) }}</span>
                        </span>

                        <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(94, 23, 235, 0.3); color: #E9D5FF; border: 1px solid rgba(168, 85, 247, 0.35); padding: 3px 10px; border-radius: 6px; font-weight: 600;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span>In-Shop Counter Ready</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Platform Escrow & Support Desk Action -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.16); padding: 8px 16px; border-radius: 12px; display: flex; align-items: center; gap: 10px; color: #FFFFFF;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 215, 0, 0.15); color: #FFD700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <strong style="display: block; font-size: 12.5px; line-height: 1.2;">Paystack Escrow Protected</strong>
                        <span style="color: #94A3B8; font-size: 11px;">100% Platform Settled &bull; Zero Direct Risk</span>
                    </div>
                </div>
                <button type="button" onclick="vmOpenPolicyModal('contact')" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22); color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 10px 16px; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Help Desk</span>
                </button>
            </div>

        </div>

        <!-- Multi-Branch Switcher (When merchant operates multiple outlets across LGAs) -->
        @if($shop->vendor && $shop->vendor->shops && $shop->vendor->shops->count() > 1)
            <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.12); display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 11.5px; color: #94A3B8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Merchant Branches:</span>
                </span>
                
                <span style="background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.4); color: #FFD700; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>{{ $shop->name }} ({{ $shop->lga->name ?? 'Active Branch' }})</span>
                </span>

                @foreach($shop->vendor->shops as $otherBranch)
                    @if($otherBranch->id !== $shop->id)
                        <a href="{{ route('shop.storefront', $otherBranch->slug ?? $otherBranch->id) }}" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.18); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: background 0.15s ease;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>{{ $otherBranch->name }} ({{ $otherBranch->lga->name ?? 'LGA' }})</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

    </div>

    <!-- 2. Store Guarantees Strip (Vector Micro-Icons) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 28px;">
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">In-Shop Inspection</strong>
                <span style="font-size: 11px; color: #64748B;">Inspect physically at Plaza counter before payment release</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(5, 150, 105, 0.08); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
            </div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">5% Cashback Reward</strong>
                <span style="font-size: 11px; color: #64748B;">Earned on every purchase from this official store</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(37, 99, 235, 0.08); color: #2563EB; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Escrow Safeguard</strong>
                <span style="font-size: 11px; color: #64748B;">Money protected until you confirm delivery or pickup OTP</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(217, 119, 6, 0.08); color: #D97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Same-Day LGA Dispatch</strong>
                <span style="font-size: 11px; color: #64748B;">Available across Uyo and Akwa Ibom corridors</span>
            </div>
        </div>
    </div>

    <!-- 3. Store Catalog & Filters -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 20px 22px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0 0 4px;">
                    Verified Products at {{ $shop->name }}
                </h2>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">
                    Showing {{ $products->count() }} authentic items available for instant delivery or counter pickup.
                </p>
            </div>

            <!-- In-Store Search Bar -->
            <form action="{{ route('shop.storefront', $shop->slug) }}" method="GET" style="display: flex; gap: 8px; max-width: 340px; width: 100%;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search in this branch..." style="flex-grow: 1; padding: 9px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px; outline: none; background: #F8FAFC;">
                <button type="submit" style="background: var(--vm-primary); color: #FFF; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span>Search</span>
                </button>
            </form>
        </div>

        <!-- Store Category Filter Chips -->
        <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none;">
            <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-mob-cat-chip {{ !request('category') ? 'active' : '' }}" style="font-size: 12px; text-decoration: none;">
                <span>All Branch Items</span>
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('shop.storefront', ['slug' => $shop->slug, 'category' => $cat->slug]) }}" class="vm-mob-cat-chip {{ request('category') == $cat->slug ? 'active' : '' }}" style="font-size: 12px; text-decoration: none;">
                    <span>{{ $cat->name }}</span>
                </a>
            @endforeach
        </div>

    </div>

    <!-- 4. Shop Products Grid -->
    <div class="vm-products-grid" id="vmProductsGridContainer">
        @forelse($products as $product)
            @php
                $shopLgaId = $product->shop->lga_id ?? null;
                $isLocalShop = ($shopLgaId == $selectedLgaId);
                $lane = $activeLanes ? $activeLanes->firstWhere('origin_lga_id', $shopLgaId) : null;
            @endphp
            <div class="vm-product-card">
                <div>
                    <!-- Product Thumbnail Box -->
                    <div class="vm-product-img-wrap" style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" class="vm-product-img" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                        
                        @if($product->is_vmarket_flagship)
                            <span style="position: absolute; top: 10px; right: 10px; background: var(--vm-primary, #5E17EB); color: #FFFFFF; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 999px; letter-spacing: 0.4px; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                <span>FLAGSHIP</span>
                            </span>
                        @endif

                        <!-- Dynamic Proximity Tag -->
                        <span style="position: absolute; bottom: 8px; left: 8px; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;
                            @if($isLocalShop)
                                background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.2);
                            @elseif($lane)
                                background: #EFF6FF; color: #1D4ED8; border: 1px solid rgba(29, 78, 216, 0.2);
                            @else
                                background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;
                            @endif">
                            @if($isLocalShop)
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span>In-Shop &amp; Same-Day</span>
                            @elseif($lane)
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <span>Delivers to {{ $activeCity }} (&#8358;{{ number_format((float)$lane->base_fee) }})</span>
                            @else
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                <span>Nationwide Transit</span>
                            @endif
                        </span>
                    </div>

                    <!-- Product Information -->
                    <div class="vm-product-info" style="padding: 12px 14px 10px;">
                        <div class="vm-product-shop" style="display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #64748B; margin-bottom: 5px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                            <span style="font-weight: 500;">{{ Str::limit($shop->name, 20) }}</span>
                            <span style="opacity: 0.5;">•</span>
                            <span style="color: #94A3B8;">{{ $shop->lga->name ?? 'Uyo' }}</span>
                        </div>

                        <h3 class="vm-product-name" style="font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 8px; line-height: 1.35; min-height: 36px;">
                            <a href="{{ route('product.detail', $product->slug) }}" style="color: inherit; text-decoration: none;">
                                {{ $product->name }}
                            </a>
                        </h3>

                        <div class="vm-product-price-row" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
                            <span class="vm-price-current" style="font-size: 16.5px; font-weight: 800; color: #5E17EB;">
                                &#8358;{{ number_format((float)$product->unit_price, 2) }}
                            </span>
                            <span style="font-size: 10.5px; color: #059669; font-weight: 700; background: #ECFDF5; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(5, 150, 105, 0.15);">
                                +5% Reward
                            </span>
                        </div>

                        <!-- Star Rating & Stock Pill -->
                        <div style="display: flex; align-items: center; gap: 5px; font-size: 11px; color: #64748B; margin-bottom: 4px;">
                            <span style="color: #F59E0B; display: inline-flex; align-items: center;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </span>
                            <strong style="color: #0F172A; font-weight: 700;">4.8</strong>
                            <span>(86)</span>
                            <span style="opacity: 0.4;">•</span>
                            <span style="color: #10B981; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;">
                                <span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span>
                                <span>In Stock</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Add to Cart & Details Action -->
                <div style="padding: 0 14px 14px;">
                    <div class="vm-card-action-grid">
                        <button type="button" class="vm-btn-add-cart" 
                                data-id="{{ $product->id }}" 
                                data-name="{{ $product->name }}" 
                                data-price="{{ $product->unit_price }}" 
                                data-image="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}"
                                data-shop="{{ $shop->name }}" 
                                data-lga="{{ $shop->lga->name ?? 'Uyo' }}" 
                                data-slug="{{ $product->slug }}"
                                style="background: #F5F3FF; border: 1.5px solid rgba(94, 23, 235, 0.25); color: #5E17EB; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.15s ease;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <span>+ Cart</span>
                        </button>
                        <a href="{{ route('product.detail', $product->slug) }}" style="background: #0F172A; border: none; color: #FFFFFF; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.15s ease;">
                            <span>Details</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>
                </div>

            </div>
        @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1px dashed var(--vm-border); border-radius: 16px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #F1F5F9; color: #64748B; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                </div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Products Found in this Branch</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0 0 16px;">Try clearing your search or category filter to browse other verified merchandise at this branch.</p>
                <a href="{{ route('shop.storefront', $shop->slug) }}" style="background: var(--vm-primary); color: #FFF; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block;">
                    View All {{ $shop->name }} Items
                </a>
            </div>
        @endforelse
    </div>

</div>
@endsection
