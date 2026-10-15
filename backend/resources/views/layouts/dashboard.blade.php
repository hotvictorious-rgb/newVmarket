<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Victorious MARKET</title>
    
    <link rel="shortcut icon" href="{{ asset('assets/img/vm_icon.jpg') }}" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800&display=swap" rel="stylesheet">
    
    <!-- Admin Master Design System referencing vmarket v1 -->
    <link rel="stylesheet" href="{{ asset('assets/css/vmarket-admin.css') }}">
    @stack('css')
</head>
<body class="admin-body">

<div class="admin-wrapper">
    <!-- Collapsible Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Brand Header -->
        <a href="{{ url('/') }}" class="sidebar-brand">
            <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" class="brand-icon">
            <div class="brand-title">
                <span class="brand-name-gold">Victorious</span>
                <span class="brand-name-white">MARKET</span>
                <span class="sidebar-role-badge @yield('role_badge_class', 'role-badge-super-admin')">
                    @yield('role_badge', 'PORTAL')
                </span>
            </div>
        </a>

        <!-- Menu Quick Filter -->
        <div class="sidebar-search-box">
            <input type="text" id="sidebarMenuFilter" class="sidebar-search-input" placeholder="🔍 Search menu...">
        </div>

        <!-- Role-Specific Navigation Menu (Strict Domain Isolation) -->
        <ul class="sidebar-nav" id="sidebarNav">
            @yield('sidebar_menu')
        </ul>

        <!-- Bottom User Footprint -->
        <div class="sidebar-footer">
            <div class="sidebar-user-pill">
                <div class="user-avatar-circle">
                    {{ substr(Auth::user()->name ?? 'VM', 0, 1) }}
                </div>
                <div class="user-meta-text">
                    <div class="user-meta-name">{{ Auth::user()->name ?? 'Demo Account' }}</div>
                    <div class="user-meta-role">{{ ucfirst(str_replace('_', ' ', Auth::user()->user_type ?? 'User')) }}</div>
                </div>
            </div>
            
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-signout-icon" title="Sign Out">
                    ⎋
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Canvas -->
    <div class="admin-main">
        <!-- Sticky Header Bar -->
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="btn-sidebar-toggle" id="sidebarToggleBtn" aria-label="Toggle Menu">
                    ☰
                </button>
                <div class="breadcrumb-trail">
                    <span>Victorious MARKET</span>
                    <span>&rsaquo;</span>
                    <span>@yield('role_title', 'Dashboard')</span>
                    <span>&rsaquo;</span>
                    <span class="active">@yield('page_title', 'Overview')</span>
                </div>
            </div>

            <div class="header-right">
                <div class="system-pill-indicator">
                    <span class="system-pill-dot"></span>
                    <span>Sovereign Engine Active • 90/5/5 Escrow</span>
                </div>

                <!-- Quick Portal Switcher (For convenient preview across all 7 actor dashboards) -->
                <div class="role-switcher-dropdown">
                    <button type="button" class="role-switcher-btn">
                        <span>Switch Portal ▾</span>
                    </button>
                    <div class="role-switcher-menu">
                        <div style="font-size: 10px; font-weight: 700; color: #94A3B8; text-transform: uppercase; padding: 4px 8px;">
                            The 7 Role Dashboards:
                        </div>
                        <a href="{{ route('admin.dashboard') }}" class="role-switcher-item">
                            <span>👑</span> <span>Super Admin Tower</span>
                        </a>
                        <a href="{{ route('admin.staff.dashboard') }}" class="role-switcher-item">
                            <span>📋</span> <span>Admin Staff Support</span>
                        </a>
                        <a href="{{ route('vendor.dashboard') }}" class="role-switcher-item">
                            <span>🏪</span> <span>Vendor Merchant</span>
                        </a>
                        <a href="{{ route('counter.dashboard') }}" class="role-switcher-item">
                            <span>🏬</span> <span>Branch Counter Staff</span>
                        </a>
                        <a href="{{ route('logistics.dashboard') }}" class="role-switcher-item">
                            <span>🚚</span> <span>Logistics Fleet Desk</span>
                        </a>
                        <a href="{{ route('rider.dashboard') }}" class="role-switcher-item">
                            <span>🏍️</span> <span>Delivery Rider</span>
                        </a>
                        <a href="{{ route('customer.dashboard') }}" class="role-switcher-item">
                            <span>🛍️</span> <span>Customer Shopper</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('storefront') }}" target="_blank" class="btn-storefront-link">
                    <span>Storefront Live 🌐</span>
                </a>
            </div>
        </header>

        <!-- Dynamic Content Body -->
        <main class="admin-content">
            @if (session('status'))
                <div class="dash-alert-success">
                    <span style="font-size: 16px;">✓</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    // Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const adminSidebar = document.getElementById('adminSidebar');
    if (sidebarToggleBtn && adminSidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            adminSidebar.classList.toggle('sidebar-open');
        });
    }

    // Sidebar Menu Live Search Filter
    const filterInput = document.getElementById('sidebarMenuFilter');
    if (filterInput) {
        filterInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const items = document.querySelectorAll('#sidebarNav .sidebar-nav-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
</script>
@stack('script')
</body>
</html>
