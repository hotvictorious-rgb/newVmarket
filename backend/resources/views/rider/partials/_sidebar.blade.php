<li class="nav-section-title">RIDER COCKPIT</li>
<li class="sidebar-nav-item">
    <a href="{{ route('rider.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('rider.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">🏍️</span>
        <span>Rider Cockpit</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('rider.deliveries.index') }}" class="sidebar-nav-link {{ request()->routeIs('rider.deliveries.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📦</span>
        <span>My Active Drops</span>
        @if(isset($activeDeliveries) && count($activeDeliveries) > 0)
            <span class="nav-link-badge">{{ count($activeDeliveries) }}</span>
        @endif
    </a>
</li>

<li class="nav-section-title">2-STEP PROTOCOL</li>
<li class="sidebar-nav-item">
    <a href="{{ route('rider.pickup.index') }}" class="sidebar-nav-link {{ request()->routeIs('rider.pickup.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏬</span>
        <span>Plaza Pickup Stop</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('rider.dropoff.index') }}" class="sidebar-nav-link {{ request()->routeIs('rider.dropoff.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏁</span>
        <span>Customer Drop-off &amp; OTP</span>
    </a>
</li>

<li class="nav-section-title">FINANCE</li>
<li class="sidebar-nav-item">
    <a href="{{ route('rider.earnings.index') }}" class="sidebar-nav-link {{ request()->routeIs('rider.earnings.*') ? 'active' : '' }}">
        <span class="nav-link-icon">💰</span>
        <span>Today's Earning Ledger</span>
    </a>
</li>