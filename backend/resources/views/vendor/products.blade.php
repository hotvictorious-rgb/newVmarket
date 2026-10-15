@extends('layouts.dashboard')

@section('title', 'Products & Freshness Catalog — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Products & Freshness Catalog')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Products &amp; Freshness</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏷️</span>
                <span>Products &amp; 7-Day Freshness Catalog</span>
            </h1>
            <p class="page-header-subtitle">
                Manage your catalog listings. Items must be restocked or re-verified every 7 days to preserve certified freshness on Victorious MARKET.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-dash btn-dash-primary" onclick="document.getElementById('vmNewProductModal').style.display = 'flex';">
                + Add New Product
            </button>
        </div>
    </div>

    <!-- Alert / Notice -->
    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('success') }}
        </div>
    @endif

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Live Merchant Inventory ({{ count($products) }})</div>
                <span class="dash-card-subtitle">Active products displayed across Uyo and Akwa Ibom consumer storefronts</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product &amp; Category</th>
                        <th>Price &amp; Stock</th>
                        <th>Freshness Status</th>
                        <th>Last Verified</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $daysOld = $product->freshness_updated_at ? \Carbon\Carbon::parse($product->freshness_updated_at)->diffInDays(now()) : 0;
                            $isFresh = $daysOld < 7;
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $product->name }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $product->category->name ?? 'General Category' }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: #059669;">₦{{ number_format($product->unit_price, 2) }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">Stock: {{ $product->current_stock }} units</span>
                            </td>
                            <td>
                                @if($isFresh)
                                    <span class="dash-badge dash-badge-success">⚡ FRESH (Day {{ $daysOld }}/7)</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">⚠️ EXPIRED (Day {{ $daysOld }})</span>
                                @endif
                            </td>
                            <td style="font-size: 12.5px; color: var(--adm-text-muted);">
                                {{ $product->freshness_updated_at ? \Carbon\Carbon::parse($product->freshness_updated_at)->format('d M, Y') : 'Never' }}
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
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No products in catalog yet. Click "+ Add New Product" to list your first item.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for New Product -->
    <div id="vmNewProductModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 17px; font-weight: 700; color: var(--adm-text); margin: 0;">Add New Merchant Product</h3>
                <button type="button" onclick="document.getElementById('vmNewProductModal').style.display = 'none';" style="border: none; background: none; font-size: 18px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('vendor.products.store') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Product Name</label>
                    <input type="text" name="name" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="e.g. Victor Special Fresh Blend">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Unit Price (₦)</label>
                        <input type="number" step="0.01" name="unit_price" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="5000">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Initial Stock</label>
                        <input type="number" name="current_stock" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="20">
                    </div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Category</label>
                    <select name="category_id" class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="document.getElementById('vmNewProductModal').style.display = 'none';">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
@endsection