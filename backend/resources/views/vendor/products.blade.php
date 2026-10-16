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

    <!-- Modal for Fast Nigerian Product Listing (30-45 Second Listing Engine) -->
    <div id="vmNewProductModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 16px; overflow-y: auto;">
        <div style="background: white; border-radius: 14px; width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 25px 35px -5px rgba(0,0,0,0.2); border: 1px solid var(--adm-border);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; border-bottom: 1px solid #F3F4F6; padding-bottom: 12px;">
                <div>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--adm-text); margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>🚀</span> Fast Merchant Product Listing
                    </h3>
                    <p style="font-size: 12px; color: var(--adm-text-muted); margin: 4px 0 0;">
                        Streamlined for Nigerian merchants • 7-Day Freshness Certified
                    </p>
                </div>
                <button type="button" onclick="closeNewProductModal();" style="border: none; background: #F3F4F6; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; color: var(--adm-text-muted); display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>

            <form method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Product Name -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 5px; color: var(--adm-text);">
                        Product Name <span style="color: #EF4444;">*</span>
                    </label>
                    <input type="text" name="name" required class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. iPhone 13 128GB Midnight Blue or Standing Fan 18 Inch Ox">
                </div>

                <!-- Condition Selector (Strictly Brand New vs UK Used) -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                        Product Condition <span style="color: #EF4444;">*</span>
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label id="lblConditionNew" style="cursor: pointer; border: 2px solid #10B981; background: #F0FDF4; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px; transition: all 0.15s ease;">
                            <input type="radio" name="condition" value="brand_new" checked onchange="toggleCondition(this.value);" style="accent-color: #10B981;">
                            <div>
                                <div style="font-weight: 800; font-size: 13px; color: #065F46;">✨ Brand New</div>
                                <div style="font-size: 11px; color: #047857;">Sealed / Factory Box</div>
                            </div>
                        </label>
                        <label id="lblConditionUsed" style="cursor: pointer; border: 1.5px solid var(--adm-border); background: white; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px; transition: all 0.15s ease;">
                            <input type="radio" name="condition" value="uk_used" onchange="toggleCondition(this.value);" style="accent-color: #4F46E5;">
                            <div>
                                <div style="font-weight: 800; font-size: 13px; color: #374151;">🇬🇧 UK Used</div>
                                <div style="font-size: 11px; color: #6B7280;">Clean Grade A / Pre-Owned</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Logistics / Delivery Means (Strictly Bike vs Keke) -->
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--adm-text);">
                        Delivery Vehicle &amp; Package Size <span style="color: #EF4444;">*</span>
                    </label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label id="lblLogisticsBike" style="cursor: pointer; border: 2px solid #0284C7; background: #F0F9FF; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px; transition: all 0.15s ease;">
                            <input type="radio" name="package_tier" value="bike" checked onchange="toggleLogistics(this.value);" style="accent-color: #0284C7;">
                            <div>
                                <div style="font-weight: 800; font-size: 13px; color: #0369A1;">🏍️ Bike Dispatch</div>
                                <div style="font-size: 11px; color: #0284C7;">Normal Fee (Phone, Shoes, Clothes)</div>
                            </div>
                        </label>
                        <label id="lblLogisticsKeke" style="cursor: pointer; border: 1.5px solid var(--adm-border); background: white; border-radius: 8px; padding: 10px; display: flex; align-items: center; gap: 10px; transition: all 0.15s ease;">
                            <input type="radio" name="package_tier" value="keke" onchange="toggleLogistics(this.value);" style="accent-color: #D97706;">
                            <div>
                                <div style="font-weight: 800; font-size: 13px; color: #374151;">🛺 Keke Cargo</div>
                                <div style="font-size: 11px; color: #B45309;">Heavy / Bulky (+Surcharge applies)</div>
                            </div>
                        </label>
                    </div>
                    <div id="kekeNotice" style="display: none; margin-top: 6px; font-size: 11.5px; color: #B45309; background: #FEF3C7; padding: 6px 10px; border-radius: 6px; font-weight: 600;">
                        ⚠️ Keke orders automatically apply the hub cargo surcharge (₦1,000 baseline) to cover tricycle rider fare.
                    </div>
                </div>

                <!-- Pricing & Slash Price (Nigerian Bargain Appeal) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Selling Price (₦) <span style="color: #EF4444;">*</span>
                        </label>
                        <input type="number" step="0.01" name="unit_price" id="inUnitPrice" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. 45000">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Slash / Original Price (₦)
                            <span style="font-size: 11px; font-weight: normal; color: var(--adm-text-muted);">(Optional strikethrough)</span>
                        </label>
                        <input type="number" step="0.01" name="slash_price" id="inSlashPrice" class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="e.g. 52000">
                    </div>
                </div>

                <!-- Stock Quantity & Shop Branch -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Stock Quantity <span style="color: #EF4444;">*</span>
                        </label>
                        <input type="number" name="inventory_quantity" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;" placeholder="20" value="1" min="0">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Plaza Outlet / Branch
                        </label>
                        <select name="shop_id" class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13px;">
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

                <!-- Category & Warranty -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Category <span style="color: #EF4444;">*</span>
                        </label>
                        <select name="category_id" required class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13px;">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Warranty / Testing Window
                        </label>
                        <select name="warranty_type" class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13px;">
                            <option value="in_store_testing" selected>Doorstep / In-Store Testing on Arrival</option>
                            <option value="7_days_replacement">7-Day Return / Replacement Warranty</option>
                            <option value="brand_warranty">Official Manufacturer Warranty</option>
                            <option value="as_is">Sold As-Is (No Warranty After Handshake)</option>
                        </select>
                    </div>
                </div>

                <!-- Image & YouTube Unboxing -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            Product Photo
                        </label>
                        <input type="file" name="image" accept="image/*" class="form-control" style="width: 100%; padding: 7px 10px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                            YouTube Unboxing Video
                        </label>
                        <input type="url" name="video_url" class="form-control" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 12.5px;" placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>

                <!-- Description / Key Specs -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">
                        Brief Description / Key Specs
                    </label>
                    <textarea name="description" rows="2" class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 8px; font-size: 13px;" placeholder="Key specifications, color, storage, warranty terms, accessories included..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #F3F4F6; padding-top: 14px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="closeNewProductModal();" style="padding: 9px 18px;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 9px 24px; font-weight: 800; font-size: 13.5px;">
                        🚀 Publish Product Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewProductModal() {
            document.getElementById('vmNewProductModal').style.display = 'flex';
        }

        function closeNewProductModal() {
            document.getElementById('vmNewProductModal').style.display = 'none';
        }

        function toggleCondition(val) {
            const lblNew = document.getElementById('lblConditionNew');
            const lblUsed = document.getElementById('lblConditionUsed');
            if (val === 'brand_new') {
                lblNew.style.border = '2px solid #10B981';
                lblNew.style.background = '#F0FDF4';
                lblUsed.style.border = '1.5px solid var(--adm-border)';
                lblUsed.style.background = 'white';
            } else {
                lblUsed.style.border = '2px solid #4F46E5';
                lblUsed.style.background = '#EEF2FF';
                lblNew.style.border = '1.5px solid var(--adm-border)';
                lblNew.style.background = 'white';
            }
        }

        function toggleLogistics(val) {
            const lblBike = document.getElementById('lblLogisticsBike');
            const lblKeke = document.getElementById('lblLogisticsKeke');
            const kekeNotice = document.getElementById('kekeNotice');
            if (val === 'bike') {
                lblBike.style.border = '2px solid #0284C7';
                lblBike.style.background = '#F0F9FF';
                lblKeke.style.border = '1.5px solid var(--adm-border)';
                lblKeke.style.background = 'white';
                kekeNotice.style.display = 'none';
            } else {
                lblKeke.style.border = '2px solid #D97706';
                lblKeke.style.background = '#FEF3C7';
                lblBike.style.border = '1.5px solid var(--adm-border)';
                lblBike.style.background = 'white';
                kekeNotice.style.display = 'block';
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNewProductModal();
            }
        });
    </script>
@endsection