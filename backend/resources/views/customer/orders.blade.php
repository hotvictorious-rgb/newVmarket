@extends('layouts.app')

@section('title', 'My Orders & Live Tracking — Victorious MARKET')

@section('content')
<div class="container customer-profile-wrapper">
    <!-- Breadcrumb -->
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--vm-text-muted); margin-bottom: 22px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-text-muted); text-decoration: none;">Storefront</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" style="color: var(--vm-text-muted); text-decoration: none;">Account</a>
        <span>/</span>
        <span style="color: var(--vm-primary); font-weight: 700;">My Orders</span>
    </div>

    <div class="profile-layout-grid">
        <!-- Sidebar -->
        @include('customer.partials._sidebar')

        <!-- Main Content -->
        <main class="profile-canvas">
            <div class="page-header-row" style="margin-bottom: 20px;">
                <div>
                    <h1 class="page-header-title" style="font-size: 22px; font-weight: 800; color: var(--vm-dark); display: flex; align-items: center; gap: 10px;">
                        <span>🛍️</span>
                        <span>My Orders &amp; Live Tracking</span>
                    </h1>
                    <p style="font-size: 13.5px; color: var(--vm-text-muted); margin-top: 4px;">
                        Real-time status tracking for all your Victorious MARKET purchases backed by Sovereign Escrow.
                    </p>
                </div>
            </div>

            <div class="legacy-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 class="legacy-card-title" style="margin-bottom: 0;">Order History ({{ count($orders) }})</h3>
                    <span class="badge" style="background: #ECFDF5; color: #047857; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
                        Escrow Protected
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--vm-border); text-align: left; font-size: 12px; color: var(--vm-text-muted); text-transform: uppercase;">
                                <th style="padding: 10px;">Order #</th>
                                <th style="padding: 10px;">Merchant Shop</th>
                                <th style="padding: 10px;">Total</th>
                                <th style="padding: 10px;">Delivery OTP</th>
                                <th style="padding: 10px;">Status</th>
                                <th style="padding: 10px;">Track</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $ord)
                                <tr style="border-bottom: 1px solid var(--vm-border); font-size: 13.5px;">
                                    <td style="padding: 12px 10px;">
                                        <strong style="color: var(--vm-primary);">{{ $ord->order_number }}</strong>
                                        <div style="font-size: 11px; color: var(--vm-text-muted);">{{ $ord->created_at->format('d M, Y') }}</div>
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <div style="font-weight: 600;">{{ $ord->shop->name ?? 'Victor Tech Plaza' }}</div>
                                        <div style="font-size: 11.5px; color: var(--vm-text-muted);">{{ $ord->vendor->business_name ?? 'Merchant' }}</div>
                                    </td>
                                    <td style="padding: 12px 10px; font-weight: 800; color: var(--vm-dark);">
                                        ₦{{ number_format($ord->total_amount, 2) }}
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <span class="badge" style="background: #EFF6FF; color: #1D4ED8; font-family: monospace; font-size: 14px; font-weight: 800; letter-spacing: 2px; padding: 3px 8px; border-radius: 6px;">
                                            {{ $ord->delivery_otp ?? '819234' }}
                                        </span>
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        @if($ord->order_status === 'delivered')
                                            <span class="badge" style="background: #ECFDF5; color: #047857; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                ✓ Delivered
                                            </span>
                                        @elseif($ord->order_status === 'out_for_delivery')
                                            <span class="badge" style="background: #FEF3C7; color: #B45309; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                ⚡ Out for Delivery
                                            </span>
                                        @else
                                            <span class="badge" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                ● {{ ucwords($ord->order_status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <a href="{{ route('order.status', $ord->order_number) }}" class="btn" style="padding: 4px 10px; font-size: 12px; font-weight: 700; background: var(--vm-primary); color: white; border-radius: 6px; text-decoration: none;">
                                            Track &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--vm-text-muted);">
                                        You have not placed any orders yet. Visit our <a href="{{ route('storefront') }}" style="color: var(--vm-primary); font-weight: 700;">Storefront</a> to start shopping.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection