<li class="nav-section-title">BRANCH TERMINAL</li>
<li class="sidebar-nav-item">
    <a href="{{ route('counter.dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('counter.dashboard') ? 'active' : '' }}">
        <span class="nav-link-icon">🏬</span>
        <span>Counter Terminal</span>
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('counter.inspections.index') }}" class="sidebar-nav-link {{ request()->routeIs('counter.inspections.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🔎</span>
        <span>Physical Inspection Desk</span>
        @if(isset($pendingInspections) && count($pendingInspections) > 0)
            <span class="nav-link-badge">{{ count($pendingInspections) }}</span>
        @endif
    </a>
</li>
<li class="sidebar-nav-item">
    <a href="{{ route('counter.handovers.index') }}" class="sidebar-nav-link {{ request()->routeIs('counter.handovers.*') ? 'active' : '' }}">
        <span class="nav-link-icon">🔑</span>
        <span>Rider Handover OTP Desk</span>
        @if(isset($stagedOrders) && count($stagedOrders) > 0)
            <span class="nav-link-badge">{{ count($stagedOrders) }}</span>
        @endif
    </a>
</li>

<li class="nav-section-title">AUDIT &amp; LOGS</li>
<li class="sidebar-nav-item">
    <a href="{{ route('counter.records.index') }}" class="sidebar-nav-link {{ request()->routeIs('counter.records.*') ? 'active' : '' }}">
        <span class="nav-link-icon">📋</span>
        <span>Today's Inspection Records</span>
    </a>
</li>