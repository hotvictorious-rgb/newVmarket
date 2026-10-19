@extends('layouts.dashboard')

@section('title', 'Products & Logistics Catalog — Merchant')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Products & Logistics Catalog')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Products &amp; Logistics</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏷️</span>
                <span>Fast Product Catalog &amp; Logistics Desk</span>
            </h1>
            <p class="page-header-subtitle">
                List products in 30 seconds. Choose between <strong>Brand New</strong> or <strong>UK Used</strong>, and assign <strong>Bike (Normal Fee)</strong> or <strong>Keke (Cargo Surcharge)</strong> logistics.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-dash btn-dash-primary" onclick="openNewProductModal();" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                <span style="font-size: 16px;">+</span> Add New Product
            </button>
        </div>
    </div>

    <!-- Alert / Notice -->
    @if(session('status') || session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('status') ?? session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px;">
            <div style="font-weight: 700; margin-bottom: 4px;">⚠️ Please resolve the following listing issues:</div>
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Catalog KPI Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="dash-card" style="padding: 18px; margin-bottom: 0;">
            <div style="font-size: 12px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Listed Items</div>
            <div style="font-size: 26px; font-weight: 800; color: var(--adm-text); margin-top: 4px;">{{ count($products) }}</div>
            <div style="font-size: 12px; color: #10B981; margin-top: 2px;">⚡ Certified Marketplace Active</div>
        </div>
        <div class="dash-card" style="padding: 18px; margin-bottom: 0;">
            <div style="font-size: 12px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Condition Breakdown</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--adm-text); margin-top: 8px; display: flex; gap: 12px;">
                <span style="color: #059669;">✨ {{ $products->where('condition', 'brand_new')->count() }} Brand New</span>
                <span style="color: #4F46E5;">🇬🇧 {{ $products->where('condition', 'uk_used')->count() }} UK Used</span>
            </div>
            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">Nigerian Standard Certified</div>
        </div>
        <div class="dash-card" style="padding: 18px; margin-bottom: 0;">
            <div style="font-size: 12px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Logistics Fleet Tiers</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--adm-text); margin-top: 8px; display: flex; gap: 12px;">
                <span style="color: #0284C7;">🏍️ {{ $products->whereIn('package_tier', ['bike', 'standard'])->count() }} Bike</span>
                <span style="color: #D97706;">🛺 {{ $products->whereIn('package_tier', ['keke', 'bulky'])->count() }} Keke Cargo</span>
            </div>
            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">Keke triggers bulky cargo surcharge</div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Live Inventory &amp; Catalog Management</div>
                <span class="dash-card-subtitle">Active merchant products synchronized with customer apps and Uyo/Akwa Ibom delivery hubs</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product &amp; Category</th>
                        <th>Condition &amp; Warranty</th>
                        <th>Logistics Tier</th>
                        <th>Price &amp; Stock</th>
                        <th>Freshness Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $daysOld = $product->listing_confirmed_at ? \Carbon\Carbon::parse($product->listing_confirmed_at)->diffInDays(now()) : 0;
                            $isFresh = $product->isFresh();
                            $isKeke = in_array($product->package_tier, ['keke', 'bulky']);
                        @endphp
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    @if($product->thumbnail)
                                        <img src="{{ asset($product->thumbnail) }}" alt="{{ $product->name }}" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--adm-border);">
                                    @else
                                        <div style="width: 44px; height: 44px; border-radius: 8px; background: #F3F4F6; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                            📦
                                        </div>
                                    @endif
                                    <div>
                                        <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $product->name }}</div>
                                        <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                            <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $product->category->name ?? 'General' }}</span>
                                            @if($product->youtube_video_id)
                                                <span style="font-size: 11px; background: #FEE2E2; color: #DC2626; padding: 1px 6px; border-radius: 4px; font-weight: 700;">▶ Video Review</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($product->condition === 'uk_used')
                                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #EEF2FF; color: #4338CA; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;">
                                        🇬🇧 UK Used
                                    </span>
                                @else
                                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #ECFDF5; color: #047857; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;">
                                        ✨ Brand New
                                    </span>
                                @endif
                                <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 4px;">
                                    🛡️ {{ $product->warranty_label }}
                                </div>
                            </td>
                            <td>
                                @if($isKeke)
                                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #FEF3C7; color: #B45309; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;">
                                        🛺 Keke Cargo (+Surcharge)
                                    </span>
                                    <div style="font-size: 11px; color: #B45309; margin-top: 2px;">Heavy / Bulky cargo</div>
                                @else
                                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #E0F2FE; color: #0369A1; padding: 3px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;">
                                        🏍️ Bike Dispatch (Normal)
                                    </span>
                                    <div style="font-size: 11px; color: var(--adm-text-muted); margin-top: 2px;">Standard motorcycle box</div>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: baseline; gap: 6px;">
                                    <span style="font-weight: 800; color: #059669; font-size: 14.5px;">₦{{ number_format((float)$product->unit_price, 2) }}</span>
                                    @if($product->slash_price && (float)$product->slash_price > (float)$product->unit_price)
                                        <span style="font-size: 12px; color: var(--adm-text-muted); text-decoration: line-through;">₦{{ number_format((float)$product->slash_price, 2) }}</span>
                                    @endif
                                </div>
                                <div style="margin-top: 4px;">
                                    @if($product->is_in_stock)
                                        <span class="dash-badge dash-badge-success" style="display:inline-flex; align-items:center; gap:4px; font-weight:700; padding:2px 7px; font-size:11px;">
                                            🟢 In Stock
                                        </span>
                                    @else
                                        <span class="dash-badge" style="background:#FEE2E2; color:#DC2626; border:1px solid #FCA5A5; display:inline-flex; align-items:center; gap:4px; font-weight:700; padding:2px 7px; font-size:11px;">
                                            🔴 Out of Stock
                                        </span>
                                    @endif
                                    @if($product->shop)
                                        <span style="font-size: 11px; color: var(--adm-text-muted);">({{ $product->shop->name }})</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($isFresh)
                                    <span class="dash-badge dash-badge-success">⚡ FRESH (Day {{ $daysOld }}/7)</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">⚠️ EXPIRED (Day {{ $daysOld }})</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <form method="POST" action="{{ route('vendor.products.toggle-stock', $product->id) }}" style="display:inline;">
                                        @csrf
                                        @if($product->is_in_stock)
                                            <button type="submit" class="btn-dash btn-dash-outline" style="padding: 4px 8px; font-size: 11px; color: #DC2626; border-color: #FCA5A5;" title="Click to mark product unavailable for purchase">
                                                🔴 Mark Out of Stock
                                            </button>
                                        @else
                                            <button type="submit" class="btn-dash btn-dash-outline" style="padding: 4px 8px; font-size: 11px; color: #059669; border-color: #A7F3D0;" title="Click to confirm product is available for purchase">
                                                🟢 Mark In Stock
                                            </button>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('vendor.products.refresh', $product->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-outline" style="padding: 4px 8px; font-size: 11px;" title="Reset 7-day freshness clock">
                                            🔄 Refresh
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">🛍️</div>
                                <div style="font-weight: 700; font-size: 15px; color: var(--adm-text);">No products listed in your catalog yet</div>
                                <p style="font-size: 13px; max-width: 400px; margin: 4px auto 16px;">
                                    Start selling in seconds. List your Brand New or UK Used stock and assign bike or keke delivery.
                                </p>
                                <button type="button" class="btn-dash btn-dash-primary" onclick="openNewProductModal();">
                                    + Add First Product
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('vendor.partials._product_studio_modal')
    @include('vendor.partials._product_studio_styles')
    @include('vendor.partials._product_studio_scripts')
@endsection