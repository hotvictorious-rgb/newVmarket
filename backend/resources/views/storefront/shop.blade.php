@extends('layouts.app')

@section('title', $shop->name . ' — Official Verified Storefront | Victorious MARKET')

@section('content')
<div class="vm-container" style="padding: 24px 16px 80px; max-width: 1200px;">

    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span>&rsaquo;</span>
        <a href="{{ route('storefront') }}#merchants" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Verified Merchants</a>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ $shop->name }}</span>
    </div>

    <!-- 1. Dedicated Vendor Hero Header Card -->
    <div style="background: linear-gradient(135deg, #1E1B4B 0%, #311042 50%, #0F172A 100%); border-radius: 18px; padding: 32px 28px; color: #FFFFFF; margin-bottom: 24px; box-shadow: 0 10px 30px -10px rgba(94, 23, 235, 0.3); position: relative; overflow: hidden;">
        
        <!-- Decorative Ambient Glow -->
        <div style="position: absolute; right: -40px; top: -40px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(255, 215, 0, 0.15) 0%, transparent 70%); pointer-events: none;"></div>

        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 24px; position: relative; z-index: 2;">
            
            <!-- Left: Logo & Store Bio -->
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div style="width: 88px; height: 88px; border-radius: 16px; background: #FFFFFF; border: 3px solid rgba(255, 215, 0, 0.8); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 8px 20px rgba(0,0,0,0.25);">
                    <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
                        <h1 style="font-size: 24px; font-weight: 800; color: #FFFFFF; margin: 0; line-height: 1.2;">
                            {{ $shop->name }}
                        </h1>
                        <span style="background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 999px; letter-spacing: 0.4px;">
                            ★ ACCREDITED MERCHANT
                        </span>
                    </div>

                    <div style="font-size: 13.5px; color: #E2E8F0; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span>🏢 <strong>{{ $shop->vendor->business_name ?? 'Victorious Verified Partner' }}</strong></span>
                        <span style="opacity: 0.5;">•</span>
                        <span>📍 {{ $shop->address }}, {{ $shop->lga->name ?? 'Uyo' }}, Akwa Ibom</span>
                    </div>

                    <!-- Pickup Hours & Status Badges -->
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 12px;">
                        <span style="background: {{ $isOpenNow ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' }}; color: {{ $isOpenNow ? '#34D399' : '#F87171' }}; border: 1px solid {{ $isOpenNow ? '#059669' : '#DC2626' }}; padding: 3px 10px; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                            <span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $isOpenNow ? '#34D399' : '#F87171' }};"></span>
                            {{ $isOpenNow ? 'Store Open for Inspection' : 'Store Currently Closed' }}
                        </span>

                        <span style="color: #CBD5E1; font-weight: 500;">
                            🕒 Store Operating Hours: {{ substr($shop->pickup_opening_time ?? '08:00', 0, 5) }} &ndash; {{ substr($shop->pickup_closing_time ?? '18:00', 0, 5) }}
                        </span>

                        <span style="background: rgba(94, 23, 235, 0.4); color: #E9D5FF; padding: 3px 10px; border-radius: 6px; font-weight: 600;">
                            ⚡ In-Shop Counter Ready
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Direct Phone & WhatsApp CTA -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                @if(!empty($shop->phone))
                    <a href="tel:{{ $shop->phone }}" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); color: #FFFFFF; font-weight: 700; font-size: 13px; padding: 10px 18px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;">
                        <span>📞 Call Store</span>
                    </a>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $shop->phone) }}" target="_blank" style="background: #25D366; color: #FFFFFF; font-weight: 700; font-size: 13px; padding: 10px 18px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35); transition: all 0.2s ease;">
                        <span>💬 WhatsApp</span>
                    </a>
                @endif
            </div>

        </div>

    </div>

    <!-- 2. Store Guarantees Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 28px;">
        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="font-size: 24px; color: var(--vm-primary);">🏢</div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">In-Shop Inspection</strong>
                <span style="font-size: 11px; color: #64748B;">Inspect physically at Plaza counter before payment release</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="font-size: 24px; color: #059669;">🪙</div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">5% Cashback Reward</strong>
                <span style="font-size: 11px; color: #64748B;">Earned on every purchase from this official store</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="font-size: 24px; color: #2563EB;">🛡️</div>
            <div>
                <strong style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Escrow Safeguard</strong>
                <span style="font-size: 11px; color: #64748B;">Money protected until you confirm delivery or pickup OTP</span>
            </div>
        </div>

        <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
            <div style="font-size: 24px; color: #D97706;">⚡</div>
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
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search in this store..." style="flex-grow: 1; padding: 9px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px; outline: none; background: #F8FAFC;">
                <button type="submit" style="background: var(--vm-primary); color: #FFF; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer;">
                    Search
                </button>
            </form>
        </div>

        <!-- Store Category Filter Chips -->
        <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none;">
            <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-mob-cat-chip {{ !request('category') ? 'active' : '' }}" style="font-size: 12px; text-decoration: none;">
                <span>✦ All Shop Items</span>
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
                            <span style="position: absolute; top: 10px; right: 10px; background: var(--vm-primary, #5E17EB); color: #FFFFFF; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 999px; letter-spacing: 0.4px;">
                                ★ FLAGSHIP
                            </span>
                        @endif

                        <!-- Dynamic Proximity Tag -->
                        <span style="position: absolute; bottom: 8px; left: 8px; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;
                            @if($isLocalShop)
                                background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.2);
                            @elseif($lane)
                                background: #EFF6FF; color: #1D4ED8; border: 1px solid rgba(29, 78, 216, 0.2);
                            @else
                                background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;
                            @endif">
                            @if($isLocalShop)
                                <span>⚡</span><span>In-Shop & Same-Day</span>
                            @elseif($lane)
                                <span>🚚</span><span>Delivers to {{ $activeCity }} (₦{{ number_format((float)$lane->base_fee) }})</span>
                            @else
                                <span>📦</span><span>Nationwide Transit</span>
                            @endif
                        </span>
                    </div>

                    <!-- Product Information -->
                    <div class="vm-product-info" style="padding: 12px 14px 10px;">
                        <div class="vm-product-shop" style="display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #64748B; margin-bottom: 5px;">
                            <span>🏬</span>
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
                                ₦{{ number_format((float)$product->unit_price, 2) }}
                            </span>
                            <span style="font-size: 10.5px; color: #059669; font-weight: 700; background: #ECFDF5; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(5, 150, 105, 0.15);">
                                +5% Reward
                            </span>
                        </div>

                        <!-- Star Rating -->
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11px; color: #64748B; margin-bottom: 4px;">
                            <span style="color: #F59E0B; font-size: 11.5px;">★</span>
                            <strong style="color: #0F172A; font-weight: 700;">4.8</strong>
                            <span>(86)</span>
                            <span style="opacity: 0.4;">•</span>
                            <span style="color: #10B981; font-weight: 600;">In Stock</span>
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
                                style="background: #F5F3FF; border: 1.5px solid rgba(94, 23, 235, 0.25); color: #5E17EB; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; transition: all 0.15s ease;">
                            <span>🛒 + Cart</span>
                        </button>
                        <a href="{{ route('product.detail', $product->slug) }}" style="background: #0F172A; border: none; color: #FFFFFF; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.15s ease;">
                            <span>Details →</span>
                        </a>
                    </div>
                </div>

            </div>
        @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1px dashed var(--vm-border); border-radius: 16px;">
                <div style="font-size: 40px; margin-bottom: 12px;">🏬</div>
                <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Products Found in this Store</h3>
                <p style="font-size: 13px; color: #64748B; margin: 0 0 16px;">Try clearing your search or category filter to browse other verified merchandise.</p>
                <a href="{{ route('shop.storefront', $shop->slug) }}" style="background: var(--vm-primary); color: #FFF; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block;">
                    View All {{ $shop->name }} Items
                </a>
            </div>
        @endforelse
    </div>

</div>
@endsection
