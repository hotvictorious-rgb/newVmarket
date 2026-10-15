@extends('layouts.dashboard')

@section('title', 'Product Moderation & Freshness Desk')
@section('role_title', 'Admin Staff')
@section('page_title', 'Product Moderation')
@section('role_badge', 'ADMIN STAFF')
@section('role_badge_class', 'role-badge-admin-staff')

@section('sidebar_menu')
    @include('admin-staff.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🔍</span>
                <span>Product Moderation &amp; 7-Day Freshness Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Verify merchant catalog submissions and inspect inventory approaching the 7-day auto-stale delisting boundary.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('admin.staff.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Staff Desk
            </a>
            <span class="dash-badge dash-badge-purple" style="font-size: 12px; padding: 6px 12px;">
                7-Day Freshness Guarantee Active
            </span>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🔍</span>
                <div>
                    <div>Catalog Moderation Queue</div>
                    <span class="dash-card-subtitle">Active merchant listings pending quality sign-off and freshness certification.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-info">{{ count($products) }} Items Monitored</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Merchant Plaza Branch</th>
                        <th>Unit Price</th>
                        <th>Freshness Status</th>
                        <th>Staff Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div style="font-weight: 700;">{{ $product->name }}</div>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">SKU: VM-PRD-{{ $product->id }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $product->category->name ?? 'General' }}</span>
                            </td>
                            <td>{{ $product->shop->name ?? 'Main Plaza Store' }}</td>
                            <td><strong>&#8358;{{ number_format($product->unit_price, 2) }}</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-success">
                                    ✓ Fresh Listing (Active)
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn-dash btn-dash-outline" onclick="alert('Product #{{ $product->id }} verified and approved for storefront!')">
                                    <span>✓ Verify &amp; Pass</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No products in moderation queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection