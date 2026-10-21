@extends('layouts.app')

@section('title', 'Victorious MARKET — Premier Omnichannel Marketplace in Akwa Ibom')

@section('content')
<div class="vm-container" style="margin-top: 18px; margin-bottom: 40px;">

        <!-- Mobile Horizontal Category Swipe Track (Thumb-Friendly on Phones) -->
    <div class="vm-mobile-category-track">
        <a href="{{ route('storefront') }}" class="vm-mob-cat-chip {{ !request('category') ? 'active' : '' }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <span>All Items</span>
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
                                <span class="vm-cat-item-icon-badge" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: inline-flex; align-items: center; justify-content: center;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>
                                </span>
                                <span class="vm-cat-item-text">{{ $cat->name }}</span>
                            </span>
                            <span class="vm-cat-chevron" style="color: #94A3B8; font-size: 14px;">&rsaquo;</span>
                        </a>
                    </li>
                @empty
                    <li class="vm-category-sidebar-item">
                        <a href="{{ route('storefront') }}" class="vm-category-sidebar-link" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; color: #334155; font-size: 13.5px; font-weight: 500; text-decoration: none;">
                            <span class="vm-cat-item-content" style="display: flex; align-items: center; gap: 10px;">
                                <span class="vm-cat-item-icon-badge" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: inline-flex; align-items: center; justify-content: center;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>
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
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Explore All Categories</span>
                    </span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
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
                    <div class="vm-val-prop-icon-wrap" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">100% Authentic Items</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Verified local merchants</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(217, 119, 6, 0.1); color: #D97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">In-Shop Inspection</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Inspect physically before payment</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <div class="vm-val-prop-text">
                        <strong class="vm-val-prop-title" style="display: block; font-size: 13px; font-weight: 700; color: #0F172A;">Directional LGA Delivery</strong>
                        <span class="vm-val-prop-desc" style="display: block; font-size: 11px; color: #64748B;">Uyo, Eket & nationwide logistics</span>
                    </div>
                </div>

                <div class="vm-val-prop-item" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; box-shadow: var(--vm-shadow-xs);">
                    <div class="vm-val-prop-icon-wrap" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
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
                    <span class="vm-proximity-icon" style="color: var(--vm-primary); display: inline-flex; align-items: center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <div class="vm-proximity-text" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="vm-proximity-label" style="color: #64748B;">Your Location:</span>
                        <strong class="vm-proximity-highlight" id="vmProximityCityText" style="color: #0F172A; font-weight: 700;">{{ $activeCity ?? 'Uyo' }}, {{ $activeState ?? 'Akwa Ibom' }}</strong>
                        <span class="vm-proximity-dot" style="color: #CBD5E1;">•</span>
                        <span class="vm-proximity-mode-tag" id="vmProximityStatusTag" style="background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--vm-primary);"></span>
                            <span>{{ $statusBadge ?? 'Pickup & Delivery Active' }}</span>
                        </span>
                    </div>
                </div>
                <button type="button" class="vm-proximity-switch-btn" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal(); else document.getElementById('vmLocationModal').classList.add('active');" style="background: #F8FAFC; border: 1px solid var(--vm-border); border-radius: 8px; padding: 6px 14px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;">
                    <span>Change Location</span>
                    <span class="vm-caret">&#9662;</span>
                </button>
            </div>

        </div>

    </div>

    <!-- 2. Amazon-Inspired Quad Discovery & Recommendation Grid -->
    <div class="vm-amazon-discovery-grid">

        <!-- Card 1: Victorious MARKET Uyo Fulfillment -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: var(--vm-primary);"></span>
                    <span>VERIFIED LOCAL HUBS</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Victorious MARKET in <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span></h3>
            </div>
            
            <div class="vm-discovery-quad-grid" id="vmShopsGridContainer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                @include('storefront.partials._shops_grid')
            </div>

            <div class="vm-discovery-card-footer">
                <a href="#merchants" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Browse Products →
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
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.08); display: flex; align-items: center; justify-content: center; color: #059669;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Fresh Produce</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Rice']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.08); display: flex; align-items: center; justify-content: center; color: #059669;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v20"/><path d="M18 2v20"/><path d="M6 12h12"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Grains &amp; Staples</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Oil']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.08); display: flex; align-items: center; justify-content: center; color: #059669;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Cooking Oils</span>
                </a>

                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce', 'search' => 'Honey']) }}" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(16, 185, 129, 0.08); display: flex; align-items: center; justify-content: center; color: #059669;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Honey &amp; Natural</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="{{ route('storefront', ['category' => 'groceries-farm-produce']) }}" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Discover Fresh Market &rarr;
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
            <a href="#categories" class="vm-section-link" style="font-size: 13px; font-weight: 700; color: var(--vm-info); text-decoration: none;">View All Categories &rarr;</a>
        </div>
        
        <div class="vm-categories-grid" style="display: flex; gap: 14px; flex-wrap: wrap;">
            @php
                $catThemes = [
                    'electronics-gadgets' => ['bg' => 'linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%)', 'color' => '#4F46E5', 'icon' => 'device'],
                    'home-appliances' => ['bg' => 'linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%)', 'color' => '#D97706', 'icon' => 'home'],
                    'groceries-farm-produce' => ['bg' => 'linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)', 'color' => '#059669', 'icon' => 'plant'],
                    'fashion-apparel' => ['bg' => 'linear-gradient(135deg, #FDF2F8 0%, #FCE7F3 100%)', 'color' => '#DB2777', 'icon' => 'fashion'],
                    'health-beauty' => ['bg' => 'linear-gradient(135deg, #FAF5FF 0%, #F3E8FF 100%)', 'color' => '#9333EA', 'icon' => 'sparkles'],
                    'automobile-parts' => ['bg' => 'linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%)', 'color' => '#DC2626', 'icon' => 'truck'],
                    'books-stationery' => ['bg' => 'linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%)', 'color' => '#0284C7', 'icon' => 'book'],
                    'solar-power' => ['bg' => 'linear-gradient(135deg, #FEFCE8 0%, #FEF08A 100%)', 'color' => '#CA8A04', 'icon' => 'sun'],
                ];
            @endphp
            @forelse($categories as $category)
                @php
                    $theme = $catThemes[$category->slug] ?? ['bg' => '#F1F5F9', 'color' => '#5E17EB', 'icon' => 'tag'];
                @endphp
                <a href="{{ route('storefront', ['category' => $category->slug]) }}#catalog" class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: inline-flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; min-width: 105px; box-shadow: var(--vm-shadow-xs); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: {{ $theme['bg'] }}; color: {{ $theme['color'] }}; border: 1px solid rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center;">
                        @if($theme['icon'] === 'device')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        @elseif($theme['icon'] === 'home')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="2"/><circle cx="16" cy="8" r="2"/></svg>
                        @elseif($theme['icon'] === 'plant')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                        @elseif($theme['icon'] === 'fashion')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46L16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>
                        @elseif($theme['icon'] === 'sparkles')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        @elseif($theme['icon'] === 'truck')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        @elseif($theme['icon'] === 'book')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        @elseif($theme['icon'] === 'sun')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        @else
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        @endif
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 700; color: #1E293B; text-align: center;">{{ $category->name }}</span>
                </a>
            @empty
                <div class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px 18px; display: inline-flex; flex-direction: column; align-items: center; gap: 8px; min-width: 105px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: #EEF2FF; color: #4F46E5; border: 1px solid #E0E7FF; display: flex; align-items: center; justify-content: center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 700; color: #1E293B;">Electronics</span>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 4. Victorious MARKET — Local Fulfillment -->
    <div class="vm-section-block" id="merchants" style="margin-bottom: 34px;">
        <div class="vm-section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
            <div style="border-left: 4px solid var(--vm-info); padding-left: 12px;">
                <h2 class="vm-section-title" style="font-size: 19px; font-weight: 800; color: #0F172A; margin: 0; line-height: 1.2;">Top Verified Merchants</h2>
                <p class="vm-section-desc" style="font-size: 13px; color: #64748B; margin-top: 3px;">One trusted local retailer sourcing from approved Uyo suppliers.</p>
            </div>
            <a href="#merchants" class="vm-section-link" style="font-size: 13px; font-weight: 700; color: var(--vm-info); text-decoration: none;">Browse Products &rarr;</a>
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
                        <span class="vm-merchant-location" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #64748B; flex-wrap: wrap;">
                            <span style="display: inline-flex; align-items: center; gap: 3px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span>{{ $shop->lga->name ?? 'Uyo' }}</span>
                            </span>
                            <span style="opacity: 0.4;">•</span>
                            <span style="display: inline-flex; align-items: center; gap: 3px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span>{{ substr($shop->pickup_opening_time ?? '08:00', 0, 5) }} - {{ substr($shop->pickup_closing_time ?? '18:00', 0, 5) }}</span>
                            </span>
                        </span>
                    </div>
                    <span style="color: var(--vm-primary); font-size: 18px; font-weight: 700; flex-shrink: 0;">&rsaquo;</span>
                </a>
            @empty
                <div class="vm-merchant-box" style="grid-column: 1 / -1; background: #FFFFFF; border: 1.5px dashed var(--vm-border); border-radius: 12px; padding: 24px; text-align: center;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    </div>
                    <strong style="display: block; font-size: 14.5px; font-weight: 700; color: #0F172A; margin-bottom: 4px;">Victorious MARKET — One Trusted Local Retailer</strong>
                    <span style="display: block; font-size: 12.5px; color: #64748B; max-width: 460px; margin: 0 auto 12px; line-height: 1.4;">
                        All products are sold by Victorious MARKET. Supplier locations remain private; any pickup exception must be arranged through central support.
                    </span>
                    <a href="{{ route('storefront') }}#catalog" style="background: #F8FAFC; border: 1px solid #CBD5E1; color: #334155; font-size: 11.5px; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Browse Uyo Products</a>
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
        
        <!-- Active Filter Indicator Banner -->
        @if(request('category') || request('search') || request('condition') || (request('scope') && request('scope') === 'all'))
            <div style="background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 12px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px;">
                    <strong style="color: #0F172A; font-weight: 700;">Filtered Results:</strong>
                    @if(request('scope') === 'all')
                        <span style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span>Scope: All Delivering (Local + Transit)</span>
                            <a href="{{ route('storefront', array_filter(['category' => request('category'), 'search' => request('search'), 'condition' => request('condition')])) }}#catalog" style="color: #1D4ED8; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Reset to local shops only">&times;</a>
                        </span>
                    @endif
                    @if(request('condition'))
                        <span style="background: {{ request('condition') === 'uk_used' ? '#FEF3C7' : '#ECFDF5' }}; border: 1px solid {{ request('condition') === 'uk_used' ? '#FDE68A' : '#A7F3D0' }}; color: {{ request('condition') === 'uk_used' ? '#92400E' : '#065F46' }}; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            @if(request('condition') === 'uk_used')
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            @else
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            @endif
                            <span>Condition: {{ request('condition') === 'uk_used' ? 'UK Used' : 'Brand New' }}</span>
                            <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'category' => request('category'), 'search' => request('search')])) }}#catalog" style="color: inherit; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Remove condition filter">&times;</a>
                        </span>
                    @endif
                    @if(request('category'))
                        <span style="background: #EDE9FE; border: 1px solid #DDD6FE; color: #5E17EB; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <span>Category: {{ ucwords(str_replace('-', ' ', request('category'))) }}</span>
                            <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'search' => request('search'), 'condition' => request('condition')])) }}#catalog" style="color: #5E17EB; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Remove category filter">&times;</a>
                        </span>
                    @endif
                    @if(request('search'))
                        <span style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <span>Keyword: "{{ request('search') }}"</span>
                            <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'category' => request('category'), 'condition' => request('condition')])) }}#catalog" style="color: #92400E; text-decoration: none; font-weight: 900; margin-left: 2px; font-size: 14px;" title="Remove search filter">&times;</a>
                        </span>
                    @endif
                </div>
                <a href="{{ route('storefront') }}#catalog" style="font-size: 12.5px; font-weight: 700; color: #EF4444; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; background: #FEF2F2;">
                    <span>Clear All Filters</span>
                </a>
            </div>
        @endif

        <!-- Dynamic Filter & Scope Pills -->
        <div class="vm-catalog-filter-bar" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #E2E8F0;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <!-- Scope: Local Shops vs All Delivering -->
                <a href="{{ route('storefront', array_filter(['scope' => 'local', 'category' => request('category'), 'search' => request('search'), 'condition' => request('condition')])) }}#catalog" 
                   class="vm-filter-pill {{ ($scope ?? 'local') === 'local' ? 'active' : '' }}"
                   style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;
                   {{ ($scope ?? 'local') === 'local' ? 'background: #5E17EB; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;' }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <span>In <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span> Shops</span>
                    @if(isset($localProductsCount))
                        <span style="font-size: 10px; opacity: 0.9; background: {{ ($scope ?? 'local') === 'local' ? 'rgba(255,255,255,0.25)' : '#E2E8F0' }}; padding: 1px 6px; border-radius: 999px;">{{ $localProductsCount }}</span>
                    @endif
                </a>

                <a href="{{ route('storefront', array_filter(['scope' => 'all', 'category' => request('category'), 'search' => request('search'), 'condition' => request('condition')])) }}#catalog" 
                   class="vm-filter-pill {{ ($scope ?? '') === 'all' ? 'active' : '' }}"
                   style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s ease;
                   {{ ($scope ?? '') === 'all' ? 'background: #5E17EB; color: #FFFFFF;' : 'background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;' }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>All Delivering to <span class="vm-dynamic-city-label">{{ $activeCity ?? 'Uyo' }}</span></span>
                    @if(isset($localProductsCount) && isset($corridorProductsCount))
                        <span style="font-size: 10px; opacity: 0.9; background: {{ ($scope ?? '') === 'all' ? 'rgba(255,255,255,0.25)' : '#E2E8F0' }}; padding: 1px 6px; border-radius: 999px;">{{ $localProductsCount + $corridorProductsCount }}</span>
                    @endif
                </a>

                <span style="color: #CBD5E1; font-weight: 300;">|</span>

                <!-- Condition Filter Pills -->
                <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'category' => request('category'), 'search' => request('search'), 'condition' => request('condition') === 'brand_new' ? null : 'brand_new'])) }}#catalog" 
                   class="vm-filter-pill"
                   style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s ease;
                   {{ request('condition') === 'brand_new' ? 'background: #059669; color: #FFFFFF;' : 'background: #F8FAFC; color: #334155; border: 1px solid #E2E8F0;' }}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span>Brand New</span>
                    @if(request('condition') === 'brand_new')
                        <span style="font-size: 11px; margin-left: 2px;">✕</span>
                    @endif
                </a>

                <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'category' => request('category'), 'search' => request('search'), 'condition' => request('condition') === 'uk_used' ? null : 'uk_used'])) }}#catalog" 
                   class="vm-filter-pill"
                   style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s ease;
                   {{ request('condition') === 'uk_used' ? 'background: #D97706; color: #FFFFFF;' : 'background: #F8FAFC; color: #334155; border: 1px solid #E2E8F0;' }}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>UK Used</span>
                    @if(request('condition') === 'uk_used')
                        <span style="font-size: 11px; margin-left: 2px;">✕</span>
                    @endif
                </a>
            </div>

            <div style="font-size: 12px; color: #64748B;">
                <span>Verified Stock</span> • <span style="color: #059669; font-weight: 700;">+5% Cashback</span>
            </div>
        </div>

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
