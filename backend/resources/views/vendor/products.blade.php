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
                                <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">
                                    Stock: <strong>{{ $product->inventory_quantity }}</strong> units
                                    @if($product->shop)
                                        ({{ $product->shop->name }})
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
                                <form method="POST" action="{{ route('vendor.products.refresh', $product->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-dash btn-dash-outline" style="padding: 4px 10px; font-size: 11.5px;">
                                        🔄 Re-verify Freshness
                                    </button>
                                </form>
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

    <!-- Facebook Marketplace-Style Listing Studio Modal -->
    <div id="vmNewProductModal" class="fb-modal-backdrop" style="display: none;">
        <div class="fb-studio-container">
            <!-- Studio Top Bar -->
            <div class="fb-studio-topbar">
                <div class="fb-topbar-left">
                    <div class="fb-market-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="fb-topbar-title">Marketplace Listing Studio</div>
                        <div class="fb-topbar-sub">Facebook Marketplace Experience • 7-Day Certified Freshness</div>
                    </div>
                </div>

                <!-- Mobile / Tablet Segmented Controller -->
                <div class="fb-mobile-tabs" id="fbMobileTabs">
                    <button type="button" class="fb-tab-btn active" id="tabBtnForm" onclick="switchStudioTab('form')">
                        ✏️ Details
                    </button>
                    <button type="button" class="fb-tab-btn" id="tabBtnPreview" onclick="switchStudioTab('preview')">
                        👁️ Live Preview <span id="fbMobilePreviewBadge" class="fb-badge-dot"></span>
                    </button>
                </div>

                <div class="fb-topbar-right">
                    <button type="button" class="fb-close-btn" onclick="closeNewProductModal();" title="Close Studio (Esc)">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Studio Body: Split Screen on Desktop, Tabbed on Mobile -->
            <div class="fb-studio-body">
                <!-- LEFT COLUMN: Form Studio -->
                <div class="fb-col-form" id="fbColForm">
                    <!-- Merchant Profile Pill -->
                    <div class="fb-merchant-pill">
                        <div class="fb-merchant-avatar">
                            {{ substr($vendor->business_name ?? Auth::user()->name ?? 'V', 0, 1) }}
                        </div>
                        <div class="fb-merchant-meta">
                            <div class="fb-merchant-name">
                                {{ $vendor->business_name ?? Auth::user()->name ?? 'Merchant Store' }}
                                <span class="fb-verified-check" title="Verified Merchant">✓</span>
                            </div>
                            <div class="fb-merchant-scope">
                                <span>🌐 Public Listing</span> • <span>Marketplace &amp; Shop Feed</span>
                            </div>
                        </div>
                    </div>

                    <form id="fbProductForm" method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data" novalidate>
                        @csrf

                        <!-- PHOTO UPLOAD STUDIO (COMPULSORY + PREVIEW) -->
                        <div class="fb-section-card">
                            <div class="fb-section-header">
                                <div>
                                    <h4 class="fb-section-title">
                                        Photos
                                        <span class="fb-badge-compulsory">* COMPULSORY</span>
                                    </h4>
                                    <p class="fb-section-hint">
                                        Add at least 1 real photo. The 1st photo will be your Marketplace cover photo. (Up to 10 photos)
                                    </p>
                                </div>
                                <div class="fb-photo-count" id="fbPhotoCount">0 / 10</div>
                            </div>

                            <!-- Photo Dropzone -->
                            <div class="fb-dropzone" id="fbDropzone" onclick="triggerPhotoSelect();">
                                <input type="file" id="fbFileInput" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;" onchange="handlePhotosSelected(this.files);">
                                <input type="file" id="fbFileInputFallback" name="image" accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;">
                                
                                <div class="fb-dropzone-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1877F2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                </div>
                                <div class="fb-dropzone-text">+ Add Photos</div>
                                <div class="fb-dropzone-sub">Click to browse or drag and drop photos here</div>
                                <div class="fb-dropzone-limits">JPEG, PNG, JPG, WEBP • Max 5MB each</div>
                            </div>

                            <!-- Inline Compulsory Warning -->
                            <div id="fbPhotoError" class="fb-inline-error" style="display: none;">
                                ⚠️ <strong>Photo is required:</strong> Please select at least 1 real photo of your item to publish.
                            </div>

                            <!-- Dynamic Photo Gallery Thumbnails -->
                            <div id="fbThumbnailGallery" class="fb-thumbnail-gallery" style="display: none;"></div>
                        </div>

                        <!-- ITEM DETAILS -->
                        <div class="fb-section-card">
                            <h4 class="fb-section-title" style="margin-bottom: 14px;">Item Details</h4>

                            <!-- Product Name / Title -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inProdName">
                                    Title <span class="fb-req">*</span>
                                </label>
                                <input type="text" name="name" id="inProdName" required maxlength="255" class="fb-input" placeholder="e.g. iPhone 13 128GB Midnight Blue or LG 2HP Inverter AC" oninput="syncLivePreview();">
                                <div class="fb-field-hint">Include brand, model, key specifications, and color for buyers.</div>
                            </div>

                            <!-- PRICING & PRICE SLASH ARCHITECTURE -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inUnitPrice">
                                        Selling Price (₦) <span class="fb-req">*</span>
                                    </label>
                                    <input type="number" step="0.01" min="0.01" max="100000000" name="unit_price" id="inUnitPrice" required class="fb-input" placeholder="e.g. 45000" oninput="validatePricing(); syncLivePreview();">
                                    <div class="fb-field-hint">What the buyer pays today.</div>
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inSlashPrice">
                                        Original Price (₦)
                                        <span class="fb-opt">(Was-Price)</span>
                                    </label>
                                    <input type="number" step="0.01" min="0.01" max="100000000" name="slash_price" id="inSlashPrice" class="fb-input" placeholder="e.g. 60000" oninput="validatePricing(); syncLivePreview();">
                                    <div class="fb-field-hint">Regular price before discount.</div>
                                </div>
                            </div>

                            <!-- Real-time Price Slash Guard Feedback -->
                            <div id="fbSlashAlert" class="fb-slash-alert" style="display: none;"></div>

                            <!-- Category & Condition -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inCategory">
                                        Category <span class="fb-req">*</span>
                                    </label>
                                    <select name="category_id" id="inCategory" required class="fb-select" onchange="syncLivePreview();">
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label">
                                        Plaza Outlet / Branch
                                    </label>
                                    <select name="shop_id" id="inShop" class="fb-select" onchange="syncLivePreview();">
                                        @if(isset($shops) && count($shops) > 0)
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endforeach
                                        @else
                                            <option value="">Primary Shop Branch</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <!-- Condition Selector (Brand New vs UK Used) -->
                            <div class="fb-field-group">
                                <label class="fb-field-label">
                                    Condition <span class="fb-req">*</span>
                                </label>
                                <div class="fb-radio-grid">
                                    <label id="cardConditionNew" class="fb-radio-card active">
                                        <input type="radio" name="condition" value="brand_new" checked onchange="setCondition('brand_new');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #065F46;">✨ Brand New</div>
                                            <div class="fb-radio-desc">Sealed / Factory Box • 0 Hours</div>
                                        </div>
                                    </label>
                                    <label id="cardConditionUsed" class="fb-radio-card">
                                        <input type="radio" name="condition" value="uk_used" onchange="setCondition('uk_used');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #374151;">🇬🇧 UK Used</div>
                                            <div class="fb-radio-desc">Clean Grade A • Pre-Owned Tested</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Delivery Fleet & Package Size (Bike vs Keke Cargo) -->
                            <div class="fb-field-group">
                                <label class="fb-field-label">
                                    Delivery Vehicle &amp; Package Tier <span class="fb-req">*</span>
                                </label>
                                <div class="fb-radio-grid">
                                    <label id="cardLogisticsBike" class="fb-radio-card active">
                                        <input type="radio" name="package_tier" value="bike" checked onchange="setLogistics('bike');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #0369A1;">🏍️ Bike Dispatch</div>
                                            <div class="fb-radio-desc">Normal Fee (Phone, Shoes, Clothes)</div>
                                        </div>
                                    </label>
                                    <label id="cardLogisticsKeke" class="fb-radio-card">
                                        <input type="radio" name="package_tier" value="keke" onchange="setLogistics('keke');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #B45309;">🛺 Keke Cargo</div>
                                            <div class="fb-radio-desc">Bulky/Heavy (Inverter, TV, AC)</div>
                                        </div>
                                    </label>
                                </div>
                                <div id="fbKekeNotice" class="fb-cargo-notice" style="display: none;">
                                    ⚠️ Keke orders automatically trigger the hub bulky cargo surcharge (₦1,000 baseline) to pay the tricycle cargo rider.
                                </div>
                            </div>

                            <!-- Stock Quantity & Warranty Window -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inInventory">
                                        Stock Quantity <span class="fb-req">*</span>
                                    </label>
                                    <input type="number" name="inventory_quantity" id="inInventory" required min="0" max="1000000" class="fb-input" value="1" oninput="syncLivePreview();">
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inWarranty">
                                        Warranty / Return Policy
                                    </label>
                                    <select name="warranty_type" id="inWarranty" class="fb-select" onchange="syncLivePreview();">
                                        <option value="in_store_testing" selected>Doorstep / In-Store Testing on Arrival</option>
                                        <option value="7_days_replacement">7-Day Return / Replacement Warranty</option>
                                        <option value="brand_warranty">Official Manufacturer Warranty</option>
                                        <option value="as_is">Sold As-Is (No Warranty After Handshake)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Description / Key Specs -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inDescription">
                                    Description / Key Specs
                                </label>
                                <textarea name="description" id="inDescription" rows="3" class="fb-textarea" placeholder="Tell buyers about your item (storage, color, included accessories, battery health, condition notes)..." oninput="syncLivePreview();"></textarea>
                            </div>

                            <!-- YouTube Unboxing Video URL -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inVideoUrl">
                                    YouTube Video Demo <span class="fb-opt">(Optional)</span>
                                </label>
                                <input type="url" name="video_url" id="inVideoUrl" class="fb-input" placeholder="https://youtube.com/watch?v=... or https://youtu.be/...">
                            </div>
                        </div>

                        <!-- Sticky Submit Bar in Form -->
                        <div class="fb-form-footer">
                            <button type="button" class="fb-btn-cancel" onclick="closeNewProductModal();">
                                Cancel
                            </button>
                            <button type="submit" id="btnFbSubmit" class="fb-btn-publish">
                                🚀 Publish Listing Now
                            </button>
                        </div>
                    </form>
                </div>

                <!-- RIGHT COLUMN: Sticky Live Facebook Marketplace Listing Preview Card -->
                <div class="fb-col-preview" id="fbColPreview">
                    <div class="fb-preview-sticky-wrapper">
                        <!-- Simulated Facebook Marketplace Banner -->
                        <div class="fb-mock-top-header">
                            <div class="fb-mock-crumb">
                                <span>Marketplace</span>
                                <span>&rsaquo;</span>
                                <span id="fbPrevCrumbCategory">Electronics</span>
                                <span>&rsaquo;</span>
                                <span id="fbPrevCrumbCondition">Brand New</span>
                            </div>
                            <div class="fb-mock-badge-live">
                                <span class="fb-pulse-dot"></span> Live Facebook Preview
                            </div>
                        </div>

                        <!-- The Facebook Listing Card -->
                        <div class="fb-listing-card">
                            <!-- Hero Image Viewport with Floating Badges -->
                            <div class="fb-card-hero" id="fbHeroContainer">
                                <img id="fbPrevHeroImg" src="" alt="Listing Preview" style="display: none;">
                                <div id="fbHeroPlaceholder" class="fb-hero-placeholder">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span style="font-weight: 700; color: #64748B; margin-top: 8px;">Product Photo Preview</span>
                                    <span style="font-size: 11.5px; color: #94A3B8;">Add photos on the left to see live preview</span>
                                </div>

                                <!-- Floating Badges on Hero -->
                                <div class="fb-hero-badges-top">
                                    <span id="fbPrevConditionPill" class="fb-pill-condition new">✨ Brand New</span>
                                    <span id="fbPrevTierPill" class="fb-pill-tier bike">🏍️ Bike Dispatch</span>
                                </div>

                                <!-- Carousel Dot Indicators -->
                                <div id="fbHeroDots" class="fb-hero-dots" style="display: none;"></div>
                            </div>

                            <!-- Listing Card Body -->
                            <div class="fb-card-content">
                                <!-- Price & Discount Row -->
                                <div class="fb-prev-price-row">
                                    <div class="fb-prev-main-price" id="fbPrevPrice">₦0.00</div>
                                    <div class="fb-prev-slash-price" id="fbPrevSlashPrice" style="display: none;"></div>
                                    <div class="fb-prev-discount-pill" id="fbPrevDiscountPill" style="display: none;"></div>
                                </div>

                                <!-- Listing Title -->
                                <div class="fb-prev-title" id="fbPrevTitle">Your Product Title Here</div>

                                <!-- Location & Timestamp -->
                                <div class="fb-prev-meta">
                                    <span id="fbPrevLocation">Listed just now in {{ $shops->first()->name ?? 'Main Outlet' }}</span>
                                    <span>•</span>
                                    <span style="color: #10B981; font-weight: 700;">In Stock (<span id="fbPrevStockCount">1</span>)</span>
                                </div>

                                <!-- Simulated Facebook Marketplace Action Buttons -->
                                <div class="fb-prev-actions">
                                    <button type="button" class="fb-btn-fb-message" tabindex="-1">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.908 1.455 5.503 3.736 7.155v3.587l3.418-1.876c.905.251 1.865.387 2.846.387 5.523 0 10-4.145 10-9.253C22 6.145 17.523 2 12 2zm1.053 12.463l-2.684-2.864-5.237 2.864 5.76-6.115 2.753 2.864 5.168-2.864-5.76 6.115z"/>
                                        </svg>
                                        Message Seller
                                    </button>
                                    <div class="fb-action-icon-btns">
                                        <button type="button" class="fb-icon-btn" title="Save to Wishlist" tabindex="-1">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </button>
                                        <button type="button" class="fb-icon-btn" title="Share Listing" tabindex="-1">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="18" cy="5" r="3"></circle>
                                                <circle cx="6" cy="12" r="3"></circle>
                                                <circle cx="18" cy="19" r="3"></circle>
                                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Seller Profile Block -->
                                <div class="fb-prev-seller-block">
                                    <div class="fb-prev-seller-header">Seller Information</div>
                                    <div class="fb-prev-seller-row">
                                        <div class="fb-prev-seller-avatar">
                                            {{ substr($vendor->business_name ?? Auth::user()->name ?? 'V', 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="fb-prev-seller-name">
                                                {{ $vendor->business_name ?? Auth::user()->name ?? 'Merchant Store' }}
                                                <span class="fb-verified-check">✓</span>
                                            </div>
                                            <div class="fb-prev-seller-sub">Verified Merchant • Highly responsive to inquiries</div>
                                            <div class="fb-prev-seller-rating">⭐⭐⭐⭐⭐ 4.9 Marketplace Rating</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Details Breakdown -->
                                <div class="fb-prev-details-block">
                                    <div class="fb-prev-details-header">Details</div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Condition:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailCondition">Brand New</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Category:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailCategory">Electronics</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Logistics:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailTier">Bike Dispatch (Standard Fare)</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Warranty:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailWarranty">Doorstep / In-Store Testing</span>
                                    </div>
                                </div>

                                <!-- Description Block -->
                                <div class="fb-prev-desc-block">
                                    <div class="fb-prev-desc-header">Seller's Description</div>
                                    <div class="fb-prev-desc-text" id="fbPrevDesc">
                                        Authentic certified product backed by Victorious MARKET escrow protection.
                                    </div>
                                </div>

                                <!-- Escrow Trust Shield -->
                                <div class="fb-prev-escrow-badge">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    <div>
                                        <strong>Victorious Escrow Handshake:</strong>
                                        <span>Payment safely held until doorstep testing is signed off.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Facebook Marketplace Listing Studio CSS Design System -->
    <style>
        .fb-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(8px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow: hidden;
            animation: fbFadeIn 0.2s ease-out;
        }

        @keyframes fbFadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        .fb-studio-container {
            background: #F0F2F5;
            width: 100%;
            max-width: 1260px;
            height: 94vh;
            max-height: 94vh;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.15);
            overflow: hidden;
        }

        .fb-studio-topbar {
            background: #FFFFFF;
            border-bottom: 1px solid #E4E6EB;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 64px;
            flex-shrink: 0;
        }

        .fb-topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fb-market-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #E7F3FF;
            color: #1877F2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-topbar-title {
            font-size: 17px;
            font-weight: 800;
            color: #050505;
            letter-spacing: -0.3px;
        }

        .fb-topbar-sub {
            font-size: 12px;
            color: #65676B;
        }

        .fb-mobile-tabs {
            display: none;
            background: #E4E6EB;
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
        }

        .fb-tab-btn {
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 700;
            color: #65676B;
            padding: 7px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fb-tab-btn.active {
            background: #FFFFFF;
            color: #1877F2;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .fb-badge-dot {
            width: 8px;
            height: 8px;
            background: #10B981;
            border-radius: 50%;
            display: inline-block;
        }

        .fb-close-btn {
            border: none;
            background: #E4E6EB;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            color: #050505;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .fb-close-btn:hover {
            background: #D8DADF;
        }

        /* Studio Split Screen Grid */
        .fb-studio-body {
            display: grid;
            grid-template-columns: 560px 1fr;
            height: calc(100% - 64px);
            overflow: hidden;
        }

        /* Left Column: Form Studio */
        .fb-col-form {
            background: #FFFFFF;
            height: 100%;
            overflow-y: auto;
            padding: 24px 28px;
            border-right: 1px solid #E4E6EB;
        }

        .fb-merchant-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #F0F2F5;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .fb-merchant-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1877F2, #0A58CA);
            color: white;
            font-weight: 800;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(24, 119, 242, 0.35);
        }

        .fb-merchant-name {
            font-size: 14px;
            font-weight: 800;
            color: #050505;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fb-verified-check {
            width: 16px;
            height: 16px;
            background: #1877F2;
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
        }

        .fb-merchant-scope {
            font-size: 12px;
            color: #65676B;
            margin-top: 2px;
        }

        .fb-section-card {
            background: #FFFFFF;
            border: 1px solid #E4E6EB;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .fb-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .fb-section-title {
            font-size: 15px;
            font-weight: 800;
            color: #050505;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fb-badge-compulsory {
            font-size: 10px;
            font-weight: 900;
            color: #DC2626;
            background: #FEE2E2;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .fb-section-hint {
            font-size: 12px;
            color: #65676B;
            margin: 4px 0 0;
        }

        .fb-photo-count {
            font-size: 12px;
            font-weight: 800;
            color: #1877F2;
            background: #E7F3FF;
            padding: 3px 10px;
            border-radius: 12px;
        }

        /* Dropzone */
        .fb-dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            background: #F8FAFC;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .fb-dropzone:hover, .fb-dropzone.dragover {
            border-color: #1877F2;
            background: #EBF5FF;
        }

        .fb-dropzone-icon {
            margin-bottom: 8px;
        }

        .fb-dropzone-text {
            font-size: 14.5px;
            font-weight: 800;
            color: #1877F2;
        }

        .fb-dropzone-sub {
            font-size: 12px;
            color: #65676B;
            margin-top: 3px;
        }

        .fb-dropzone-limits {
            font-size: 11px;
            color: #94A3B8;
            margin-top: 4px;
        }

        .fb-inline-error {
            background: #FEF2F2;
            border-left: 3.5px solid #EF4444;
            padding: 9px 12px;
            border-radius: 6px;
            color: #B91C1C;
            font-size: 12px;
            margin-top: 10px;
        }

        /* Dynamic Photo Gallery Strip */
        .fb-thumbnail-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .fb-thumb-card {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #E4E6EB;
            cursor: pointer;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .fb-thumb-card.active {
            border-color: #1877F2;
            box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.35);
        }

        .fb-thumb-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fb-thumb-badge-cover {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.75);
            color: #FBBF24;
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 5px;
            border-radius: 4px;
            backdrop-filter: blur(2px);
        }

        .fb-thumb-btn-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(0, 0, 0, 0.65);
            color: white;
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .fb-thumb-btn-remove:hover {
            background: #EF4444;
        }

        .fb-thumb-add-tile {
            aspect-ratio: 1;
            border: 2px dashed #CBD5E1;
            border-radius: 8px;
            background: #F8FAFC;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #1877F2;
            font-weight: 700;
            font-size: 11px;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .fb-thumb-add-tile:hover {
            border-color: #1877F2;
            background: #EBF5FF;
        }

        /* Form Inputs */
        .fb-field-group {
            margin-bottom: 14px;
        }

        .fb-field-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #050505;
            margin-bottom: 5px;
        }

        .fb-req {
            color: #EF4444;
            font-weight: 900;
        }

        .fb-opt {
            font-size: 11.5px;
            font-weight: 400;
            color: #65676B;
        }

        .fb-input, .fb-select, .fb-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #CED0D4;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: #050505;
            background: #FFFFFF;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
        }

        .fb-input:focus, .fb-select:focus, .fb-textarea:focus {
            outline: none;
            border-color: #1877F2;
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.15);
        }

        .fb-input.error, .fb-select.error {
            border-color: #EF4444 !important;
            background-color: #FEF2F2;
        }

        .fb-input.valid {
            border-color: #10B981 !important;
        }

        .fb-field-hint {
            font-size: 11.5px;
            color: #65676B;
            margin-top: 4px;
        }

        .fb-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Price Slash Alert */
        .fb-slash-alert {
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .fb-slash-alert.error {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
        }

        .fb-slash-alert.success {
            background: #F0FDF4;
            border: 1px solid #86EFAC;
            color: #065F46;
        }

        /* Radio Choice Cards */
        .fb-radio-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .fb-radio-card {
            border: 1.5px solid #CED0D4;
            background: #FFFFFF;
            border-radius: 10px;
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            transition: all 0.15s ease;
        }

        .fb-radio-card input {
            margin-top: 3px;
        }

        .fb-radio-card.active {
            border-color: #1877F2;
            background: #F0F7FF;
        }

        .fb-radio-title {
            font-size: 13px;
            font-weight: 800;
        }

        .fb-radio-desc {
            font-size: 11px;
            color: #65676B;
            margin-top: 2px;
        }

        .fb-cargo-notice {
            margin-top: 8px;
            font-size: 11.5px;
            color: #92400E;
            background: #FEF3C7;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid #FDE68A;
        }

        /* Form Footer / Submit */
        .fb-form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #E4E6EB;
            margin-top: 18px;
        }

        .fb-btn-cancel {
            border: none;
            background: #E4E6EB;
            color: #050505;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .fb-btn-cancel:hover {
            background: #D8DADF;
        }

        .fb-btn-publish {
            border: none;
            background: #1877F2;
            color: #FFFFFF;
            padding: 11px 26px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.35);
            transition: background 0.15s ease, transform 0.1s ease;
        }

        .fb-btn-publish:hover:not(:disabled) {
            background: #166FE5;
            transform: translateY(-1px);
        }

        .fb-btn-publish:disabled {
            background: #94A3B8 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* RIGHT COLUMN: Live Facebook Preview */
        .fb-col-preview {
            background: #F0F2F5;
            height: 100%;
            overflow-y: auto;
            padding: 24px 28px;
            display: flex;
            justify-content: center;
        }

        .fb-preview-sticky-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .fb-mock-top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 12px;
            color: #65676B;
        }

        .fb-mock-crumb {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .fb-mock-badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 3px 10px;
            border-radius: 12px;
        }

        .fb-pulse-dot {
            width: 7px;
            height: 7px;
            background: #10B981;
            border-radius: 50%;
            animation: fbPulse 1.5s infinite;
        }

        @keyframes fbPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* Facebook Listing Card */
        .fb-listing-card {
            background: #FFFFFF;
            border-radius: 14px;
            border: 1px solid #CED0D4;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .fb-card-hero {
            position: relative;
            aspect-ratio: 4/3;
            background: #E4E6EB;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-card-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fb-hero-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
        }

        .fb-hero-badges-top {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            justify-content: space-between;
            pointer-events: none;
        }

        .fb-pill-condition {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        .fb-pill-condition.new {
            background: rgba(16, 185, 129, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-condition.used {
            background: rgba(79, 70, 229, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-tier {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        .fb-pill-tier.bike {
            background: rgba(2, 132, 199, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-tier.keke {
            background: rgba(217, 119, 6, 0.9);
            color: #FFFFFF;
        }

        .fb-hero-dots {
            position: absolute;
            bottom: 8px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 6px;
        }

        .fb-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transition: all 0.15s ease;
        }

        .fb-dot.active {
            background: #FFFFFF;
            transform: scale(1.3);
        }

        /* Card Content */
        .fb-card-content {
            padding: 16px 18px 20px;
        }

        .fb-prev-price-row {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 4px;
        }

        .fb-prev-main-price {
            font-size: 24px;
            font-weight: 800;
            color: #050505;
            letter-spacing: -0.5px;
        }

        .fb-prev-slash-price {
            font-size: 15px;
            color: #65676B;
            text-decoration: line-through;
            font-weight: 600;
        }

        .fb-prev-discount-pill {
            font-size: 11.5px;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .fb-prev-title {
            font-size: 18px;
            font-weight: 700;
            color: #050505;
            line-height: 1.35;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .fb-prev-meta {
            font-size: 12.5px;
            color: #65676B;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
        }

        /* Action Buttons */
        .fb-prev-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .fb-btn-fb-message {
            flex: 1;
            background: #1877F2;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .fb-action-icon-btns {
            display: flex;
            gap: 6px;
        }

        .fb-icon-btn {
            border: none;
            background: #E4E6EB;
            color: #050505;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Seller Info */
        .fb-prev-seller-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-seller-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 8px;
        }

        .fb-prev-seller-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fb-prev-seller-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #1877F2;
            color: white;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-prev-seller-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #050505;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .fb-prev-seller-sub {
            font-size: 11.5px;
            color: #65676B;
        }

        .fb-prev-seller-rating {
            font-size: 11px;
            font-weight: 700;
            color: #B45309;
            margin-top: 2px;
        }

        /* Details */
        .fb-prev-details-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-details-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 8px;
        }

        .fb-prev-detail-item {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            padding: 4px 0;
        }

        .fb-detail-label {
            color: #65676B;
            font-weight: 600;
        }

        .fb-detail-value {
            color: #050505;
            font-weight: 700;
            text-align: right;
        }

        /* Description */
        .fb-prev-desc-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-desc-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 6px;
        }

        .fb-prev-desc-text {
            font-size: 12.5px;
            color: #374151;
            line-height: 1.5;
            white-space: pre-line;
            word-break: break-word;
        }

        /* Escrow Shield */
        .fb-prev-escrow-badge {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11.5px;
            color: #065F46;
        }

        /* RESPONSIVENESS ACROSS ALL POSSIBLE SCREENS */
        @media (max-width: 991px) {
            .fb-modal-backdrop {
                padding: 0;
            }

            .fb-studio-container {
                max-width: 100vw;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }

            .fb-mobile-tabs {
                display: flex;
            }

            .fb-studio-body {
                display: block;
                height: calc(100% - 64px);
                position: relative;
            }

            .fb-col-form {
                width: 100%;
                height: 100%;
                display: block;
                box-sizing: border-box;
                padding: 20px 16px 80px;
            }

            .fb-col-preview {
                width: 100%;
                height: 100%;
                display: none;
                box-sizing: border-box;
                padding: 20px 16px 80px;
            }

            .fb-col-preview.active-tab {
                display: flex;
            }

            .fb-col-form.hide-tab {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .fb-topbar-sub {
                display: none;
            }

            .fb-grid-2 {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .fb-radio-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .fb-merchant-pill {
                padding: 10px;
            }
        }
    </style>

    <!-- Facebook Marketplace Listing Interactive JavaScript Engine -->
    <script>
        // State
        let uploadedFiles = [];
        let activeHeroIndex = 0;
        let isPriceValid = true;

        function openNewProductModal() {
            const modal = document.getElementById('vmNewProductModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            syncLivePreview();
            validatePricing();
        }

        function closeNewProductModal() {
            const modal = document.getElementById('vmNewProductModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Mobile Tab Switcher
        function switchStudioTab(tab) {
            const colForm = document.getElementById('fbColForm');
            const colPreview = document.getElementById('fbColPreview');
            const btnForm = document.getElementById('tabBtnForm');
            const btnPreview = document.getElementById('tabBtnPreview');

            if (tab === 'preview') {
                colForm.classList.add('hide-tab');
                colPreview.classList.add('active-tab');
                btnPreview.classList.add('active');
                btnForm.classList.remove('active');
            } else {
                colForm.classList.remove('hide-tab');
                colPreview.classList.remove('active-tab');
                btnForm.classList.add('active');
                btnPreview.classList.remove('active');
            }
        }

        // Trigger file input
        function triggerPhotoSelect() {
            document.getElementById('fbFileInput').click();
        }

        // Handle File Selection
        function handlePhotosSelected(fileList) {
            const newFiles = Array.from(fileList);
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            for (let file of newFiles) {
                if (!validTypes.includes(file.type)) {
                    alert('Photo must be JPG, PNG, JPEG or WEBP format.');
                    continue;
                }
                if (file.size > maxSize) {
                    alert(`"${file.name}" exceeds 5MB size limit.`);
                    continue;
                }
                if (uploadedFiles.length < 10) {
                    uploadedFiles.push(file);
                } else {
                    alert('Maximum 10 photos allowed per listing.');
                    break;
                }
            }

            updateGalleryUI();
            updateFileInputDataTransfer();
            validatePhotoRequirement();
        }

        // Synchronize files to the actual form input using DataTransfer
        function updateFileInputDataTransfer() {
            const dataTransfer = new DataTransfer();
            uploadedFiles.forEach(file => dataTransfer.items.add(file));
            
            const fileInput = document.getElementById('fbFileInput');
            fileInput.files = dataTransfer.files;

            // Also synchronize primary photo to single fallback input
            if (uploadedFiles.length > 0) {
                const singleTransfer = new DataTransfer();
                singleTransfer.items.add(uploadedFiles[0]);
                document.getElementById('fbFileInputFallback').files = singleTransfer.files;
            }
        }

        // Remove photo by index
        function removePhoto(index, event) {
            if (event) event.stopPropagation();
            uploadedFiles.splice(index, 1);
            if (activeHeroIndex >= uploadedFiles.length) {
                activeHeroIndex = Math.max(0, uploadedFiles.length - 1);
            }
            updateGalleryUI();
            updateFileInputDataTransfer();
            validatePhotoRequirement();
        }

        // Set active preview thumbnail
        function selectHeroPhoto(index) {
            activeHeroIndex = index;
            renderHeroImage();
            renderGalleryThumbnails();
        }

        // Update thumbnail gallery UI
        function updateGalleryUI() {
            const countBadge = document.getElementById('fbPhotoCount');
            const gallery = document.getElementById('fbThumbnailGallery');
            const dropzone = document.getElementById('fbDropzone');

            countBadge.textContent = `${uploadedFiles.length} / 10`;

            if (uploadedFiles.length === 0) {
                gallery.style.display = 'none';
                dropzone.style.display = 'block';
                activeHeroIndex = 0;
            } else {
                gallery.style.display = 'grid';
                renderGalleryThumbnails();
            }

            renderHeroImage();
        }

        function renderGalleryThumbnails() {
            const gallery = document.getElementById('fbThumbnailGallery');
            gallery.innerHTML = '';

            uploadedFiles.forEach((file, idx) => {
                const card = document.createElement('div');
                card.className = `fb-thumb-card ${idx === activeHeroIndex ? 'active' : ''}`;
                card.onclick = () => selectHeroPhoto(idx);

                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = `Photo ${idx + 1}`;
                card.appendChild(img);

                // Cover badge for 1st photo
                if (idx === 0) {
                    const coverBadge = document.createElement('div');
                    coverBadge.className = 'fb-thumb-badge-cover';
                    coverBadge.textContent = '⭐ Cover';
                    card.appendChild(coverBadge);
                }

                // Delete button
                const btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = 'fb-thumb-btn-remove';
                btnRemove.innerHTML = '&times;';
                btnRemove.title = 'Remove photo';
                btnRemove.onclick = (e) => removePhoto(idx, e);
                card.appendChild(btnRemove);

                gallery.appendChild(card);
            });

            // If less than 10 photos, show "+ Add More" tile
            if (uploadedFiles.length < 10) {
                const addTile = document.createElement('div');
                addTile.className = 'fb-thumb-add-tile';
                addTile.onclick = triggerPhotoSelect;
                addTile.innerHTML = `
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Photo</span>
                `;
                gallery.appendChild(addTile);
            }
        }

        function renderHeroImage() {
            const heroImg = document.getElementById('fbPrevHeroImg');
            const placeholder = document.getElementById('fbHeroPlaceholder');
            const dotsContainer = document.getElementById('fbHeroDots');

            if (uploadedFiles.length > 0 && uploadedFiles[activeHeroIndex]) {
                heroImg.src = URL.createObjectURL(uploadedFiles[activeHeroIndex]);
                heroImg.style.display = 'block';
                placeholder.style.display = 'none';

                // Carousel Dots
                if (uploadedFiles.length > 1) {
                    dotsContainer.style.display = 'flex';
                    dotsContainer.innerHTML = '';
                    uploadedFiles.forEach((_, i) => {
                        const dot = document.createElement('div');
                        dot.className = `fb-dot ${i === activeHeroIndex ? 'active' : ''}`;
                        dotsContainer.appendChild(dot);
                    });
                } else {
                    dotsContainer.style.display = 'none';
                }
            } else {
                heroImg.style.display = 'none';
                placeholder.style.display = 'flex';
                dotsContainer.style.display = 'none';
            }
        }

        // Validate compulsory photo rule
        function validatePhotoRequirement() {
            const errorBanner = document.getElementById('fbPhotoError');
            const dropzone = document.getElementById('fbDropzone');

            if (uploadedFiles.length === 0) {
                dropzone.style.borderColor = '#EF4444';
                return false;
            } else {
                dropzone.style.borderColor = '#CBD5E1';
                errorBanner.style.display = 'none';
                return true;
            }
        }

        // PRICE SLASH VALIDATION (Strictly forbids selling price >= original price)
        function validatePricing() {
            const inUnitPrice = document.getElementById('inUnitPrice');
            const inSlashPrice = document.getElementById('inSlashPrice');
            const alertBox = document.getElementById('fbSlashAlert');
            const btnSubmit = document.getElementById('btnFbSubmit');

            const unitPrice = parseFloat(inUnitPrice.value) || 0;
            const slashPrice = inSlashPrice.value ? parseFloat(inSlashPrice.value) : null;

            isPriceValid = true;
            alertBox.style.display = 'none';
            inUnitPrice.classList.remove('error', 'valid');
            inSlashPrice.classList.remove('error', 'valid');
            btnSubmit.disabled = false;

            if (slashPrice !== null && !isNaN(slashPrice)) {
                // If slash price is entered, selling price MUST be strictly less than slash price
                if (unitPrice >= slashPrice) {
                    isPriceValid = false;
                    alertBox.style.display = 'block';
                    alertBox.className = 'fb-slash-alert error';
                    alertBox.innerHTML = `
                        ❌ <strong>Invalid Price Slash:</strong> The selling price (<strong>₦${unitPrice.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong>)
                        cannot be higher than or equal to the original regular price (<strong>₦${slashPrice.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong>).
                        A discount slash must lower the price. Please correct the selling price or original price.
                    `;
                    inUnitPrice.classList.add('error');
                    inSlashPrice.classList.add('error');
                    btnSubmit.disabled = true;
                } else if (unitPrice > 0 && slashPrice > unitPrice) {
                    const savings = slashPrice - unitPrice;
                    const discountPercent = Math.round((savings / slashPrice) * 100);
                    alertBox.style.display = 'block';
                    alertBox.className = 'fb-slash-alert success';
                    alertBox.innerHTML = `
                        🏷️ <strong>Valid Deal Slash:</strong> Buyer saves <strong>₦${savings.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong> (${discountPercent}% OFF) on Marketplace!
                    `;
                    inUnitPrice.classList.add('valid');
                    inSlashPrice.classList.add('valid');
                }
            }
        }

        // Condition toggle
        function setCondition(val) {
            const cardNew = document.getElementById('cardConditionNew');
            const cardUsed = document.getElementById('cardConditionUsed');
            if (val === 'brand_new') {
                cardNew.classList.add('active');
                cardUsed.classList.remove('active');
            } else {
                cardUsed.classList.add('active');
                cardNew.classList.remove('active');
            }
            syncLivePreview();
        }

        // Logistics toggle
        function setLogistics(val) {
            const cardBike = document.getElementById('cardLogisticsBike');
            const cardKeke = document.getElementById('cardLogisticsKeke');
            const kekeNotice = document.getElementById('fbKekeNotice');
            if (val === 'bike') {
                cardBike.classList.add('active');
                cardKeke.classList.remove('active');
                kekeNotice.style.display = 'none';
            } else {
                cardKeke.classList.add('active');
                cardBike.classList.remove('active');
                kekeNotice.style.display = 'block';
            }
            syncLivePreview();
        }

        // Sync form values into the sticky Facebook Marketplace preview card (Safe XSS-free)
        function syncLivePreview() {
            const titleInput = document.getElementById('inProdName');
            const unitPriceInput = document.getElementById('inUnitPrice');
            const slashPriceInput = document.getElementById('inSlashPrice');
            const categorySelect = document.getElementById('inCategory');
            const shopSelect = document.getElementById('inShop');
            const inventoryInput = document.getElementById('inInventory');
            const warrantySelect = document.getElementById('inWarranty');
            const descInput = document.getElementById('inDescription');

            // Elements in Preview Card
            const prevTitle = document.getElementById('fbPrevTitle');
            const prevPrice = document.getElementById('fbPrevPrice');
            const prevSlash = document.getElementById('fbPrevSlashPrice');
            const prevDiscount = document.getElementById('fbPrevDiscountPill');
            const prevLocation = document.getElementById('fbPrevLocation');
            const prevStock = document.getElementById('fbPrevStockCount');
            const prevDesc = document.getElementById('fbPrevDesc');

            const prevCrumbCat = document.getElementById('fbPrevCrumbCategory');
            const prevCrumbCond = document.getElementById('fbPrevCrumbCondition');
            const prevConditionPill = document.getElementById('fbPrevConditionPill');
            const prevTierPill = document.getElementById('fbPrevTierPill');

            const prevDetailCond = document.getElementById('fbPrevDetailCondition');
            const prevDetailCat = document.getElementById('fbPrevDetailCategory');
            const prevDetailTier = document.getElementById('fbPrevDetailTier');
            const prevDetailWarranty = document.getElementById('fbPrevDetailWarranty');

            // Title
            prevTitle.textContent = titleInput.value.trim() ? titleInput.value.trim() : 'Your Product Title Here';

            // Prices
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const slashPrice = slashPriceInput.value ? parseFloat(slashPriceInput.value) : null;

            prevPrice.textContent = `₦${unitPrice.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            if (slashPrice && slashPrice > unitPrice && unitPrice > 0) {
                prevSlash.style.display = 'inline-block';
                prevSlash.textContent = `₦${slashPrice.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                const savings = slashPrice - unitPrice;
                const percent = Math.round((savings / slashPrice) * 100);
                prevDiscount.style.display = 'inline-block';
                prevDiscount.textContent = `${percent}% OFF`;
            } else {
                prevSlash.style.display = 'none';
                prevDiscount.style.display = 'none';
            }

            // Category
            const selectedCatText = categorySelect.options[categorySelect.selectedIndex]?.text || 'General';
            prevCrumbCat.textContent = selectedCatText;
            prevDetailCat.textContent = selectedCatText;

            // Shop / Location
            const selectedShopText = shopSelect.options[shopSelect.selectedIndex]?.text || 'Plaza Outlet';
            prevLocation.textContent = `Listed just now in ${selectedShopText}`;

            // Inventory
            prevStock.textContent = inventoryInput.value || '1';

            // Condition
            const conditionVal = document.querySelector('input[name="condition"]:checked')?.value || 'brand_new';
            if (conditionVal === 'brand_new') {
                prevCrumbCond.textContent = 'Brand New';
                prevConditionPill.textContent = '✨ Brand New';
                prevConditionPill.className = 'fb-pill-condition new';
                prevDetailCond.textContent = 'Brand New (Factory Sealed)';
            } else {
                prevCrumbCond.textContent = 'UK Used';
                prevConditionPill.textContent = '🇬🇧 UK Used';
                prevConditionPill.className = 'fb-pill-condition used';
                prevDetailCond.textContent = 'UK Used (Clean Grade A)';
            }

            // Logistics Tier
            const tierVal = document.querySelector('input[name="package_tier"]:checked')?.value || 'bike';
            if (tierVal === 'bike') {
                prevTierPill.textContent = '🏍️ Bike Dispatch';
                prevTierPill.className = 'fb-pill-tier bike';
                prevDetailTier.textContent = 'Bike Dispatch (Standard Fare)';
            } else {
                prevTierPill.textContent = '🛺 Keke Cargo';
                prevTierPill.className = 'fb-pill-tier keke';
                prevDetailTier.textContent = 'Keke Cargo (+Bulky Surcharge applies)';
            }

            // Warranty
            const warrantyText = warrantySelect.options[warrantySelect.selectedIndex]?.text || 'Doorstep Testing';
            prevDetailWarranty.textContent = warrantyText;

            // Description
            const desc = descInput.value.trim();
            prevDesc.textContent = desc ? desc : 'Authentic certified product backed by Victorious MARKET escrow protection.';
        }

        // Form Submit Validation Handler
        document.getElementById('fbProductForm').addEventListener('submit', function (e) {
            // Check compulsory photo
            if (uploadedFiles.length === 0) {
                e.preventDefault();
                const errorBanner = document.getElementById('fbPhotoError');
                errorBanner.style.display = 'block';
                document.getElementById('fbDropzone').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            // Check price validity
            validatePricing();
            if (!isPriceValid) {
                e.preventDefault();
                alert('Please resolve the price slash error. Slashed selling price cannot exceed the original price.');
                document.getElementById('inUnitPrice').focus();
                return false;
            }

            // Submit button loading state
            const btnSubmit = document.getElementById('btnFbSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '⏳ Publishing to Marketplace...';
        });

        // Drag and Drop support
        const dropzone = document.getElementById('fbDropzone');
        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handlePhotosSelected(dt.files);
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNewProductModal();
            }
        });
    </script>
@endsection