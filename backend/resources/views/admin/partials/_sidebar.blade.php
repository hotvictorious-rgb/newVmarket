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
        <span>Directional LGA Lanes</span>
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
<li class="sidebar-nav-item">
    <a href="{{ route('admin.announcements.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📢</span>
        <span>Announcement Bar &amp; Care</span>
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
    <a href="{{ route('vendor.dashboard') }}" class="sidebar-nav-link">
        <span class="nav-link-icon">🏬</span>
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
        <span class="nav-link-icon">🏪</span>
        <span>Inspect Counter Terminals</span>
    </a>
</li>