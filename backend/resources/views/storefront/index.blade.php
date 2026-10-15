@extends('layouts.app')

@section('title', 'Victorious MARKET — Premier Omnichannel Marketplace in Akwa Ibom')

@section('content')
<div class="vm-container" style="margin-top: 18px; margin-bottom: 40px;">

    <!-- 1. Top Hero Section: Side Category Dropdown (Left) + Branded Hero Banner (Right) -->
    <div class="vm-hero-wrapper" style="display: flex; gap: 20px; margin-bottom: 24px;">
        
        <!-- Category Side Dropdown Navigation (Desktop Left) -->
        <aside class="vm-category-sidebar" style="width: 260px; flex-shrink: 0; background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--vm-shadow-xs);">
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
            <div class="vm-hero-slider-container" style="background: linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%); border: 1px solid rgba(94, 23, 235, 0.12); border-radius: 14px; padding: 34px 40px; box-shadow: var(--vm-shadow-xs); position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center;">
                <div style="max-width: 580px; position: relative; z-index: 2;">
                    
                    <!-- Verified Omnichannel Badge Pill -->
                    <div class="vm-hero-badge-pill" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 10px; border-radius: 999px; background: #FFFBE6; color: #D97706; margin-bottom: 14px;">
                        <span class="vm-badge-dot" style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                        <span>VERIFIED OMNICHANNEL MARKET</span>
                    </div>

                    <h2 class="vm-hero-title" style="font-size: 32px; font-weight: 800; color: #0F172A; line-height: 1.2; margin-bottom: 10px; letter-spacing: -0.5px;">
                        Seamless Shopping, Swift Logistics
                    </h2>

                    <p class="vm-hero-subtitle" style="font-size: 14.5px; color: #64748B; margin-bottom: 22px; line-height: 1.5;">
                        Akwa Ibom’s Premier Platform • Physical In-Shop Inspection • Escrow Protected
                    </p>

                    <div class="vm-hero-actions" style="display: flex; gap: 12px; align-items: center;">
                        <a href="#catalog" class="vm-hero-cta-btn" style="display: inline-flex; align-items: center; gap: 6px; background: var(--vm-gold); color: #0F172A; font-weight: 800; font-size: 14px; padding: 11px 22px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35); transition: all 0.2s ease;">
                            <span>Shop Verified Items</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Brand Emblem Pedestal -->
                <div style="display: flex; align-items: center; justify-content: center; position: relative; margin-left: 20px;">
                    <div style="width: 130px; height: 130px; border-radius: 28px; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 32px rgba(94, 23, 235, 0.16); border: 2px solid rgba(255, 215, 0, 0.45);">
                        <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 108px; height: 108px; border-radius: 22px; object-fit: cover;">
                    </div>
                </div>
            </div>

            <!-- Value Proposition Strip (4 Aligned Trust Pills) -->
            <div class="vm-val-prop-strip" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                
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
    <div class="vm-amazon-discovery-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px;">

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
                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 13.8V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v9.8"/><rect x="4" y="14" width="16" height="8" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Blenders & Grinders</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="15" y1="4" x2="15" y2="20"/><circle cx="18" cy="9" r="1"/><circle cx="18" cy="15" r="1"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Microwaves & Ovens</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="2"/><circle cx="16" cy="8" r="2"/><circle cx="8" cy="16" r="2"/><circle cx="16" cy="16" r="2"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Gas Cookers & Stoves</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(234, 88, 12, 0.06); display: flex; align-items: center; justify-content: center; color: #EA580C;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Air Fryers & Fryers</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="#catalog" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
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
                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Smartphones & iPhones</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Laptops & Computers</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Audio & Headphones</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(94, 23, 235, 0.06); display: flex; align-items: center; justify-content: center; color: var(--vm-primary);">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="2" width="12" height="20" rx="2"/><polygon points="13 7 9 13 12 13 11 17 15 11 12 11 13 7"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Power Banks & Gear</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="#catalog" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Explore All Tech Deals →
                </a>
            </div>
        </div>

        <!-- Card 4: Start Looking Sharp -->
        <div class="vm-discovery-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs);">
            <div class="vm-discovery-card-header" style="margin-bottom: 14px;">
                <div class="vm-discovery-badge-pill" style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: 999px; background: rgba(219, 39, 119, 0.1); color: #DB2777; margin-bottom: 6px;">
                    <span class="vm-badge-dot" style="width: 5px; height: 5px; border-radius: 50%; background: #DB2777;"></span>
                    <span>FASHION & BEAUTY</span>
                </div>
                <h3 class="vm-discovery-card-title" style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Start Looking Sharp</h3>
            </div>

            <div class="vm-discovery-quad-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(219, 39, 119, 0.06); display: flex; align-items: center; justify-content: center; color: #DB2777;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46L16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.5a2 2 0 0 0 1.54 1.63L6 11.2V20a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-8.8l1.6-.38a2 2 0 0 0 1.54-1.63l.58-3.5a2 2 0 0 0-1.34-2.23z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Men’s Clothing</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(219, 39, 119, 0.06); display: flex; align-items: center; justify-content: center; color: #DB2777;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l3 7-9 12L3 10l3-7z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Women’s Fashion</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(219, 39, 119, 0.06); display: flex; align-items: center; justify-content: center; color: #DB2777;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 17h20v2H2zM4 17l2-7h12l2 7z"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Shoes & Sneakers</span>
                </a>

                <a href="#catalog" class="vm-discovery-tile" style="text-decoration: none;">
                    <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: rgba(219, 39, 119, 0.06); display: flex; align-items: center; justify-content: center; color: #DB2777;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                    <span class="vm-discovery-tile-caption" style="display: block; font-size: 11px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Beauty & Skincare</span>
                </a>
            </div>

            <div class="vm-discovery-card-footer">
                <a href="#catalog" class="vm-discovery-card-link" style="font-size: 12.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                    Discover Trending Fashion →
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
            @forelse($categories as $category)
                <a href="{{ route('storefront', ['category' => $category->slug]) }}" class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 10px; padding: 16px 22px; display: inline-flex; flex-direction: column; align-items: center; gap: 10px; text-decoration: none; min-width: 110px; box-shadow: var(--vm-shadow-xs); transition: transform 0.2s ease;">
                    <div style="width: 46px; height: 46px; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center;">
                        <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $category->name }}" style="width: 28px; height: 28px; opacity: 0.5;">
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 600; color: #334155;">{{ $category->name }}</span>
                </a>
            @empty
                <div class="vm-category-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 10px; padding: 16px 22px; display: inline-flex; flex-direction: column; align-items: center; gap: 10px; min-width: 110px;">
                    <div style="width: 46px; height: 46px; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center;">
                        <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="Electronics" style="width: 28px; height: 28px; opacity: 0.5;">
                    </div>
                    <span class="vm-category-name" style="font-size: 12.5px; font-weight: 600; color: #334155;">Electronics</span>
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

        <div class="vm-merchants-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
            @forelse($shops as $shop)
                <div class="vm-merchant-box" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px; display: flex; align-items: center; gap: 14px; box-shadow: var(--vm-shadow-xs);">
                    <div style="width: 56px; height: 56px; border-radius: 10px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                        <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="vm-merchant-details">
                        <strong class="vm-merchant-name" style="display: block; font-size: 14px; font-weight: 700; color: #0F172A;">{{ $shop->name }}</strong>
                        <span class="vm-merchant-location" style="display: block; font-size: 12px; color: #64748B; margin-top: 2px;">
                            📍 {{ $shop->lga->name ?? 'Uyo' }}, Akwa Ibom • In-Shop Ready
                        </span>
                    </div>
                </div>
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

        <!-- 5. Latest Products in Market (Filtered Dynamically by Active Location) -->
    <div class="vm-section-block" id="catalog">
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

        <div class="vm-products-grid" id="vmProductsGridContainer" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; transition: opacity 0.2s ease;">
            @include('storefront.partials._products_grid')
        </div>
    </div>

</div>

@push('scripts')
<script>
    // Client-side cart manager
    let cart = [];

    document.querySelectorAll('.vm-add-cart-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);

            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.qty += 1;
            } else {
                cart.push({ id, name, price, qty: 1 });
            }

            renderCart();
            // Open cart drawer
            document.getElementById('vmCartDrawer').classList.add('active');
            document.getElementById('vmCartOverlay').classList.add('active');
        });
    });

    function renderCart() {
        const count = cart.reduce((sum, item) => sum + item.qty, 0);
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const cashback = subtotal * 0.05;

        document.getElementById('vmHeaderCartBadge').textContent = count;
        document.getElementById('vmCartBadgeCount').textContent = count + ' items';
        document.getElementById('vmCartSubtotal').textContent = '₦' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2 });

        const container = document.getElementById('vmCartItemsContainer');
        if (cart.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px 10px; color: #94A3B8;">
                    <span style="font-size: 48px; display: block; margin-bottom: 12px;">🛍️</span>
                    <strong style="color: #334155; font-size: 16px; display: block; margin-bottom: 6px;">Your cart is empty</strong>
                    <p style="font-size: 13px;">Explore verified items with 5% cashback reward</p>
                </div>
            `;
        } else {
            container.innerHTML = cart.map(item => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #F1F5F9;">
                    <div style="flex-grow: 1; padding-right: 10px;">
                        <strong style="font-size: 13px; color: #0F172A; display: block;">${item.name}</strong>
                        <span style="font-size: 12px; color: var(--vm-info); font-weight: 700;">₦${item.price.toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; font-weight: 600; color: #475569;">x${item.qty}</span>
                        <button type="button" onclick="removeItem('${item.id}')" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 14px;">🗑️</button>
                    </div>
                </div>
            `).join('');
        }
    }

    window.removeItem = function (id) {
        cart = cart.filter(item => item.id !== id);
        renderCart();
    };
</script>
@endpush
@endsection
