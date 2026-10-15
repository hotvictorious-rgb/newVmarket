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
    <div class="dash-card" id="delivery-lanes">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🛣️</span>
                <div>
                    <div>Directional Delivery Lanes Matrix (Akwa Ibom LGAs)</div>
                    <span class="dash-card-subtitle">Active LGA corridors, base tariffs, distance rates, and same-day cut-off windows.</span>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;"><button type="button" class="btn-dash btn-dash-primary" onclick="var el = document.getElementById('vmNewLaneCollapse'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">+ Add Directional Lane</button><span class="dash-badge dash-badge-info">LGA Topology</span></div>
        </div>

                <!-- Add Directional Delivery Lane Form -->
        <div id="vmNewLaneCollapse" style="display: none; background: #F8FAFC; border-bottom: 2px solid var(--adm-border); padding: 20px 24px;">
            <div style="font-weight: 700; font-size: 14px; margin-bottom: 12px; color: var(--adm-text); display: flex; align-items: center; gap: 8px;">
                <span>🛣️</span> Configure New Directional LGA Corridor
            </div>
            <form method="POST" action="{{ route('admin.delivery_lanes.store') }}">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Origin LGA (Merchant)</label>
                        <select name="origin_lga_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            @foreach($allLgas ?? [] as $lga)
                                <option value="{{ $lga->id }}" {{ $lga->name == 'Uyo' ? 'selected' : '' }}>{{ $lga->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Destination LGA (Customer)</label>
                        <select name="destination_lga_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF;">
                            @foreach($allLgas ?? [] as $lga)
                                <option value="{{ $lga->id }}">{{ $lga->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Base Tariff (₦)</label>
                        <input type="number" step="50" name="base_fee" value="500" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Speed Min (Hours)</label>
                        <input type="number" name="estimated_delivery_hours_min" value="2" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Speed Max (Hours)</label>
                        <input type="number" name="estimated_delivery_hours_max" value="4" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">Daily Cut-Off Time</label>
                        <input type="text" name="cutoff_time" value="15:00" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border);">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="document.getElementById('vmNewLaneCollapse').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary">✓ Save Directional Lane</button>
                </div>
            </form>
        </div>
        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Origin LGA</th>
                        <th>Destination LGA</th>
                        <th>Base Tariff</th>
                        <th>Per-Km Rate</th>
                        <th>Order Cut-off</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveryLanes as $lane)
                        <tr>
                            <td><strong>{{ $lane->originLga->name ?? 'Uyo' }}</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $lane->destinationLga->name ?? 'Ikot Ekpene' }}</span>
                            </td>
                            <td>&#8358;{{ number_format($lane->base_fee, 2) }}</td>
                            <td>&#8358;{{ number_format($lane->per_km_rate, 2) }}/km</td>
                            <td>{{ $lane->cutoff_time }} (Daily)</td>
                            <td>
                                <span class="dash-badge dash-badge-success">● Active Corridor</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">
                                No delivery lanes configured.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Platform Escrow Orders -->
    <div class="dash-card" id="recent-orders">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>📦</span>
                <div>
                    <div>Recent Platform Escrow Orders</div>
                    <span class="dash-card-subtitle">Full transaction oversight across Uyo branches and directional transit.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Full Audit Trail</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Branch Plaza</th>
                        <th>Total</th>
                        <th>Fulfillment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;">{{ $order->order_number }}</td>
                            <td>{{ $order->customer->name ?? 'Customer' }}</td>
                            <td>{{ $order->shop->name ?? 'Uyo Plaza' }}</td>
                            <td><strong>&#8358;{{ number_format($order->total_amount, 2) }}</strong></td>
                            <td>
                                <span class="dash-badge dash-badge-gold">
                                    {{ ucfirst(str_replace('_', ' ', $order->fulfillment_type)) }}
                                </span>
                            </td>
                            <td>
                                @if($order->order_status === 'delivered')
                                    <span class="dash-badge dash-badge-success">Delivered ✓</span>
                                @elseif($order->order_status === 'out_for_delivery')
                                    <span class="dash-badge dash-badge-info">In LGA Transit 🚚</span>
                                @else
                                    <span class="dash-badge dash-badge-warning">{{ ucfirst($order->order_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: var(--adm-text-muted);">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stage 1.2: Merchant Accreditation & KYC Desk -->
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
