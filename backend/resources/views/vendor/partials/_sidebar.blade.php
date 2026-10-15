<li class="nav-section-title">MERCHANT COMMERCE</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">📊</span>
        <span>Merchant Overview</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.products.index') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.products.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏷️</span>
        <span>Products &amp; Freshness</span>
        @if(isset($products) && count($products) > 0)
            <span class="nav-link-badge">{{ count($products) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.orders.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🛍️</span>
        <span>Customer Orders</span>
        @if(isset($orders) && count($orders) > 0)
            <span class="nav-link-badge">{{ count($orders) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.reservations.index') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.reservations.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📝</span>
        <span>Pickup Reservations</span>
        @if(isset($reservations) && count($reservations) > 0)
            <span class="nav-link-badge">{{ count($reservations) }}</span>
        @endif
    </a>
</li>

<li class="nav-section-title">OUTLETS &amp; PAYOUTS</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.shops.index') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.shops.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏬</span>
        <span>Physical Plaza Outlets</span>
        @if(isset($shops) && count($shops) > 0)
            <span class="nav-link-badge">{{ count($shops) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('vendor.payout.index') }}" class="sidebar-nav-link {{ request()->routeIs('vendor.payout.*') ? 'active' : '' }}">
        <span class="nav-link-icon">💳</span>
        <span>90% Payout Bank Account</span>
    </a>
</li>