@extends('layouts.dashboard')

@section('title', 'Product Catalog & Flagship Badge Governance')
@section('role_title', 'Super Admin')
@section('page_title', 'Platform Product Catalog')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">⭐</span>
                <span>Product Catalog &amp; Flagship Badge Governance</span>
            </h1>
            <p class="page-header-subtitle">
                Inspect merchant inventory across all plaza shops and assign sovereign ★ FLAGSHIP status to verified, high-quality products.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 12px; font-size: 12px;">
                ★ Flagship Badge Engine Active
            </span>
        </div>
    </div>

    <!-- Status Alert -->
    @if(session('status'))
        <div style="background: #ECFDF5; border: 1.5px solid #10B981; color: #065F46; border-radius: 10px; padding: 14px 18px; margin-bottom: 22px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- 3 KPI Metric Cards -->
    <div class="stat-grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Catalog Products</span>
                <span class="stat-card-value">{{ number_format($totalProducts) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">All Registered Merchant SKUs</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-gold" style="border-left: 4px solid var(--adm-gold);">
            <div class="stat-card-info">
                <span class="stat-card-label">Flagship Certified Items</span>
                <span class="stat-card-value" style="color: #B45309;">{{ number_format($flagshipCount) }}</span>
                <span class="stat-card-subtext" style="color: #92400E;">Featured with ★ FLAGSHIP Badge</span>
            </div>
            <div class="stat-card-icon-box" style="background: rgba(255, 215, 0, 0.15); color: #B45309;">⭐</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Standard Merchant Products</span>
                <span class="stat-card-value">{{ number_format($totalProducts - $flagshipCount) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Verified Third-Party Listings</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏪</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background: #FFFFFF; border: 1px solid var(--adm-border); border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--adm-shadow-xs);">
        <form method="GET" action="{{ route('admin.products.index') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 14px; align-items: flex-end;">
            <div>
                <label style="font-size: 11.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px; text-transform: uppercase;">Search Products</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, SKU, Shop, Merchant..." style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13px; box-sizing: border-box;">
            </div>

            <div>
                <label style="font-size: 11.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px; text-transform: uppercase;">Category</label>
                <select name="category_id" style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #FFF;">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size: 11.5px; font-weight: 700; color: #475569; display: block; margin-bottom: 5px; text-transform: uppercase;">Flagship Filter</label>
                <select name="flagship" style="width: 100%; padding: 9px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #FFF;">
                    <option value="">All Items</option>
                    <option value="1" {{ request('flagship') === '1' ? 'selected' : '' }}>★ Flagship Only</option>
                    <option value="0" {{ request('flagship') === '0' ? 'selected' : '' }}>Standard Only</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-dash btn-dash-primary" style="padding: 9px 18px; font-size: 13px;">
                    Filter
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn-dash btn-dash-outline" style="padding: 9px 14px; font-size: 13px; text-decoration: none;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div style="background: #FFFFFF; border: 1px solid var(--adm-border); border-radius: 14px; overflow: hidden; box-shadow: var(--adm-shadow-xs);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center; background: #FAF5FF;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">📋</span>
                <strong style="color: #0F172A; font-size: 14px;">Merchant Product Roster ({{ $products->total() }} items)</strong>
            </div>
            <span style="font-size: 12px; color: #64748B;">Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="dash-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 1px solid var(--adm-border); text-align: left; color: #475569; font-size: 11.5px; text-transform: uppercase;">
                        <th style="padding: 12px 16px; width: 60px;">Image</th>
                        <th style="padding: 12px 16px;">Product &amp; Category</th>
                        <th style="padding: 12px 16px;">Merchant &amp; Plaza Shop</th>
                        <th style="padding: 12px 16px;">Unit Price</th>
                        <th style="padding: 12px 16px; text-align: center;">Flagship Status</th>
                        <th style="padding: 12px 16px; text-align: right;">Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr style="border-bottom: 1px solid #F1F5F9; transition: background 0.15s ease;">
                            <td style="padding: 12px 16px;">
                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" style="max-width: 90%; max-height: 90%; object-fit: contain;">
                                </div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 700; color: #0F172A; margin-bottom: 3px;">
                                    {{ $product->name }}
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: #64748B;">
                                    <span style="background: #F1F5F9; padding: 2px 6px; border-radius: 4px;">{{ $product->category->name ?? 'General' }}</span>
                                    <span>&bull;</span>
                                    <span>SKU: {{ $product->sku ?? ('VM-' . str_pad($product->id, 5, '0', STR_PAD_LEFT)) }}</span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: #334155; margin-bottom: 2px;">
                                    🏬 {{ $product->shop->name ?? 'Verified Merchant Plaza' }}
                                </div>
                                <div style="font-size: 11.5px; color: #64748B; display: flex; align-items: center; gap: 5px;">
                                    <span>📍 {{ $product->shop->lga->name ?? 'Uyo' }}</span>
                                    <span>&bull;</span>
                                    <span>Vendor: {{ $product->vendor->business_name ?? 'Accredited Merchant' }}</span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <strong style="color: var(--adm-primary); font-size: 14px;">
                                    ₦{{ number_format((float)$product->unit_price, 2) }}
                                </strong>
                                <div style="font-size: 11px; color: #059669; font-weight: 600;">
                                    +5% Cashback (₦{{ number_format((float)$product->unit_price * 0.05, 2) }})
                                </div>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                @if($product->is_vmarket_flagship)
                                    <span style="background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);">
                                        <span>★</span> FLAGSHIP
                                    </span>
                                @else
                                    <span style="background: #F1F5F9; color: #64748B; font-weight: 600; font-size: 11px; padding: 4px 10px; border-radius: 999px; display: inline-block;">
                                        Standard Listing
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <form method="POST" action="{{ route('admin.products.toggle_flagship', $product->id) }}" style="display: inline-block;">
                                    @csrf
                                    @if($product->is_vmarket_flagship)
                                        <button type="submit" class="btn-dash btn-dash-outline" style="font-size: 11.5px; padding: 5px 12px; color: #DC2626; border-color: #FCA5A5; background: #FFF;" title="Remove Flagship Badge">
                                            <span>✕ Remove Flagship</span>
                                        </button>
                                    @else
                                        <button type="submit" class="btn-dash" style="background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-weight: 700; font-size: 11.5px; padding: 6px 14px; border: none; border-radius: 6px; cursor: pointer; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);" title="Assign Flagship Badge">
                                            <span>★ Make Flagship</span>
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 36px 16px; text-align: center; color: #94A3B8;">
                                <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
                                <strong style="color: #334155; font-size: 14px; display: block; margin-bottom: 4px;">No products match your filter criteria</strong>
                                <span style="font-size: 12px;">Try adjusting search terms or resetting the filter toolbar.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div style="padding: 14px 20px; border-top: 1px solid var(--adm-border); background: #FAF5FF; display: flex; justify-content: flex-end;">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
