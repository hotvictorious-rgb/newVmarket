@extends('layouts.app')

@section('title', 'Victorious MARKET — Premier Omnichannel Marketplace in Akwa Ibom')

@section('content')
<div class="vm-container" style="margin-top: 18px; margin-bottom: 40px;">

        <!-- Mobile Horizontal Category Swipe Track (Thumb-Friendly on Phones) -->
    <div class="vm-mobile-category-track">
        <a href="{{ route('storefront') }}" class="vm-mob-cat-chip {{ !request('category') ? 'active' : '' }}">
            <span>✦ All Items</span>
        </a>
        @foreach($categories as $cat)
            <a href="{{ route('storefront', ['category' => $cat->slug]) }}" class="vm-mob-cat-chip {{ request('category') == $cat->slug ? 'active' : '' }}">
                <span>{{ $cat->name }}</span>
            </a>
        @endforeach
    </div>

    <!-- 1. Top Hero Section: Side Category Dropdown (Left) + Dynamic Hero Banner (Right) -->
    <div class="vm-hero-wrapper">
        
        <!-- Category Side Dropdown Navigation (Desktop Left) -->
        <aside class="vm-category-sidebar" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; overflow: hidden; flex-direction: column; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-category-sidebar-header" style="background: linear-gradient(135deg, #1E1B4B 0%, #311042 100%); color: #FFFFFF; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
                <div class="vm-cat-header-left" style="display: flex; align-items: center; gap: 10px;">
                    <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="VM" style="width: 22px; height: 22px; border-radius: 6px; object-fit: cover;">
                    <span class="vm-cat-header-title" style="font-weight: 700; font-size: 14px; letter-spacing: -0.2px;">All Categories</span>
                </div>
                <span class="vm-cat-header-badge" style="background: var(--vm-gold); color: #0F172A; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 999px;">
                    {{ $categories->count() > 0 ? $categories->count() : 1 }}
                </span>
            </div>

            <ul class="vm-category-sidebar-list" style="list-style: none; margin: 0; padding: 6px 0; flex-grow: 1;">
                @forelse($categories as $cat)
                    <li class="vm-category-sidebar-item">
                        <a href="{{ route('storefront', ['category' => $cat->slug]) }}" class="vm-category-sidebar-link" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; color: #334155; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: background 0.15s ease;">
                            <span class="vm-cat-item-content" style="display: flex; align-items: center; gap: 10px;">
                                <span class="vm-cat-item-icon-badge" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">
                                    ✦
                                </span>
                                <span class="vm-cat-item-text">{{ $cat->name }}</span>
                            </span>
                            <span class="vm-cat-chevron" style="color: #94A3B8; font-size: 14px;">›</span>
                        </a>
                    </li>
                @empty
                    <li class="vm-category-sidebar-item">
                        <a href="{{ route('storefront') }}" class="vm-category-sidebar-link" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; color: #334155; font-size: 13.5px; font-weight: 500; text-decoration: none;">
                            <span class="vm-cat-item-content" style="display: flex; align-items: center; gap: 10px;">
                                <span class="vm-cat-item-icon-badge" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">
                                    ✦
                                </span>
                                <span class="vm-cat-item-text">Electronics</span>
                            </span>
                        </a>
                    </li>
                @endforelse
            </ul>

            <div style="border-top: 1px solid var(--vm-border); padding: 12px 18px; background: #F8FAFC;">
                <a href="#catalog" class="vm-cat-view-all-link" style="display: flex; align-items: center; justify-content: space-between; color: var(--vm-primary); font-size: 12.5px; font-weight: 700; text-decoration: none;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        <span>✦</span>
                        <span>Explore All Categories</span>
                    </span>
                    <span>→</span>
                </a>
            </div>
        </aside>

        <!-- Main Content Column (Desktop: Right column alongside Category Sidebar) -->
        <div class="vm-hero-main-column" style="flex-grow: 1; display: flex; flex-direction: column; gap: 14px;">

            <!-- Hero Banner Card -->
                        <!-- Dynamic Hero Banner Slider (Configurable in Admin Panel) -->
            <div class="vm-hero-slider-wrapper" style="position: relative;">
                @php
                    $activeHeroBanners = (isset($heroBanners) && $heroBanners->isNotEmpty()) 
                        ? $heroBanners 
                        : collect([
                            (object)[
                                'title' => 'Seamless Shopping, Swift Logistics',
                                'subtitle' => "Akwa Ibom's Premier Platform • Physical In-Shop Inspection • Escrow Protected",
                                'badge_text' => 'VERIFIED OMNICHANNEL MARKET',
                                'bg_gradient' => 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)',
                                'text_color' => '#0F172A',
                                'cta_text' => 'Shop Verified Items',
                                'cta_url' => '#catalog',
                                'image_path' => 'assets/img/vm_icon.jpg',
                            ]
                        ]);
                @endphp

                <div class="vm-hero-slides-track" id="vmHeroSliderTrack">
                    @foreach($activeHeroBanners as $index => $banner)
                        @include('storefront.partials._banner_renderer', ['banner' => $banner, 'index' => $index])
                    @endforeach
                </div>

                @if($activeHeroBanners->count() > 1)
                    <!-- Prev & Next Arrow Controls -->
                    <button type="button" onclick="vmPrevHeroSlide()" aria-label="Previous Slide" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(4px); border: 1px solid rgba(226, 232, 240, 0.9); color: #0F172A; font-size: 18px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 5; transition: all 0.15s ease;">
                        &#8249;
                    </button>
                    <button type="button" onclick="vmNextHeroSlide()" aria-label="Next Slide" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border-radius: 50%; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(4px); border: 1px solid rgba(226, 232, 240, 0.9); color: #0F172A; font-size: 18px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.12); z-index: 5; transition: all 0.15s ease;">
                        &#8250;
                    </button>

                    <!-- Slider Navigation Dots -->
                    <div style="display: flex; justify-content: center; gap: 6px; margin-top: 10px;">
                        @foreach($activeHeroBanners as $idx => $b)
                            <button type="button" class="vm-hero-dot {{ $idx === 0 ? 'active' : '' }}" 
                                    onclick="vmSwitchHeroSlide({{ $idx }})"
                                    style="width: {{ $idx === 0 ? '24px' : '8px' }}; height: 8px; border-radius: 999px; background: {{ $idx === 0 ? '#5E17EB' : '#CBD5E1' }}; border: none; padding: 0; cursor: pointer; transition: all 0.25s ease;"
                                    data-dot-index="{{ $idx }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Value Proposition Strip (4 Aligned Trust Pills) -->
            <div class="vm-val-prop-strip">
                
                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🛡️
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">100% Authentic Items</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Verified local merchants</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(217, 119, 6, 0.1); color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🏪
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">In-Shop Inspection</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Inspect physically before payment</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        ⚡
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Directional LGA Delivery</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Uyo, Eket & nationwide logistics</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #059669; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        💳
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Paystack Escrow</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Cards, transfers & cashbacks</span>
                    </div>
                </div>

            </div>

                        <!-- Omnichannel Proximity Status Bar -->
            <div class="vm-proximity-strip" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 10px 18px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--vm-shadow-xs);">
                <div class="vm-proximity-info" style="display: flex; align-items: center; gap: 10px; font-size: 13.5px;">
                    <span class="vm-proximity-icon" style="color: var(--vm-primary); font-size: 16px;">📍</span>
                    <div class="vm-proximity-text" style="display: flex; align-items: center; gap: 8px;">
                        <span class="vm-proximity-label" style="color: #64748B;">Your Location:</span>
                        <strong class="vm-proximity-highlight" id="vmProximityCityText" style="color: #0F172A; font-weight: 700;">{{ $activeCity ?? 'Uyo' }}, {{ $activeState ?? 'Akwa Ibom' }}</strong>
                        <span class="vm-proximity-dot" style="color: #CBD5E1;">•</span>
                        <span class="vm-proximity-mode-tag" id="vmProximityStatusTag" style="background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                            {{ $statusBadge ?? '⚡ Pickup & Delivery Active' }}
                        </span>
                    </div>
                </div>
                <button type="button" class="vm-proximity-switch-btn" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal(); else document.getElementById('vmLocationModal').classList.add('active');" style="background: #F8FAFC; border: 1px solid var(--vm-border); border-radius: 8px; padding: 6px 14px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                    <span>Change Location</span>
                    <span class="vm-caret">▾</span>
                </button>
            </div>

        </div>

    </div>

    <!-- 2. Amazon-Inspired Quad Discovery & Recommendation Grid -->
    <div class="vm-amazon-discovery-grid">

        <!-- Card 1: Verified Stores Closer to You in Uyo -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: var(--vm-primary);"></span>
                    <span>VERIFIED LOCAL HUBS</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Stores Closer to You in <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span></h3>
            </div>
            
            <div class="vm-discovery-quad-grid" id="vmShopsGridContainer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                @include('storefront.partials._shops_grid')
            </div>

            <div class="vm-discovery-card-footer">
                <a href="#merchants" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Explore Stores in {{ $activeCity ?? 'Uyo' }} →
                </a>
            </div>
        </div>

        <!-- Card 2: Shop Kitchen Must-Haves -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(217, 119, 6, 0.1); color: #D97706; margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: #D97706;"></span>
                    <span>KITCHEN & HOME</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Shop Kitchen Must-Haves</h3>
            </div>

            <div class="vm-discovery-quad-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <a href="{{ route('storefront', ['category' => 'home-appliances', 'search' => 'Blender']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v9.8"/><rect x="4" y="14" width="16" height="8" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Blenders & Grinders</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'home-appliances', 'search' => 'Microwave']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="15" y1="4" x2="15" y2="20"/><circle cx="18" cy="9" r="1"/><circle cx="18" cy="15" r="1"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Microwaves & Ovens</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'home-appliances', 'search' => 'Cooker']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="2"/><circle cx="16" cy="8" r="2"/><circle cx="8" cy="16" r="2"/><circle cx="16" cy="16" r="2"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Gas Cookers & Stoves</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'home-appliances', 'search' => 'Fryer']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Air Fryers & Fryers</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="{{ route('storefront', ['category' => 'home-appliances']) }}" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Explore Kitchen Appliances →
                </a>
            </div>
        </div>

        <!-- Card 3: Level Up Your Tech & PC -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(124, 58, 237, 0.1); color: #7C3AED; margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: #7C3AED;"></span>
                    <span>TECH & GADGETS</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Level Up Your Tech & PC</h3>
            </div>

            <div class="vm-discovery-quad-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <a href="{{ route('storefront', ['category' => 'electronics-gadgets', 'search' => 'Phone']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Smartphones & iPhones</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'electronics-gadgets', 'search' => 'Laptop']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Laptops & Computers</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'electronics-gadgets', 'search' => 'Audio']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Audio & Headphones</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'electronics-gadgets', 'search' => 'Power']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2"/><polygon points="13 7 9 13 12 13 11 17 15 11 12 11 13 7"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Power Banks & Gear</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="{{ route('storefront', ['category' => 'electronics-gadgets']) }}" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Explore All Tech Deals →
                </a>
            </div>
        </div>

        <!-- Card 4: Farm Fresh & Groceries -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(16, 185, 129, 0.1); color: #059669; margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: #059669;"></span>
                    <span>FRESH & GROCERIES</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Akwa Ibom Fresh Harvest</h3>
            </div>

            <div class="vm-discovery-quad-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Produce']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.06); display: flex; align-items: center; justify-content: center; color: #059669; font-size: 24px;">
                        🥬
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Fresh Produce</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Rice']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.06); display: flex; align-items: center; justify-content: center; color: #059669; font-size: 24px;">
                        🌾
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Grains & Staples</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Oil']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.06); display: flex; align-items: center; justify-content: center; color: #059669; font-size: 24px;">
                        🫒
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Cooking Oils</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Honey']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.06); display: flex; align-items: center; justify-content: center; color: #059669; font-size: 24px;">
                        🍯
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Honey & Natural</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce']) }}" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Discover Fresh Market →
                </a>
            </div>
        </div>

    </div>

    <!-- 3. Browse Popular Categories -->
    <div class="vm-section-block" id="categories" style="margin-bottom: 34px;">
        <div class="vm-section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
            <div style="border-left: 4px solid var(--vm-info); padding-left: 12px;">
                <h2 class="vm-section-title" style="font-size: 19px; font-weight: 800; color: #0F172A; margin: 0; line-height: 1.2;">Browse Popular Categories</h2>
                <p class="vm-section-desc" style="font-size: 13px; color: #64748B; margin-top: 3px;">Quality goods curated from certified merchants across Akwa Ibom.</p>
            </div>
            <a href="#categories" class="vm-section-link" style="font-size: 13px; font-weight: 700; color: var(--vm-info); text-decoration: none;">View All Categories →</a>
        </div>
        
        <div class="vm-categories-grid" style="display: flex; gap: 14px; flex-wrap: wrap;">
            @php
                $catIcons = [
                    'electronics-gadgets' => ['icon' => '📱', 'bg' => 'linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%)', 'color' => '#4F46E5'],
                    'home-appliances' => ['icon' => '🔌', 'bg' => 'linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%)', 'color' => '#D97706'],
                    'groceries-farm-produce' => ['icon' => '🌾', 'bg' => 'linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)', 'color' => '#059669'],
                    'fashion-apparel' => ['icon' => '👗', 'bg' => 'linear-gradient(135deg, #FDF2F8 0%, #FCE7F3 100%)', 'color' => '#DB2777'],
                    'health-beauty' => ['icon' => '💄', 'bg' => 'linear-gradient(135deg, #FAF5FF 0%, #F3E8FF 100%)', 'color' => '#9333EA'],
                    'automobile-parts' => ['icon' => '🚗', 'bg' => 'linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%)', 'color' => '#DC2626'],
                    'books-stationery' => ['icon' => '📚', 'bg' => 'linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%)', 'color' => '#0284C7'],
                    'solar-power' => ['icon' => '⚡', 'bg' => 'linear-gradient(135deg, #FEFCE8 0%, #FEF08A 100%)', 'color' => '#CA8A04'],
                ];
            @endphp
            @forelse($categories as $category)
                @php
                    $catTheme = $catIcons[$category->slug] ?? ['icon' => '🏷️', 'bg' => '#F1F5F9', 'color' => '#5E17EB'];
                @endphp
                <a href="{{ route('storefront', ['category' => $category->slug]) }}#catalog" class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: inline-flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; min-width: 105px; box-shadow: var(--vm-shadow-xs); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: {{ $catTheme['bg'] }}; border: 1px solid rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        {{ $catTheme['icon'] }}
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 700; color: #1E293B; text-align: center;">{{ $category->name }}</span>
                </a>
            @empty
                <div class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: inline-flex; flex-direction: column; align-items: center; gap: 8px; min-width: 105px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #EEF2FF; border: 1px solid #E0E7FF; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        📱
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 700; color: #1E293B;">Electronics</span>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 4. Top Verified Merchants -->
    <div class="vm-section-block" id="merchants" style="margin-bottom: 34px;">
        <div class="vm-section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
            <div style="border-left: 4px solid var(--vm-info); padding-left: 12px;">
                <h2 class="vm-section-title" style="font-size: 19px; font-weight: 800; color: #0F172A; margin: 0; line-height: 1.2;">Top Verified Merchants</h2>
                <p class="vm-section-desc" style="font-size: 13px; color: #64748B; margin-top: 3px;">Official retail stores offering physical counter inspection and rapid dispatch.</p>
            </div>
            <a href="#merchants" class="vm-section-link" style="font-size: 13px; font-weight: 700; color: var(--vm-info); text-decoration: none;">All Stores →</a>
        </div>

        <div class="vm-merchants-grid" id="vmShopsGridContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            @forelse($shops as $shop)
                <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-merchant-box" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px; display: flex; align-items: center; gap: 14px; box-shadow: var(--vm-shadow-xs); text-decoration: none; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                    <div style="width: 56px; height: 56px; border-radius: 10px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                        <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="vm-merchant-details" style="flex-grow: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                            <strong class="vm-merchant-name" style="font-size: 14px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $shop->name }}</strong>
                            <span style="font-size: 9.5px; background: #ECFDF5; color: #059669; font-weight: 800; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">VERIFIED</span>
                        </div>
                        <span class="vm-merchant-location" style="display: block; font-size: 12px; color: #64748B;">
                            📍 {{ $shop->lga->name ?? 'Uyo' }}, Akwa Ibom &bull; 🕒 {{ substr($shop->pickup_opening_time ?? '08:00', 0, 5) }} - {{ substr($shop->pickup_closing_time ?? '18:00', 0, 5) }}
                        </span>
                    </div>
                    <span style="color: var(--vm-primary); font-size: 18px; font-weight: 700; flex-shrink: 0;">&rsaquo;</span>
                </a>
            @empty
                <div class="vm-merchant-box" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px; display: flex; align-items: center; gap: 14px;">
                    <div style="width: 56px; height: 56px; border-radius: 10px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                        <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Emeka Electronics" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="vm-merchant-details">
                        <strong class="vm-merchant-name" style="display: block; font-size: 14px; font-weight: 700; color: #0F172A;">Emeka Electronics Plaza</strong>
                        <span class="vm-merchant-location" style="display: block; font-size: 12px; color: #64748B; margin-top: 2px;">
                            📍 Uyo, Akwa Ibom • In-Shop Ready
                        </span>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

            <!-- Dynamic Promotional Strip (Configurable via Admin Panel) -->
    @if(isset($promoBanners) && $promoBanners->isNotEmpty())
        @php $promo = $promoBanners->first(); @endphp
        <div class="vm-promo-strip-container" style="margin-bottom: 30px;">
            @include('storefront.partials._banner_renderer', ['banner' => $promo, 'index' => 0])
        </div>
    @endif

    <!-- 5. Latest Products in Market (Filtered Dynamically by Active Location) -->
    <div class="vm-section-block" id="catalog">
        
        <!-- Active Filter Indicator Banner (Gap 3 Fix) -->
        @if(request('category') || request('search'))
            <div style="background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 12px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px;">
                    <strong style="color: #0F172A; font-weight: 700;">Filtered Results:</strong>
                    @if(request('category'))
                        <span style="background: #EDE9FE; border: 1px solid #DDD6FE; color: #5E17EB; padding: 3px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <span>🏷️</span>
                            <span>Category: {{ ucwords(str_replace('-', ' ', request('category'))) }}</span>
                            <a href="{{ route('storefront', array_filter(['search' => request('search')])) }}#catalog" style="color: #5E17EB; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Remove category filter">&times;</a>
                        </span>
                    @endif
                    @if(request('search'))
                        <span style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; padding: 3px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <span>🔍</span>
                            <span>Keyword: "{{ request('search') }}"</span>
                            <a href="{{ route('storefront', array_filter(['category' => request('category')])) }}#catalog" style="color: #92400E; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Remove search filter">&times;</a>
                        </span>
                    @endif
                </div>
                <a href="{{ route('storefront') }}#catalog" style="font-size: 12.5px; font-weight: 700; color: #EF4444; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; background: #FEF2F2;">
                    <span>✕ Clear All Filters</span>
                </a>
            </div>
        @endif

        <div class="vm-section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
            <div style="border-left: 4px solid var(--vm-info); padding-left: 12px;">
                <h2 class="vm-section-title" style="font-size: 19px; font-weight: 800; color: #0F172A; margin: 0; line-height: 1.2;">
                    Latest Products in Market — Available in <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span>
                </h2>
                <p class="vm-section-desc" style="font-size: 13px; color: #64748B; margin-top: 3px;">
                    Fresh arrivals delivering to <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span> with verified 5% customer cashback reward.
                </p>
            </div>
            <a href="#catalog" class="vm-section-link" style="font-size: 13px; font-weight: 700; color: var(--vm-info); text-decoration: none;">View All →</a>
        </div>

        <div class="vm-products-grid" id="vmProductsGridContainer">
            @include('storefront.partials._products_grid')
        </div>
    </div>

</div>

@push('scripts')
<script>
    // Hero Slider Carousel Script (Gap 6 Enhanced)
    let currentHeroSlide = 0;
    let heroSliderTimer = null;
    const heroSlides = document.querySelectorAll('.vm-hero-slide-item');
    const heroDots = document.querySelectorAll('.vm-hero-dot');
    const heroTrack = document.getElementById('vmHeroSliderTrack');

    window.vmSwitchHeroSlide = function(index) {
        if (!heroSlides || heroSlides.length <= 1) return;
        currentHeroSlide = (index + heroSlides.length) % heroSlides.length;

        heroSlides.forEach((slide, idx) => {
            if (idx === currentHeroSlide) {
                slide.style.display = 'flex';
                slide.classList.add('active');
            } else {
                slide.style.display = 'none';
                slide.classList.remove('active');
            }
        });

        heroDots.forEach((dot, idx) => {
            if (idx === currentHeroSlide) {
                dot.style.width = '24px';
                dot.style.background = '#5E17EB';
            } else {
                dot.style.width = '8px';
                dot.style.background = '#CBD5E1';
            }
        });
    };

    window.vmNextHeroSlide = function() {
        window.vmSwitchHeroSlide(currentHeroSlide + 1);
    };

    window.vmPrevHeroSlide = function() {
        window.vmSwitchHeroSlide(currentHeroSlide - 1);
    };

    function startHeroSlider() {
        if (heroSlides && heroSlides.length > 1 && !heroSliderTimer) {
            heroSliderTimer = setInterval(() => {
                window.vmNextHeroSlide();
            }, 6000);
        }
    }

    function stopHeroSlider() {
        if (heroSliderTimer) {
            clearInterval(heroSliderTimer);
            heroSliderTimer = null;
        }
    }

    startHeroSlider();

    if (heroTrack) {
        heroTrack.addEventListener('mouseenter', stopHeroSlider);
        heroTrack.addEventListener('mouseleave', startHeroSlider);
    }
</script>
@endpush
@endsection
