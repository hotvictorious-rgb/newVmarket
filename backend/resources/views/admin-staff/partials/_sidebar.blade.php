<li class="nav-section-title">STAFF OPERATIONS</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.staff.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('admin.staff.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">📋</span>
        <span>Staff Desk Overview</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.staff.moderation.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.staff.moderation.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🔍</span>
        <span>Product Moderation &amp; Freshness</span>
        @if(isset($products) && count($products) > 0)
            <span class="nav-link-badge">{{ count($products) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.staff.orders.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.staff.orders.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📦</span>
        <span>Order Dispatch Triage</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.staff.inspections.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.staff.inspections.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🏬</span>
        <span>Counter Inspection Monitor</span>
    </a>
</li>

<li class="nav-section-title">SUPPORT &amp; LOGS</li>
<li class="sidebar-nav-item">
    <a href="{{ route('admin.staff.logs.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.staff.logs.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📜</span>
        <span>Platform Operational Logs</span>
    </a>
</li>