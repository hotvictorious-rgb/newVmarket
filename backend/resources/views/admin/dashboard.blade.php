@extends('layouts.dashboard')

@section('title', 'Super Admin Command Tower')
@section('role_title', 'Super Admin')
@section('page_title', 'Platform Sovereign Command Tower')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    <li class="nav-section-title">GOVERNANCE &amp; CONTROL</li>
    <li class="sidebar-nav-item">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-nav-link active">
            <span class="nav-link-icon">👑</span>
            <span>Command Tower</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#settlement-desk" class="sidebar-nav-link">
            <span class="nav-link-icon">💰</span>
            <span>90/5/5 Settlement Desk</span>
            <span class="nav-link-badge">{{ count($pendingSettlements) }}</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#announcement-settings" class="sidebar-nav-link">
            <span class="nav-link-icon">📢</span>
            <span>Announcement Bar &amp; Care</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="#delivery-lanes" class="sidebar-nav-link">
            <span class="nav-link-icon">🛣️</span>
            <span>Directional LGA Lanes</span>
        </a>
    </li>

    <li class="nav-section-title">ECOSYSTEM ACTORS</li>
    <li class="sidebar-nav-item">
        <a href="#recent-orders" class="sidebar-nav-link">
            <span class="nav-link-icon">📦</span>
            <span>Platform Escrow Orders</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="{{ route('vendor.dashboard') }}" class="sidebar-nav-link">
            <span class="nav-link-icon">🏪</span>
            <span>Inspect Merchant Desk</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="{{ route('logistics.dashboard') }}" class="sidebar-nav-link">
            <span class="nav-link-icon">🚚</span>
            <span>Inspect Logistics Fleet</span>
        </a>
    </li>
    <li class="sidebar-nav-item">
        <a href="{{ route('counter.dashboard') }}" class="sidebar-nav-link">
            <span class="nav-link-icon">🏬</span>
            <span>Inspect Counter Terminals</span>
        </a>
    </li>
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="VM" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; border: 1.5px solid var(--adm-gold);">
                <span>Platform Sovereign Command Tower</span>
            </h1>
            <p class="page-header-subtitle">
                Central Governance, Directional Lane Matrix, Treasury Escrow, and 90/5/5 Mathematical Enforcement.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Zero-Trust Server Gate Active
            </span>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Escrow Orders</span>
                <span class="stat-card-value">{{ $metrics['active_orders'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Prepaid via Paystack</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📦</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accredited Merchants</span>
                <span class="stat-card-value">{{ $metrics['total_vendors'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">{{ $metrics['total_shops'] }} Verified Uyo Counters</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏪</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Settlements in 24h Hold</span>
                <span class="stat-card-value">{{ $metrics['held_settlements'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Dispute Protection Buffer</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">⏳</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Customer Cashback Ledger</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)$metrics['total_cashback_issued'], 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">5% Platform Funded</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🪙</div>
        </div>
    </div>

    <!-- Control Center 1: Storefront Announcement & Customer Care Settings -->
    <div class="dash-card" id="announcement-settings" style="border: 1.5px solid rgba(94, 23, 235, 0.25);">
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.05) 100%);">
            <div class="dash-card-title">
                <span style="font-size: 18px;">📢</span>
                <div>
                    <div>Storefront Announcement &amp; Support Control Center</div>
                    <span class="dash-card-subtitle">Configure ordering hotlines, promotional discount tickers, and live broadcast banners across all customer pages.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Live Broadcast Engine</span>
        </div>

        <div class="dash-card-body">
            <!-- Real-Time Top Bar Live Preview -->
            <div style="background: #0F172A; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; border: 1px solid rgba(255, 255, 255, 0.1);">
                <div style="font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    Storefront Top Bar Live Preview:
                </div>
                <div id="vmTopBarPreview" style="background: {{ $announcementSettings['announcement_bg_color'] ?? '#0F172A' }}; color: #E2E8F0; padding: 10px 16px; border-radius: 6px; font-size: 12.5px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255, 255, 255, 0.12);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="previewBadge" style="font-size: 14px;">{{ $announcementSettings['announcement_badge'] ?? '💬' }}</span>
                        <span id="previewText" style="font-weight: 600;">{!! $announcementSettings['announcement_text'] ?? 'Call or Whatsapp 09118949035 to order' !!}</span>
                    </div>
                    <div style="font-size: 11.5px; color: #94A3B8; display: flex; gap: 12px;">
                        <span>Become a Vendor</span>
                        <span>•</span>
                        <span>Help &amp; Support</span>
                    </div>
                </div>
            </div>

            <!-- Quick Campaign Presets -->
            <div style="margin-bottom: 20px;">
                <span style="font-size: 12px; font-weight: 600; color: var(--adm-text-muted); display: block; margin-bottom: 8px;">
                    Quick Campaign Presets (Click to autofill):
                </span>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="preset-btn btn-dash btn-dash-outline" data-type="phone_order" data-badge="💬" data-text="Call or Whatsapp 09118949035 to order" data-link="https://wa.me/2349118949035" data-bg="#0F172A">
                        📞 WhatsApp/Call 09118949035
                    </button>
                    <button type="button" class="preset-btn btn-dash btn-dash-outline" data-type="flash_sale" data-badge="⚡" data-text="Flash Deals: Up to 15% off electronics in Uyo today!" data-link="#catalog" data-bg="#5E17EB">
                        ⚡ Flash Deals 15% Off
                    </button>
                    <button type="button" class="preset-btn btn-dash btn-dash-outline" data-type="delivery_special" data-badge="🚚" data-text="Directional Logistics: Flat ₦500 express delivery across Uyo LGA" data-link="#catalog" data-bg="#065F46">
                        🚚 Flat ₦500 Uyo Delivery
                    </button>
                    <button type="button" class="preset-btn btn-dash btn-dash-outline" data-type="cashback_promo" data-badge="🪙" data-text="Earn 5% Instant Cashback on all authentic verified items" data-link="#catalog" data-bg="#78350F">
                        🪙 5% Sovereign Cashback
                    </button>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" action="{{ route('admin.settings.announcement') }}">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 18px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Support Phone Number</label>
                        <input type="text" name="support_phone" id="inputPhone" value="{{ $announcementSettings['support_phone'] }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--adm-border); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Support WhatsApp Number</label>
                        <input type="text" name="support_whatsapp" id="inputWhatsapp" value="{{ $announcementSettings['support_whatsapp'] }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--adm-border); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Support Official Email</label>
                        <input type="email" name="support_email" value="{{ $announcementSettings['support_email'] }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--adm-border); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Campaign Category</label>
                        <select name="announcement_type" id="selectType" style="width: 100%; padding: 8px 12px; border: 1px solid var(--adm-border); border-radius: 6px; background: #FFF;">
                            <option value="phone_order" {{ $announcementSettings['announcement_type'] == 'phone_order' ? 'selected' : '' }}>WhatsApp &amp; Phone Ordering</option>
                            <option value="flash_sale" {{ $announcementSettings['announcement_type'] == 'flash_sale' ? 'selected' : '' }}>Flash Sales &amp; Discounts</option>
                            <option value="delivery_special" {{ $announcementSettings['announcement_type'] == 'delivery_special' ? 'selected' : '' }}>Directional Delivery Advisory</option>
                            <option value="cashback_promo" {{ $announcementSettings['announcement_type'] == 'cashback_promo' ? 'selected' : '' }}>5% Cashback Promotion</option>
                            <option value="custom_notice" {{ $announcementSettings['announcement_type'] == 'custom_notice' ? 'selected' : '' }}>Custom Broadcast Message</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 80px 1fr 140px; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Badge</label>
                        <input type="text" name="announcement_badge" id="inputBadge" value="{{ $announcementSettings['announcement_badge'] }}" style="width: 100%; padding: 8px; text-align: center; border: 1px solid var(--adm-border); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Announcement Message Text</label>
                        <input type="text" name="announcement_text" id="inputText" value="{{ $announcementSettings['announcement_text'] }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--adm-border); border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Bar Color</label>
                        <input type="color" name="announcement_bg_color" id="inputBg" value="{{ $announcementSettings['announcement_bg_color'] }}" style="width: 100%; height: 38px; padding: 2px; border: 1px solid var(--adm-border); border-radius: 6px; cursor: pointer;">
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="announcement_is_active" value="1" {{ $announcementSettings['announcement_is_active'] ? 'checked' : '' }}>
                        <span>Broadcast Active on Customer Storefront</span>
                    </label>

                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 8px 20px;">
                        <span>✓ Save &amp; Broadcast Instantly</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manual 90% Settlement Desk (Escrow Disbursal) -->
    <div class="dash-card" id="settlement-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>💰</span>
                <div>
                    <div>Manual Settlement Desk (90% Vendor Disbursal)</div>
                    <span class="dash-card-subtitle">Disburse cleared orders to verified merchant bank accounts following inspection pass &amp; custody handshake.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Cleared Escrow Buffer</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Vendor Merchant</th>
                        <th>Registered Bank Details</th>
                        <th>Gross Order</th>
                        <th>90% Vendor Share</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingSettlements as $order)
                        <tr>
                            <td>
                                <strong style="font-family: monospace;">{{ $order->order_number }}</strong>
                                <div style="font-size: 11px; color: var(--adm-text-muted);">{{ $order->created_at->diffForHumans() }}</div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $order->vendor->business_name ?? 'Vendor' }}</div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Customer: {{ $order->customer->name ?? 'Shopper' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $order->vendor->bank_name ?? 'Zenith Bank' }}</span>
                                <div style="font-family: monospace; font-weight: 700; margin-top: 3px;">
                                    {{ $order->vendor->account_number ?? '1012345678' }}
                                </div>
                            </td>
                            <td>&#8358;{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <strong style="color: var(--adm-success); font-size: 14px;">
                                    &#8358;{{ number_format($order->vendor_earning_90, 2) }}
                                </strong>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.disburse.settlement') }}">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                                    <button type="submit" class="btn-dash btn-dash-success" onclick="return confirm('Confirm 90% disbursement of ₦{{ number_format($order->vendor_earning_90, 2) }} to {{ $order->vendor->business_name }}?')">
                                        <span>💸 Disburse 90%</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 24px; color: var(--adm-text-muted);">
                                No orders currently pending manual settlement disbursement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Directional Delivery Lanes Matrix -->
        <!-- MASTER SECTION: 3-Tier Zonal Distance Routing & Directional Delivery Lanes Matrix -->
    <div class="dash-card" id="delivery-lanes" style="border: 1.5px solid rgba(94, 23, 235, 0.25);">
        
        <!-- Header with Sub-Tabs -->
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(94, 23, 235, 0.05) 0%, rgba(255, 215, 0, 0.06) 100%); flex-wrap: wrap; gap: 12px;">
            <div class="dash-card-title">
                <span style="font-size: 22px;">🛣️</span>
                <div>
                    <div style="font-size: 16px; font-weight: 800;">Delivery Lanes &amp; National Shipping Matrix</div>
                    <span class="dash-card-subtitle">
                        3-Tier Zonal Distance Routing &amp; Bidirectional Custom Corridors across 774 LGAs in Nigeria.
                    </span>
                </div>
            </div>
            
            <!-- Tab Switcher Pills -->
            <div style="display: flex; gap: 8px; background: rgba(15, 23, 42, 0.05); padding: 4px; border-radius: 8px;">
                <button type="button" class="btn-dash btn-dash-primary" id="btnTabCorridors" onclick="switchLaneTab('corridors')" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                    🚲 Custom LGA Corridors ({{ count($deliveryLanes) }})
                </button>
                <button type="button" class="btn-dash btn-dash-outline" id="btnTabZonal" onclick="switchLaneTab('zonal')" style="padding: 6px 14px; font-size: 12px; font-weight: 700; background: #FFF;">
                    ⚙️ 3-Tier Zonal Baseline Rates
                </button>
            </div>
        </div>

        <div class="dash-card-body" style="padding: 24px;">

            <!-- ============================================================ -->
            <!-- TAB 1: 3-TIER ZONAL BASELINE MATRIX (Guarantees 100% Coverage) -->
            <!-- ============================================================ -->
            <div id="vmZonalRatesTab" style="display: none;">
                <div style="background: #F8FAFC; border: 1px solid var(--adm-border); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px;">
                    <div style="font-weight: 700; color: #0F172A; font-size: 13.5px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <span style="color: #059669;">🛡️</span> 100% Nationwide Fail-Safe Distance Architecture
                    </div>
                    <p style="font-size: 12px; color: #64748B; margin: 0; line-height: 1.5;">
                        Victorious Market classifies all delivery orders into Tier 1, Tier 2, or Tier 3 automatically based on physical LGA/State distance unless a specific custom corridor override has been saved below. This prevents any checkout blockages nationwide.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.delivery_lanes.zonal') }}">
                    @csrf
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 24px;">
                        
                        <!-- Tier 1: Intra-LGA (Municipal) -->
                        <div style="background: #F5F3FF; border: 1.5px solid #DDD6FE; border-radius: 10px; padding: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div style="font-weight: 800; font-size: 13.5px; color: var(--adm-primary);">🚲 Tier 1: Intra-LGA (Municipal)</div>
                                <span class="dash-badge dash-badge-purple">Tier 1</span>
                            </div>
                            <p style="font-size: 11.5px; color: #64748B; margin-bottom: 14px; line-height: 1.4;">
                                Applied when merchant and buyer are in the <strong>SAME LGA</strong> (e.g. Uyo ⟷ Uyo). Express bike dispatch.
                            </p>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Base Delivery Fee (₦)</label>
                                <input type="number" step="50" name="zone_intra_lga_fee" value="{{ $zonalSettings['zone_intra_lga_fee'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Estimated Lead Time</label>
                                <input type="text" name="zone_intra_lga_eta" value="{{ $zonalSettings['zone_intra_lga_eta'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                        </div>

                        <!-- Tier 2: Inter-LGA (Regional) -->
                        <div style="background: #EFF6FF; border: 1.5px solid #BFDBFE; border-radius: 10px; padding: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div style="font-weight: 800; font-size: 13.5px; color: #1D4ED8;">🚚 Tier 2: Inter-LGA (Regional)</div>
                                <span class="dash-badge dash-badge-info">Tier 2</span>
                            </div>
                            <p style="font-size: 11.5px; color: #64748B; margin-bottom: 14px; line-height: 1.4;">
                                Applied when merchant and buyer are in the <strong>SAME State, but DIFFERENT LGAs</strong> (e.g. Uyo ⟷ Eket).
                            </p>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Base Delivery Fee (₦)</label>
                                <input type="number" step="50" name="zone_inter_lga_fee" value="{{ $zonalSettings['zone_inter_lga_fee'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Estimated Lead Time</label>
                                <input type="text" name="zone_inter_lga_eta" value="{{ $zonalSettings['zone_inter_lga_eta'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                        </div>

                        <!-- Tier 3: Inter-State (National Freight) -->
                        <div style="background: #FAF5FF; border: 1.5px solid #E9D5FF; border-radius: 10px; padding: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div style="font-weight: 800; font-size: 13.5px; color: #7C3AED;">✈️ Tier 3: Inter-State (National)</div>
                                <span class="dash-badge dash-badge-purple">Tier 3</span>
                            </div>
                            <p style="font-size: 11.5px; color: #64748B; margin-bottom: 14px; line-height: 1.4;">
                                Applied across <strong>DIFFERENT States</strong> (e.g. Akwa Ibom ⟷ Lagos). Interstate park and courier freight.
                            </p>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Base Delivery Fee (₦)</label>
                                <input type="number" step="100" name="zone_inter_state_fee" value="{{ $zonalSettings['zone_inter_state_fee'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Estimated Lead Time</label>
                                <input type="text" name="zone_inter_state_eta" value="{{ $zonalSettings['zone_inter_state_eta'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                        </div>

                        <!-- Bulky Cargo Surcharge Addon -->
                        <div style="background: #FFFBEB; border: 1.5px solid #FDE68A; border-radius: 10px; padding: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div style="font-weight: 800; font-size: 13.5px; color: #B45309;">📦 Bulky Cargo Surcharge</div>
                                <span class="dash-badge dash-badge-gold">Addon</span>
                            </div>
                            <p style="font-size: 11.5px; color: #64748B; margin-bottom: 14px; line-height: 1.4;">
                                Appended automatically across all tiers when a product is classified as <strong>bulky package tier</strong>.
                            </p>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Bulky Surcharge Amount (₦)</label>
                                <input type="number" step="100" name="zone_bulky_cargo_surcharge" value="{{ $zonalSettings['zone_bulky_cargo_surcharge'] }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            </div>
                            <div style="font-size: 11px; color: #92400E; margin-top: 14px;">
                                ✓ Applies on refrigerators, generators &amp; large displays.
                            </div>
                        </div>

                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 24px; font-weight: 700;">
                            ✓ Save 3-Tier Zonal Distance Baseline Rates
                        </button>
                    </div>
                </form>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: TWO-COLUMN MASTER-DETAIL CUSTOM OVERRIDE CORRIDORS    -->
            <!-- ============================================================ -->
            <div id="vmCorridorsTab">
                <div style="display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start;">
                    
                    <!-- LEFT COLUMN: ADD CUSTOM OVERRIDE CORRIDOR FORM -->
                    <div style="background: #FFFFFF; border: 1.5px solid var(--adm-border); border-radius: 12px; padding: 20px; box-shadow: var(--adm-shadow-sm);">
                        <div style="font-weight: 800; font-size: 14px; color: #0F172A; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <span>➕</span> Add Custom Corridor Override
                        </div>
                        <p style="font-size: 11.5px; color: #64748B; margin-bottom: 16px;">
                            Create high-priority custom pricing between specific LGA pairs.
                        </p>

                        <form method="POST" action="{{ route('admin.delivery_lanes.store') }}">
                            @csrf
                            
                            <!-- Origin Card -->
                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #0F172A; margin-bottom: 6px;">
                                    🛫 Origin LGA (Merchant Outlet) *
                                </label>
                                <select name="origin_lga_id" id="laneOriginSelect" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF; font-size: 13px;">
                                    @foreach($allLgas ?? [] as $lga)
                                        <option value="{{ $lga->id }}" {{ $lga->name == 'Uyo' ? 'selected' : '' }}>{{ $lga->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Destination Card -->
                            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                                <label style="display: block; font-size: 11.5px; font-weight: 700; color: #1D4ED8; margin-bottom: 6px;">
                                    🛬 Destination LGA (Customer Doorstep) *
                                </label>
                                <select name="destination_lga_id" id="laneDestSelect" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF; font-size: 13px;">
                                    @foreach($allLgas ?? [] as $lga)
                                        <option value="{{ $lga->id }}" {{ $lga->name == 'Eket' ? 'selected' : '' }}>{{ $lga->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Terms -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Base Tariff (₦) *</label>
                                    <input type="number" step="50" name="base_fee" value="1200" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Bulky Surcharge (₦)</label>
                                    <input type="number" step="50" name="bulky_surcharge" value="1000" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Min Hours *</label>
                                    <input type="number" name="estimated_delivery_hours_min" value="2" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Max Hours *</label>
                                    <input type="number" name="estimated_delivery_hours_max" value="6" required style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                            </div>

                            <!-- Bidirectional Checkbox -->
                            <div style="background: #ECFDF5; border: 1.5px solid #A7F3D0; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12px; font-weight: 700; color: #065F46;">
                                    <input type="checkbox" name="is_bidirectional" value="1" checked style="margin-top: 2px;">
                                    <div>
                                        <span>⇄ Bidirectional Corridor (A ⇄ B)</span>
                                        <div style="font-size: 11px; font-weight: normal; color: #047857; margin-top: 2px;">
                                            Automatically creates symmetric rates in both directions.
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <button type="submit" class="btn-dash btn-dash-primary" style="width: 100%; padding: 11px; font-weight: 700; font-size: 13px;">
                                + Save Custom Corridor
                            </button>
                        </form>
                    </div>

                    <!-- RIGHT COLUMN: ACTIVE CORRIDORS LIST & SEARCH -->
                    <div>
                        <!-- Search and Quick Filter Bar -->
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px;">
                            <div style="position: relative; flex: 1;">
                                <input type="text" id="vmLaneSearchInput" onkeyup="filterLaneRows()" placeholder="🔍 Filter corridors by LGA name..." style="width: 100%; padding: 9px 14px 9px 36px; border-radius: 8px; border: 1px solid var(--adm-border); font-size: 13px;">
                                <span style="position: absolute; left: 12px; top: 10px; color: #94A3B8; font-size: 13px;">🔍</span>
                            </div>
                            <span class="dash-badge dash-badge-purple" style="font-size: 12px; padding: 6px 12px;">
                                {{ count($deliveryLanes) }} Custom Overrides
                            </span>
                        </div>

                        <!-- Corridors Table -->
                        <div class="dash-table-responsive" style="background: #FFFFFF; border: 1px solid var(--adm-border); border-radius: 10px; overflow: hidden;">
                            <table class="dash-table" id="vmLanesTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Origin LGA</th>
                                        <th style="text-align: center;">Direction</th>
                                        <th>Destination LGA</th>
                                        <th>Base Tariff</th>
                                        <th>Lead Time</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deliveryLanes as $idx => $lane)
                                        <tr class="lane-row" data-origin="{{ strtolower($lane->originLga->name ?? '') }}" data-dest="{{ strtolower($lane->destinationLga->name ?? '') }}">
                                            <td style="font-size: 12px; color: var(--adm-text-muted);">{{ $idx + 1 }}</td>
                                            <td>
                                                <strong style="color: var(--adm-text);">{{ $lane->originLga->name ?? 'Uyo' }}</strong>
                                            </td>
                                            <td style="text-align: center;">
                                                @if(!empty($lane->is_bidirectional))
                                                    <span class="dash-badge dash-badge-success" style="font-size: 11px; padding: 3px 8px;" title="Bidirectional: Applies both ways">
                                                        ⇄ Bidirectional
                                                    </span>
                                                @else
                                                    <span class="dash-badge dash-badge-purple" style="font-size: 11px; padding: 3px 8px;" title="One-Way Directional">
                                                        ➔ Directional
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="dash-badge dash-badge-info" style="font-weight: 700;">{{ $lane->destinationLga->name ?? 'Eket' }}</span>
                                            </td>
                                            <td>
                                                <strong style="color: #0F172A; font-size: 13.5px;">&#8358;{{ number_format((float)$lane->base_fee, 2) }}</strong>
                                                @if((float)$lane->bulky_surcharge > 0)
                                                    <div style="font-size: 10.5px; color: #B45309;">+&#8358;{{ number_format((float)$lane->bulky_surcharge) }} bulky</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="dash-badge dash-badge-purple" style="font-size: 11.5px;">
                                                    {{ $lane->estimated_delivery_hours_min }}-{{ $lane->estimated_delivery_hours_max }} hrs
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <form method="POST" action="{{ route('admin.delivery_lanes.toggle', $lane->id) }}" style="margin: 0; display: inline;">
                                                    @csrf
                                                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;">
                                                        @if($lane->is_active)
                                                            <span class="dash-badge dash-badge-success" style="cursor: pointer;">● Active</span>
                                                        @else
                                                            <span class="dash-badge dash-badge-danger" style="cursor: pointer;">● Inactive</span>
                                                        @endif
                                                    </button>
                                                </form>
                                            </td>
                                            <td style="text-align: right;">
                                                <form method="POST" action="{{ route('admin.delivery_lanes.delete', $lane->id) }}" onsubmit="return confirm('Delete this corridor?');" style="margin: 0; display: inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-dash btn-dash-danger" style="padding: 3px 8px; font-size: 11px;">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 24px; color: var(--adm-text-muted);">
                                                No custom corridor lanes saved yet. Zonal baseline rates will apply automatically.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script>
    function switchLaneTab(tab) {
        if (tab === 'zonal') {
            document.getElementById('vmZonalRatesTab').style.display = 'block';
            document.getElementById('vmCorridorsTab').style.display = 'none';
            document.getElementById('btnTabZonal').className = 'btn-dash btn-dash-primary';
            document.getElementById('btnTabCorridors').className = 'btn-dash btn-dash-outline';
            document.getElementById('btnTabCorridors').style.background = '#FFF';
        } else {
            document.getElementById('vmZonalRatesTab').style.display = 'none';
            document.getElementById('vmCorridorsTab').style.display = 'block';
            document.getElementById('btnTabCorridors').className = 'btn-dash btn-dash-primary';
            document.getElementById('btnTabZonal').className = 'btn-dash btn-dash-outline';
            document.getElementById('btnTabZonal').style.background = '#FFF';
        }
    }

    function filterLaneRows() {
        var query = document.getElementById('vmLaneSearchInput').value.toLowerCase();
        var rows = document.querySelectorAll('.lane-row');
        rows.forEach(function(row) {
            var origin = row.getAttribute('data-origin') || '';
            var dest = row.getAttribute('data-dest') || '';
            if (origin.includes(query) || dest.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>

    <div class="dash-card" id="merchants-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏛️</span>
                <div>
                    <div>Accredited Merchant KYC & Banking Desk</div>
                    <span class="dash-card-subtitle">Examine legal CAC/NIN verification and disburse permissions to sell on Victorious MARKET.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Sovereign Quality Gate</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Merchant / Business</th>
                        <th>CAC Number</th>
                        <th>Director NIN</th>
                        <th>Settlement Bank</th>
                        <th>KYC Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allVendors ?? [] as $v)
                        <tr>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">{{ $v->business_name }}</strong>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Owner: {{ $v->user->name ?? 'N/A' }} ({{ $v->user->phone ?? 'N/A' }})</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $v->cac_number ?? 'Pending CAC' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $v->nin_number ?? 'Pending NIN' }}</span>
                            </td>
                            <td>
                                <div><strong>{{ $v->bank_name ?? 'Zenith Bank' }}</strong></div>
                                <div style="font-family: monospace; font-size: 12px; color: var(--adm-text-muted);">{{ $v->account_number ?? '1012345678' }}</div>
                            </td>
                            <td>
                                @if($v->kyc_status === 'verified')
                                    <span class="dash-badge dash-badge-success">● Accredited Active</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">● {{ ucfirst($v->kyc_status ?? 'Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.vendors.accredit', $v->id) }}" style="display: flex; gap: 6px; margin: 0;">
                                    @csrf
                                    @if($v->kyc_status === 'verified')
                                        <button type="submit" name="action" value="reject" class="btn-dash btn-dash-danger" style="padding: 4px 10px; font-size: 11px;">
                                            Suspend
                                        </button>
                                    @else
                                        <button type="submit" name="action" value="verify" class="btn-dash btn-dash-success" style="padding: 4px 10px; font-size: 11px;">
                                            ✓ Approve KYC
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                No merchants registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('script')
<script>
    // Live preview interactions for announcement control center
    const inputText = document.getElementById('inputText');
    const inputBadge = document.getElementById('inputBadge');
    const inputBg = document.getElementById('inputBg');
    const previewText = document.getElementById('previewText');
    const previewBadge = document.getElementById('previewBadge');
    const vmTopBarPreview = document.getElementById('vmTopBarPreview');

    if (inputText && previewText) {
        inputText.addEventListener('input', (e) => { previewText.textContent = e.target.value; });
    }
    if (inputBadge && previewBadge) {
        inputBadge.addEventListener('input', (e) => { previewBadge.textContent = e.target.value; });
    }
    if (inputBg && vmTopBarPreview) {
        inputBg.addEventListener('input', (e) => { vmTopBarPreview.style.background = e.target.value; });
    }

    // Preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (inputText) inputText.value = btn.dataset.text;
            if (inputBadge) inputBadge.value = btn.dataset.badge;
            if (inputBg) inputBg.value = btn.dataset.bg;
            if (previewText) previewText.textContent = btn.dataset.text;
            if (previewBadge) previewBadge.textContent = btn.dataset.badge;
            if (vmTopBarPreview) vmTopBarPreview.style.background = btn.dataset.bg;
            const selectType = document.getElementById('selectType');
            if (selectType) selectType.value = btn.dataset.type;
        });
    });
</script>
@endpush
