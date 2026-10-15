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

    <!-- Profile Menu -->
    <ul class="profile-menu">
        <li class="{{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
            <a href="{{ route('customer.dashboard') }}">
                <span class="profile-menu-icon-title">
                    <span>👤</span>
                    <span>My Profile</span>
                </span>
            </a>
        </li>
        <li class="{{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
            <a href="{{ route('customer.orders.index') }}">
                <span class="profile-menu-icon-title">
                    <span>🛍️</span>
                    <span>My Orders</span>
                </span>
                <span class="badge" style="background: var(--vm-primary-light); color: var(--vm-primary); font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                    {{ $total_order ?? 0 }}
                </span>
            </a>
        </li>
        <li class="{{ request()->routeIs('customer.reservations.*') ? 'active' : '' }}">
            <a href="{{ route('customer.reservations.index') }}">
                <span class="profile-menu-icon-title">
                    <span>🏬</span>
                    <span>Pickup Reservations</span>
                </span>
                <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                    {{ isset($reservations) ? count($reservations) : 0 }}
                </span>
            </a>
        </li>
        <li class="{{ request()->routeIs('customer.addresses.*') ? 'active' : '' }}">
            <a href="{{ route('customer.addresses.index') }}">
                <span class="profile-menu-icon-title">
                    <span>📍</span>
                    <span>My Addresses</span>
                </span>
            </a>
        </li>
        <li class="{{ request()->routeIs('customer.cashback.*') ? 'active' : '' }}">
            <a href="{{ route('customer.cashback.index') }}">
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
                <span style="font-size: 12px; color: var(--vm-text-muted);">{{ $wishlists ?? 3 }}</span>
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