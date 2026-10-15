@extends('layouts.app')

@section('title', 'My Profile & Account Suite — Victorious MARKET')

@section('content')
<style>
    /* Legacy theme_vmarket Customer Profile Styling */
    .customer-profile-wrapper {
        padding: 32px 0 60px;
    }

    .profile-layout-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 24px;
        align-items: start;
    }

    @media (max-width: 992px) {
        .profile-layout-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Legacy Profile Sidebar */
    .profile-sidebar-sticky {
        position: sticky;
        top: 90px;
        background: #FFFFFF;
        border: 1px solid var(--vm-border);
        border-radius: var(--vm-radius-md);
        box-shadow: var(--vm-shadow-sm);
        padding: 24px 18px;
    }

    .profile-sidebar-avatar-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--vm-border);
        margin-bottom: 16px;
    }

    .profile-sidebar-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--vm-primary-light);
        color: var(--vm-primary);
        font-size: 30px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2.5px solid var(--vm-primary);
        box-shadow: 0 4px 12px var(--vm-primary-glow);
        margin-bottom: 12px;
        position: relative;
    }

    .profile-avatar-camera-badge {
        position: absolute;
        bottom: 0;
        right: 0;
        background: var(--vm-gold);
        color: #0F172A;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        border: 2px solid #FFF;
    }

    .profile-user-name {
        font-size: 17px;
        font-weight: 700;
        color: var(--vm-dark);
        margin-bottom: 2px;
    }

    .profile-user-joined {
        font-size: 12px;
        color: var(--vm-text-muted);
    }

    /* Legacy Profile Menu Links */
    .profile-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .profile-menu li a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: var(--vm-radius-sm);
        color: var(--vm-text);
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 500;
        transition: all 0.15s ease;
        border-left: 3px solid transparent;
    }

    .profile-menu li a:hover {
        background: var(--vm-bg-alt);
        color: var(--vm-primary);
    }

    .profile-menu li.active a {
        background: var(--vm-primary-light);
        border-left-color: var(--vm-primary);
        color: var(--vm-primary);
        font-weight: 700;
    }

    .profile-menu-icon-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Right Canvas Components */
    .profile-canvas {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .legacy-stat-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    @media (max-width: 768px) {
        .legacy-stat-row {
            grid-template-columns: 1fr;
        }
    }

    .legacy-stat-card {
        background: #FFFFFF;
        border: 1px solid var(--vm-border);
        border-radius: var(--vm-radius-md);
        padding: 20px;
        box-shadow: var(--vm-shadow-sm);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .legacy-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--vm-shadow-md);
    }

    .legacy-stat-card-gold-purple {
        background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.15) 100%) !important;
        border: 1.5px solid rgba(255, 215, 0, 0.6) !important;
    }

    .legacy-cashback-banner {
        background: rgba(94, 23, 235, 0.04);
        border: 1.5px dashed rgba(94, 23, 235, 0.3);
        border-radius: var(--vm-radius-md);
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .legacy-card {
        background: #FFFFFF;
        border: 1px solid var(--vm-border);
        border-radius: var(--vm-radius-md);
        box-shadow: var(--vm-shadow-sm);
        padding: 24px;
    }

    .legacy-card-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--vm-border);
    }

    .legacy-card-title {
        font-size: 16px;
        font-weight: 800;
        color: var(--vm-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        background: var(--vm-bg);
        border-radius: var(--vm-radius-sm);
        padding: 18px 20px;
    }

    @media (max-width: 576px) {
        .details-grid {
            grid-template-columns: 1fr;
        }
    }

    .details-item label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--vm-text-muted);
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .details-item span {
        font-size: 14px;
        font-weight: 600;
        color: var(--vm-dark);
    }

    .verification-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        background: #ECFDF5;
        color: #047857;
        margin-left: 6px;
    }
</style>

<div class="container customer-profile-wrapper">
    <!-- Breadcrumb -->
    <div style="font-size: 12.5px; color: var(--vm-text-muted); margin-bottom: 20px; display: flex; align-items: center; gap: 6px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-text-muted); text-decoration: none;">Home</a>
        <span>&rsaquo;</span>
        <span style="color: var(--vm-dark); font-weight: 600;">My Profile &amp; Account Suite</span>
    </div>

    @if (session('status'))
        <div style="background: #ECFDF5; border: 1px solid #6EE7B7; border-radius: var(--vm-radius-md); padding: 12px 18px; margin-bottom: 20px; color: #065F46; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="profile-layout-grid">
        <!-- ========================================================= -->
        <!-- COLUMN 1: LEGACY PROFILE SIDEBAR (_profile-aside)         -->
        <!-- ========================================================= -->
        <aside class="profile-sidebar-sticky">
            <div class="profile-sidebar-avatar-wrap">
                <div class="profile-sidebar-avatar">
                    {{ substr($customer->name ?? 'Emeka', 0, 1) }}
                    <span class="profile-avatar-camera-badge">📷</span>
                </div>
                <h4 class="profile-user-name">{{ $customer->name ?? 'Emeka Okonkwo' }}</h4>
                <p class="profile-user-joined">Joined {{ $customer->created_at ? $customer->created_at->format('d M, Y') : 'Oct 15, 2026' }}</p>
                <div style="margin-top: 6px;">
                    <span class="badge" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 999px; border: 1px solid rgba(94, 23, 235, 0.2);">
                        👑 ACCREDITED SHOPPER
                    </span>
                </div>
            </div>

            <!-- Profile Menu (Legacy theme_vmarket) -->
            <ul class="profile-menu">
                <li class="active">
                    <a href="#personal-details">
                        <span class="profile-menu-icon-title">
                            <span>👤</span>
                            <span>My Profile</span>
                        </span>
                    </a>
                </li>
                <li>
                    <a href="#orders-tracking">
                        <span class="profile-menu-icon-title">
                            <span>🛍️</span>
                            <span>My Orders</span>
                        </span>
                        <span class="badge" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                            {{ $total_order }}
                        </span>
                    </a>
                </li>
                <li>
                    <a href="#pickup-reservations">
                        <span class="profile-menu-icon-title">
                            <span>🏬</span>
                            <span>Pickup Reservations</span>
                        </span>
                        <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                            {{ count($reservations) }}
                        </span>
                    </a>
                </li>
                <li>
                    <a href="#my-addresses">
                        <span class="profile-menu-icon-title">
                            <span>📍</span>
                            <span>My Addresses</span>
                        </span>
                    </a>
                </li>
                <li>
                    <a href="#cashback-wallet">
                        <span class="profile-menu-icon-title">
                            <span>🪙</span>
                            <span>Victorious Cashback</span>
                        </span>
                        <span class="badge" style="background: var(--vm-gold); color: #0F172A; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 999px;">
                            5%
                        </span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('storefront') }}#catalog">
                        <span class="profile-menu-icon-title">
                            <span>❤️</span>
                            <span>Wish List</span>
                        </span>
                        <span style="font-size: 12px; color: var(--vm-text-muted);">{{ $wishlists }}</span>
                    </a>
                </li>
                <li>
                    <a href="https://wa.me/2349118949035" target="_blank">
                        <span class="profile-menu-icon-title">
                            <span>💬</span>
                            <span>Support Tickets</span>
                        </span>
                    </a>
                </li>
                <li style="margin-top: 10px; border-top: 1px solid var(--vm-border); padding-top: 8px;">
                    <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                        @csrf
                        <button type="submit" style="background: none; border: none; width: 100%; text-align: left; padding: 10px 14px; color: #EF4444; font-size: 13.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                            <span>⎋</span>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </li>
            </ul>
        </aside>

        <!-- ========================================================= -->
        <!-- COLUMN 2: LEGACY USER PROFILE CANVAS                      -->
        <!-- ========================================================= -->
        <main class="profile-canvas">
            
            <!-- Top 3 Stat Cards (Legacy theme_vmarket Parity) -->
            <div class="legacy-stat-row">
                <!-- Card 1: Orders -->
                <div class="legacy-stat-card">
                    <h3 style="font-size: 28px; font-weight: 800; color: var(--vm-dark); margin-bottom: 4px;">{{ $total_order }}</h3>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--vm-text-muted);">
                        <span>🛍️</span>
                        <span>Orders</span>
                    </div>
                </div>

                <!-- Card 2: Wishlist -->
                <div class="legacy-stat-card">
                    <h3 style="font-size: 28px; font-weight: 800; color: var(--vm-dark); margin-bottom: 4px;">{{ $wishlists }}</h3>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--vm-text-muted);">
                        <span>❤️</span>
                        <span>Wish List</span>
                    </div>
                </div>

                <!-- Card 3: Victorious Cashback Rewards (Gold & Purple Luxury Card) -->
                <div class="legacy-stat-card legacy-stat-card-gold-purple">
                    <div style="margin-bottom: 4px;">
                        <span style="background: linear-gradient(135deg, #7C3AED 0%, #D4AF37 100%); color: #FFF; font-size: 10.5px; font-weight: 800; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.5px;">
                            ★ 5% REWARDS
                        </span>
                    </div>
                    <h3 style="font-size: 26px; font-weight: 800; color: var(--vm-primary); margin-bottom: 2px;">
                        {{ number_format($total_loyalty_point) }} <span style="font-size: 13px; font-weight: 600; color: var(--vm-text-muted);">PTS</span>
                    </h3>
                    <div style="font-size: 12px; font-weight: 700; color: #78350F; display: flex; align-items: center; gap: 4px;">
                        <span>🪙</span>
                        <span>Victorious Cashback</span>
                    </div>
                    <div style="font-size: 11.5px; color: var(--vm-text-muted); margin-top: 2px;">
                        &asymp; &#8358;{{ number_format($cashbackNairaValue, 2) }} Reward Value
                    </div>
                </div>
            </div>

            <!-- 5% Victorious Cashback Guarantee Banner (Legacy Parity) -->
            <div class="legacy-cashback-banner">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #5E17EB; color: #FFD700; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                        🎁
                    </div>
                    <div>
                        <div style="font-size: 13.5px; font-weight: 800; color: var(--vm-dark);">
                            5% Victorious Cashback Guarantee
                        </div>
                        <div style="font-size: 12px; color: var(--vm-text-muted);">
                            Earn 5% cashback rewards on every completed home delivery or in-shop pickup reservation across Victorious Market.
                        </div>
                    </div>
                </div>
                <a href="{{ route('storefront') }}#catalog" class="btn-accent" style="padding: 7px 18px; font-size: 12px; text-decoration: none;">
                    Shop &amp; Earn &rarr;
                </a>
            </div>

            <!-- Personal Details Card (Legacy Parity) -->
            <div class="legacy-card" id="personal-details">
                <div class="legacy-card-header-row">
                    <h3 class="legacy-card-title">
                        <span>👤</span>
                        <span>Personal Details</span>
                    </h3>
                    <button type="button" class="btn-outline" style="padding: 5px 14px; font-size: 12px; border-radius: 999px;" onclick="alert('Profile editor modal: Update name, phone, or password.')">
                        <span>Edit Profile ✏️</span>
                    </button>
                </div>

                <div class="details-grid">
                    <div class="details-item">
                        <label>First Name</label>
                        <span>{{ $f_name ?? 'Emeka' }}</span>
                    </div>
                    <div class="details-item">
                        <label>Last Name</label>
                        <span>{{ $l_name ?? 'Okonkwo' }}</span>
                    </div>
                    <div class="details-item">
                        <label>Phone Number</label>
                        <span>
                            {{ $customer->phone ?? '+2348055550001' }}
                            <span class="verification-badge">✓ Phone Verified</span>
                        </span>
                    </div>
                    <div class="details-item">
                        <label>Email Address</label>
                        <span>
                            {{ $customer->email ?? 'customer@victoriousmarket.com.ng' }}
                            <span class="verification-badge">✓ Email Verified</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Live Order Multi-Stage Tracking & Orders (Legacy Parity) -->
            @php $latestOrder = $orders->first(); @endphp
            <div class="legacy-card" id="orders-tracking">
                <div class="legacy-card-header-row">
                    <div>
                        <h3 class="legacy-card-title">
                            <span>📦</span>
                            <span>Live Order Multi-Stage Tracking</span>
                        </h3>
                        <p style="font-size: 12px; color: var(--vm-text-muted); margin-top: 2px;">
                            Track your package from physical in-shop inspection to dual OTP doorstep handover.
                        </p>
                    </div>
                    @if($latestOrder)
                        <span class="badge badge-purple" style="font-size: 11.5px; padding: 4px 12px;">
                            Order #{{ $latestOrder->order_number }}
                        </span>
                    @endif
                </div>

                @if($latestOrder)
                    <!-- 5-Stage Stepper -->
                    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 20px; text-align: center;">
                        <div style="background: #ECFDF5; border: 1.5px solid #10B981; border-radius: 8px; padding: 10px 4px;">
                            <span style="font-size: 15px;">💳</span>
                            <div style="font-size: 11px; font-weight: 700; color: #065F46; margin-top: 2px;">1. Escrow Held</div>
                            <span style="font-size: 9.5px; color: #047857;">Paystack Secured</span>
                        </div>

                        <div style="background: #ECFDF5; border: 1.5px solid #10B981; border-radius: 8px; padding: 10px 4px;">
                            <span style="font-size: 15px;">🔎</span>
                            <div style="font-size: 11px; font-weight: 700; color: #065F46; margin-top: 2px;">2. Inspected</div>
                            <span style="font-size: 9.5px; color: #047857;">In-Shop Passed</span>
                        </div>

                        <div style="background: #ECFDF5; border: 1.5px solid #10B981; border-radius: 8px; padding: 10px 4px;">
                            <span style="font-size: 15px;">🏬</span>
                            <div style="font-size: 11px; font-weight: 700; color: #065F46; margin-top: 2px;">3. Handover</div>
                            <span style="font-size: 9.5px; color: #047857;">Rider Custody</span>
                        </div>

                        <div style="background: {{ in_array($latestOrder->order_status, ['out_for_delivery', 'delivered']) ? '#F0F9FF' : '#F8FAFC' }}; border: 1.5px solid {{ in_array($latestOrder->order_status, ['out_for_delivery', 'delivered']) ? '#0284C7' : '#E2E8F0' }}; border-radius: 8px; padding: 10px 4px;">
                            <span style="font-size: 15px;">🚚</span>
                            <div style="font-size: 11px; font-weight: 700; color: #0369A1; margin-top: 2px;">4. In LGA Transit</div>
                            <span style="font-size: 9.5px; color: var(--vm-text-muted);">Uyo Corridor</span>
                        </div>

                        <div style="background: {{ $latestOrder->order_status === 'delivered' ? '#ECFDF5' : '#FFFBEB' }}; border: 1.5px solid {{ $latestOrder->order_status === 'delivered' ? '#10B981' : '#F59E0B' }}; border-radius: 8px; padding: 10px 4px;">
                            <span style="font-size: 15px;">🔑</span>
                            <div style="font-size: 11px; font-weight: 700; color: {{ $latestOrder->order_status === 'delivered' ? '#065F46' : '#B45309' }}; margin-top: 2px;">5. Doorstep OTP</div>
                            <span style="font-size: 9.5px; color: var(--vm-text-muted);">Dual Handshake</span>
                        </div>
                    </div>

                    <!-- Customer Private Delivery Verification OTP Box -->
                    <div style="background: linear-gradient(135deg, #0F172A 0%, #1A0F35 100%); border-radius: var(--vm-radius-md); padding: 18px 22px; color: #FFF; border: 1.5px solid var(--vm-gold); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--vm-gold); text-transform: uppercase; letter-spacing: 0.5px;">
                                YOUR PRIVATE DELIVERY VERIFICATION OTP:
                            </span>
                            <div style="font-family: monospace; font-size: 30px; font-weight: 800; color: #FFFFFF; letter-spacing: 5px; margin: 2px 0;">
                                {{ $latestOrder->verification_code ?? '829104' }}
                            </div>
                            <span style="font-size: 11.5px; color: #94A3B8;">
                                ⚠️ Provide this code to the courier <strong>only after</strong> examining your delivered package at the doorstep.
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge" style="background: rgba(255, 215, 0, 0.2); color: var(--vm-gold); border: 1px solid var(--vm-gold); font-size: 11.5px; font-weight: 700; padding: 6px 14px; border-radius: 999px;">
                                🪙 +&#8358;{{ number_format($latestOrder->customer_cashback_5, 2) }} Cashback Pending
                            </span>
                        </div>
                    </div>
                @else
                    <div style="text-align: center; padding: 30px; color: var(--vm-text-muted);">
                        No orders placed yet. Browse the storefront to purchase authentic items!
                    </div>
                @endif

                <!-- Orders Table (Legacy Parity) -->
                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px;">Order History:</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1.5px solid var(--vm-border); text-align: left; color: var(--vm-text-muted); font-size: 11.5px; text-transform: uppercase;">
                                <th style="padding: 10px 12px;">Order #</th>
                                <th style="padding: 10px 12px;">Merchant Plaza</th>
                                <th style="padding: 10px 12px;">Total</th>
                                <th style="padding: 10px 12px;">Type</th>
                                <th style="padding: 10px 12px;">Status</th>
                                <th style="padding: 10px 12px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr style="border-bottom: 1px solid var(--vm-border-light);">
                                    <td style="padding: 12px; font-family: monospace; font-weight: 700;">
                                        <a href="{{ route('order.status', ['order_number' => $order->order_number]) }}" style="color: var(--vm-primary); text-decoration: underline;" title="View Live Tracking & OTP">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td style="padding: 12px;">{{ $order->shop->name ?? 'Victor Tech Plaza' }}</td>
                                    <td style="padding: 12px;"><strong>&#8358;{{ number_format($order->grand_total ?? $order->total_amount, 2) }}</strong></td>
                                    <td style="padding: 12px;">
                                        <span class="badge" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 11px;">
                                            {{ ucfirst(str_replace('_', ' ', $order->fulfillment_type)) }}
                                        </span>
                                    </td>
                                    <td style="padding: 12px;">
                                        @if($order->order_status === 'delivered')
                                            <span class="badge badge-success" style="font-size: 11px;">Delivered ✓</span>
                                        @elseif($order->order_status === 'out_for_delivery')
                                            <span class="badge badge-purple" style="font-size: 11px;">In Transit 🚚</span>
                                        @else
                                            <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 11px;">{{ ucfirst($order->order_status) }}</span>
                                        @endif
                                    </td>
                                    <td style="padding: 12px; text-align: right;">
                                        <a href="{{ route('order.status', ['order_number' => $order->order_number]) }}" class="btn-accent" style="padding: 4px 10px; font-size: 11px; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>Track &amp; OTP</span>
                                            <span>🔑</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px; color: var(--vm-text-muted);">No orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- In-Shop Pickup Reservations (Legacy Parity) -->
            <div class="legacy-card" id="pickup-reservations">
                <div class="legacy-card-header-row">
                    <div>
                        <h3 class="legacy-card-title">
                            <span>🏬</span>
                            <span>Pickup Reservations (In-Shop Physical Inspection)</span>
                        </h3>
                        <p style="font-size: 12px; color: var(--vm-text-muted); margin-top: 2px;">
                            Manage your in-store pickup reservations. Present your code at the plaza counter for examination.
                        </p>
                    </div>
                    <span class="badge badge-gold" style="font-size: 11px;">Inspect Before Pay</span>
                </div>

                @forelse($reservations as $res)
                    <div style="border: 1px solid var(--vm-border); border-radius: var(--vm-radius-sm); padding: 16px; margin-bottom: 12px; background: #FFF;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                            <span style="font-family: monospace; font-size: 16px; font-weight: 800; color: var(--vm-primary);">
                                {{ $res->reservation_code }}
                            </span>
                            <div>
                                @if($res->status === 'inspected')
                                    <span class="badge badge-success" style="font-size: 11px;">✓ Inspected &amp; Passed</span>
                                @else
                                    <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 11px;">⏳ Awaiting In-Shop Inspection</span>
                                @endif
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <div style="font-size: 13.5px; font-weight: 700; color: var(--vm-dark);">{{ $res->product->name ?? 'Verified Electronic Item' }}</div>
                                <div style="font-size: 12px; color: var(--vm-text-muted);">📍 {{ $res->shop->name ?? 'Victor Tech Uyo Flagship Plaza' }} &bull; 42 Ikot Ekpene Road, Uyo</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 15px; font-weight: 800; color: var(--vm-dark);">&#8358;{{ number_format($res->total_amount ?? ($res->product->unit_price ?? 32000), 2) }}</div>
                                <button type="button" class="btn-accent" style="padding: 4px 12px; font-size: 11px; margin-top: 4px;" onclick="alert('Trip signal sent! Plaza counter staff notified you are en route. Stock held for 90m.')">
                                    🚗 I Am Coming Now
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <p style="text-align: center; color: var(--vm-text-muted); padding: 20px;">No pickup reservations active right now.</p>
                @endforelse
            </div>

            <!-- 5% Sovereign Cashback Rewards Wallet (Legacy Parity) -->
            <div class="legacy-card" id="cashback-wallet">
                <div class="legacy-card-header-row">
                    <div>
                        <h3 class="legacy-card-title">
                            <span>🪙</span>
                            <span>5% Sovereign Cashback Rewards Wallet</span>
                        </h3>
                        <p style="font-size: 12px; color: var(--vm-text-muted); margin-top: 2px;">
                            5% automatic platform-funded cashback on every verified purchase in Akwa Ibom.
                        </p>
                    </div>
                    <span class="badge badge-gold" style="font-size: 11px;">Instant Reward</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 20px;">
                    <div style="background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: var(--vm-radius-md); padding: 18px;">
                        <span style="font-size: 11px; font-weight: 800; color: #B45309; text-transform: uppercase;">Available Cashback Balance</span>
                        <div style="font-size: 26px; font-weight: 800; color: #78350F; margin: 4px 0;">
                            &#8358;{{ number_format((float)$customerMetrics['cashback_available'], 2) }}
                        </div>
                        <button type="button" class="btn-accent" style="width: 100%; justify-content: center; font-size: 12px; padding: 7px;" onclick="alert('Promo code VM-REWARD-5 copied! Paste at checkout to apply ₦{{ number_format((float)$customerMetrics['cashback_available'], 2) }} reward.')">
                            🎟️ Apply at Checkout
                        </button>
                    </div>

                    <div style="background: var(--vm-bg); border: 1px solid var(--vm-border); border-radius: var(--vm-radius-md); padding: 18px;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--vm-text-muted); text-transform: uppercase;">Pending Maturation (24h Buffer)</span>
                        <div style="font-size: 26px; font-weight: 800; color: var(--vm-dark); margin: 4px 0;">
                            &#8358;{{ number_format((float)$customerMetrics['cashback_pending'], 2) }}
                        </div>
                        <span style="font-size: 11px; color: var(--vm-text-muted);">Automatically matures after the 24-hour return window expires.</span>
                    </div>
                </div>

                <h4 style="font-size: 13.5px; font-weight: 700; margin-bottom: 10px;">Cashback Rewards History:</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr style="border-bottom: 1.5px solid var(--vm-border); text-align: left; color: var(--vm-text-muted); font-size: 11px; text-transform: uppercase;">
                                <th style="padding: 8px 10px;">Order #</th>
                                <th style="padding: 8px 10px;">Cashback Amount</th>
                                <th style="padding: 8px 10px;">Status</th>
                                <th style="padding: 8px 10px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cashbackLedger as $entry)
                                <tr style="border-bottom: 1px solid var(--vm-border-light);">
                                    <td style="padding: 10px; font-family: monospace; font-weight: 700;">
                                        Order #{{ $entry->order->order_number ?? 'VM-908231' }}
                                    </td>
                                    <td style="padding: 10px;">
                                        <strong style="color: #B45309;">+&#8358;{{ number_format($entry->amount, 2) }}</strong>
                                    </td>
                                    <td style="padding: 10px;">
                                        @if($entry->status === 'matured')
                                            <span class="badge badge-success" style="font-size: 10.5px;">Cleared &amp; Ready ✓</span>
                                        @else
                                            <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 10.5px;">Maturing in 24h ⏳</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px; color: var(--vm-text-muted); font-size: 11.5px;">
                                        {{ $entry->created_at ? $entry->created_at->diffForHumans() : 'Just now' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--vm-text-muted); padding: 16px;">No cashback transactions recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- My Addresses Section (Legacy theme_vmarket Parity) -->
            <div class="legacy-card" id="my-addresses">
                <div class="legacy-card-header-row">
                    <div>
                        <h3 class="legacy-card-title">
                            <span>📍</span>
                            <span>My Addresses</span>
                        </h3>
                        <p style="font-size: 12px; color: var(--vm-text-muted); margin-top: 2px;">
                            Saved Akwa Ibom delivery addresses for rapid checkout and directional lane courier dispatch.
                        </p>
                    </div>
                    <button type="button" class="btn-outline" style="padding: 5px 14px; font-size: 12px; border-radius: 999px;" onclick="alert('Add address modal: Select LGA, enter street address and phone.')">
                        <span>+ Add Address</span>
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                    <div style="border: 1.5px solid var(--vm-primary); border-radius: var(--vm-radius-sm); padding: 16px; background: #FFF;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: var(--vm-dark); font-size: 14px;">Home (Default Shipping Address)</strong>
                            <span class="badge badge-purple" style="font-size: 10px;">DEFAULT</span>
                        </div>
                        <address style="font-style: normal; font-size: 13px; color: var(--vm-text-muted); line-height: 1.5; margin-bottom: 8px;">
                            <div><strong>Emeka Okonkwo</strong></div>
                            <div>12 Hospital Road, Uyo, Akwa Ibom State</div>
                            <div>📞 +2348055550001</div>
                        </address>
                        <div style="display: flex; gap: 8px; font-size: 11.5px;">
                            <a href="javascript:" onclick="alert('Edit address modal')" style="color: var(--vm-primary); font-weight: 600; text-decoration: none;">Edit</a>
                            <span>&bull;</span>
                            <a href="javascript:" onclick="alert('Address is default')" style="color: var(--vm-text-muted); text-decoration: none;">Primary Address</a>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
@endsection
