<li class="nav-section-title">FLEET DISPATCH</li>
<li class="sidebar-nav-item">
    <a href="{{ route('logistics.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">🚚</span>
        <span>Fleet Command</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('logistics.riders.index') }}" class="sidebar-nav-link {{ request()->routeIs('logistics.riders.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏍️</span>
        <span>Rider Roster &amp; Shifts</span>
        @if(isset($activeRiders) && count($activeRiders) > 0)
            <span class="nav-link-badge">{{ count($activeRiders) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('logistics.waybills.index') }}" class="sidebar-nav-link {{ request()->routeIs('logistics.waybills.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📜</span>
        <span>Directional LGA Waybills</span>
        @if(isset($activeDeliveries) && count($activeDeliveries) > 0)
            <span class="nav-link-badge">{{ count($activeDeliveries) }}</span>
        @endif
    </a>
</li>

<li class="nav-section-title">FINANCE &amp; SETTLEMENT</li>
<li class="sidebar-nav-item">
    <a href="{{ route('logistics.wallet.index') }}" class="sidebar-nav-link {{ request()->routeIs('logistics.wallet.*') ? 'active' : '' }}">
        <span class="nav-link-icon">💼</span>
        <span>5% Logistics Fee Wallet</span>
    </a>
</li>