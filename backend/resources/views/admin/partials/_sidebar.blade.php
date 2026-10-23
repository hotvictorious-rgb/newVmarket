<li class="nav-section-title">GOVERNANCE &amp; CONTROL</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">👑</span>
        <span>Command Tower</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.delivery_lanes.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.delivery_lanes.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🛣️</span>
        <span>Standard Delivery Fee Matrix</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.settlements.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.settlements.*') ? 'active' : '' }}">
        <span class="nav-link-icon">💰</span>
        <span>90/5/5 Settlement Desk</span>
        @if(isset($pendingSettlementsCount) && $pendingSettlementsCount > 0)
            <span class="nav-link-badge">{{ $pendingSettlementsCount }}</span>
        @elseif(isset($pendingSettlements) && count($pendingSettlements) > 0)
            <span class="nav-link-badge">{{ count($pendingSettlements) }}</span>
        @endif
    </a>
</li>
@if (auth()->user()?->isSuperAdmin())
<li class="sidebar-nav-item">
    <a href="{{ route('admin.retailer-supplier-payouts.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.retailer-supplier-payouts.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏦</span>
        <span>Retailer Supplier Payables</span>
    </a>
</li>
@endif
<li class="sidebar-nav-item">
    <a href="{{ route('admin.announcements.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📢</span>
        <span>Announcement Bar &amp; Care</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.banners.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🎨</span>
        <span>Storefront Banners &amp; Promos</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.collection_points.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.collection_points.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏢</span>
        <span>Return Hubs &amp; Drop-offs</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.returns.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.returns.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🔄</span>
        <span>Returns &amp; Refunds Desk</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.legal.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.legal.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📜</span>
        <span>Legal &amp; Policy CMS</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.notifications.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🔔</span>
        <span>Push &amp; Alerts Dispatch</span>
    </a>
</li>

<li class="nav-section-title">ECOSYSTEM ACTORS</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📦</span>
        <span>Platform Escrow Orders</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.merchants.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.merchants.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏪</span>
        <span>Merchant Accreditation Desk</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.logistics_companies.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.logistics_companies.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🚚</span>
        <span>Logistics Companies Desk</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.products.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
        <span class="nav-link-icon">⭐</span>
        <span>Flagship Products Catalog</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.supplier-catalog.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.supplier-catalog.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🧾</span>
        <span>Supplier Catalog &amp; Listings</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.dashboard') }}" class="sidebar-nav-link">
        <span class="nav-link-icon">🏬</span>
        <span>Inspect Merchant Desk</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('logistics.dashboard') }}" class="sidebar-nav-link">
        <span class="nav-link-icon">🏍️</span>
        <span>Inspect Fleet Portal</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('counter.dashboard') }}" class="sidebar-nav-link">
        <span class="nav-link-icon">🏪</span>
        <span>Inspect Counter Terminals</span>
    </a>
</li>