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
    <script>
        // Check sidebar state early to avoid layout shift
        if (localStorage.getItem('vm_sidebar_collapsed') === '1' && window.innerWidth > 992) {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    @stack('css')

<style>
/* Sovereign In-App Notification Bell & Dropdown Styles */
.header-notif-dropdown {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.btn-notif-trigger {
    position: relative;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #CBD5E1;
    width: 38px;
    height: 38px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-notif-trigger:hover {
    background: rgba(255, 215, 0, 0.12);
    border-color: rgba(255, 215, 0, 0.4);
    color: #FFD700;
}
.notif-badge-pill {
    position: absolute;
    top: -4px;
    right: -4px;
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: #FFFFFF;
    font-size: 10px;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
    border: 1.5px solid #0F172A;
    animation: pulseBadge 2.5s infinite;
}
@keyframes pulseBadge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.12); }
}
.notif-dropdown-card {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 380px;
    max-width: 90vw;
    background: rgba(15, 23, 42, 0.97);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 215, 0, 0.25);
    border-radius: 14px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.7), 0 0 20px rgba(255, 215, 0, 0.05);
    z-index: 1050;
    overflow: hidden;
    flex-direction: column;
}
.notif-dropdown-card.active {
    display: flex;
}
.notif-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.2);
}
.notif-count-pill {
    background: rgba(255, 215, 0, 0.15);
    color: #FFD700;
    border: 1px solid rgba(255, 215, 0, 0.3);
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
}
.btn-mark-all-read {
    background: transparent;
    border: none;
    color: #94A3B8;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.15s ease;
    padding: 4px 6px;
}
.btn-mark-all-read:hover {
    color: #FFD700;
    text-decoration: underline;
}
.notif-card-body {
    max-height: 380px;
    overflow-y: auto;
    padding: 4px 0;
}
.notif-card-body::-webkit-scrollbar {
    width: 5px;
}
.notif-card-body::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
}
.notif-card-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}
.notif-loading-state, .notif-empty-state {
    padding: 32px 20px;
    text-align: center;
}
.notif-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-top-color: #FFD700;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.15s ease;
    cursor: pointer;
    text-decoration: none;
}
.notif-item:hover {
    background: rgba(255, 255, 255, 0.04);
}
.notif-item.unread {
    background: rgba(255, 215, 0, 0.04);
}
.notif-item.unread:hover {
    background: rgba(255, 215, 0, 0.08);
}
.notif-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.notif-content-box {
    flex: 1;
    min-width: 0;
}
.notif-item-title {
    font-size: 12px;
    font-weight: 700;
    color: #F8FAFC;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.notif-unread-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #FFD700;
    box-shadow: 0 0 6px rgba(255, 215, 0, 0.8);
    display: inline-block;
}
.notif-item-body {
    font-size: 11px;
    color: #94A3B8;
    line-height: 1.4;
    word-break: break-word;
}
.notif-item-meta {
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    color: #64748B;
}
.notif-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.25);
}
</style>
</head>
<body class="admin-body">

<div class="admin-wrapper">
    <!-- Collapsible Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Brand Header with Close/Collapse Button -->
        <div class="sidebar-brand-container" style="display: flex; align-items: center; justify-content: space-between; padding: 0 16px; height: var(--adm-header-height); border-bottom: 1px solid var(--adm-sidebar-border); background: rgba(0, 0, 0, 0.2);">
            <a href="{{ url('/') }}" class="sidebar-brand" style="border: none; padding: 0; background: none;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" class="brand-icon">
                <div class="brand-title">
                    <span class="brand-name-gold">Victorious</span>
                    <span class="brand-name-white">MARKET</span>
                    <span class="sidebar-role-badge @yield('role_badge_class', 'role-badge-super-admin')">
                        @yield('role_badge', 'PORTAL')
                    </span>
                </div>
            </a>
            <button type="button" class="btn-sidebar-close" id="sidebarCloseBtn" aria-label="Close Sidebar" title="Collapse Sidebar (Ctrl+B)">
                ✕
            </button>
        </div>

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

    <!-- Mobile Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main Content Canvas -->
    <div class="admin-main">
        <!-- Sticky Header Bar -->
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="btn-sidebar-toggle" id="sidebarToggleBtn" aria-label="Toggle Menu" title="Toggle Sidebar (Ctrl+B)">
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
                                <!-- Sovereign In-App Notification Bell Dropdown -->
                <div class="header-notif-dropdown" id="headerNotifDropdown">
                    <button type="button" class="btn-notif-trigger" id="notifBellBtn" aria-label="System Notifications" title="System Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="notif-badge-pill" id="notifUnreadBadge" style="display: none;">0</span>
                    </button>
                    
                    <div class="notif-dropdown-card" id="notifDropdownCard">
                        <div class="notif-card-header">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 700; font-size: 13px; color: #F8FAFC; text-transform: uppercase; letter-spacing: 0.5px;">Alerts & Dispatches</span>
                                <span class="notif-count-pill" id="notifCountPill">0 New</span>
                            </div>
                            <button type="button" class="btn-mark-all-read" id="btnMarkAllRead" title="Mark all alerts as read">
                                Mark all read
                            </button>
                        </div>
                        
                        <div class="notif-card-body" id="notifListContainer">
                            <div class="notif-loading-state" id="notifLoadingState">
                                <span class="notif-spinner"></span> Loading dispatches...
                            </div>
                            <div class="notif-empty-state" id="notifEmptyState" style="display: none;">
                                <div style="font-size: 26px; margin-bottom: 6px;">🔔</div>
                                <div style="font-size: 13px; font-weight: 600; color: #94A3B8;">All Caught Up</div>
                                <div style="font-size: 11px; color: #64748B;">No unread push alerts or dispatches on your account.</div>
                            </div>
                            <div class="notif-items-list" id="notifItemsList"></div>
                        </div>
                        
                        <div class="notif-card-footer">
                            <span style="font-size: 11px; color: #64748B; display: flex; align-items: center; gap: 4px;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10B981;"></span>
                                Live Sovereign Bus
                            </span>
                            @if(Auth::check() && Auth::user()->user_type === 'super_admin')
                                <a href="{{ route('admin.notifications.index') }}" style="font-size: 11px; color: #FFD700; text-decoration: none; font-weight: 600;">Control Tower &rsaquo;</a>
                            @endif
                        </div>
                    </div>
                </div>

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
                            <span>🏬</span> <span>Vendor Merchant</span>
                        </a>
                        <a href="{{ route('counter.dashboard') }}" class="role-switcher-item">
                            <span>🏪</span> <span>Branch Counter Staff</span>
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
    // Closeable Sidebar System (Desktop & Mobile)
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const adminSidebar = document.getElementById('adminSidebar');

    function toggleSidebar() {
        if (window.innerWidth > 992) {
            // Desktop Collapse
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('vm_sidebar_collapsed', isCollapsed ? '1' : '0');
        } else {
            // Mobile Offcanvas
            adminSidebar.classList.toggle('sidebar-open');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('active', adminSidebar.classList.contains('sidebar-open'));
            }
        }
    }

    function closeSidebar() {
        if (window.innerWidth > 992) {
            document.body.classList.add('sidebar-collapsed');
            localStorage.setItem('vm_sidebar_collapsed', '1');
        } else {
            adminSidebar.classList.remove('sidebar-open');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.remove('active');
            }
        }
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', toggleSidebar);
    }
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', closeSidebar);
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    // Keyboard Shortcut (Ctrl+B or Cmd+B to toggle, Esc to close)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            toggleSidebar();
        } else if (e.key === 'Escape') {
            if (window.innerWidth <= 992 && adminSidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        }
    });

    // Apply saved desktop state to body on page load
    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('vm_sidebar_collapsed') === '1' && window.innerWidth > 992) {
            document.body.classList.add('sidebar-collapsed');
        }
    });

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

<script>
// Sovereign In-App Notifications Real-Time Client
(function() {
    const bellBtn = document.getElementById('notifBellBtn');
    const dropdownCard = document.getElementById('notifDropdownCard');
    const badge = document.getElementById('notifUnreadBadge');
    const countPill = document.getElementById('notifCountPill');
    const itemsList = document.getElementById('notifItemsList');
    const loadingState = document.getElementById('notifLoadingState');
    const emptyState = document.getElementById('notifEmptyState');
    const btnMarkAll = document.getElementById('btnMarkAllRead');

    if (!bellBtn || !dropdownCard) return;

    let hasLoaded = false;
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        const now = new Date();
        const past = new Date(dateStr);
        const diffSec = Math.floor((now - past) / 1000);
        if (diffSec < 60) return 'Just now';
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return diffMin + 'm ago';
        const diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return diffHr + 'h ago';
        return Math.floor(diffHr / 24) + 'd ago';
    }

    function getIconForType(type) {
        if (!type) return '🔔';
        const t = type.toLowerCase();
        if (t.includes('delivery') || t.includes('dispatch') || t.includes('arrival')) return '🚚';
        if (t.includes('pickup') || t.includes('ready')) return '📦';
        if (t.includes('payout') || t.includes('settlement') || t.includes('escrow') || t.includes('wallet')) return '💰';
        if (t.includes('dispute') || t.includes('defect') || t.includes('fraud')) return '🛡️';
        if (t.includes('danger') || t.includes('fail') || t.includes('reject')) return '⚠️';
        if (t.includes('order') || t.includes('purchase')) return '🛍️';
        return '🔔';
    }

    async function fetchNotifications() {
        try {
            const res = await fetch('/portal/notifications/in-app', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) return;
            const data = await res.json();
            
            const unread = data.unread_count || 0;
            if (unread > 0) {
                badge.textContent = unread > 99 ? '99+' : unread;
                badge.style.display = 'flex';
                countPill.textContent = unread + ' New';
            } else {
                badge.style.display = 'none';
                countPill.textContent = '0 New';
            }

            renderNotifications(data.notifications || []);
            hasLoaded = true;
        } catch (err) {
            console.warn('[Victorious Bus] In-app notification poll skipped:', err);
        }
    }

    function renderNotifications(items) {
        if (loadingState) loadingState.style.display = 'none';
        if (!items || items.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            if (itemsList) itemsList.innerHTML = '';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';
        if (!itemsList) return;

        itemsList.innerHTML = items.map(n => {
            const isUnread = !n.read_at;
            const icon = getIconForType(n.type);
            const timeAgo = formatTimeAgo(n.created_at);
            const actionUrl = n.action_url || '';
            const safeTitle = (n.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const safeBody = (n.body || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            return `
                <div class="notif-item ${isUnread ? 'unread' : ''}" data-id="${n.id}" data-url="${actionUrl}">
                    <div class="notif-icon-box">${icon}</div>
                    <div class="notif-content-box">
                        <div class="notif-item-title">
                            <span>${safeTitle}</span>
                            ${isUnread ? '<span class="notif-unread-dot"></span>' : ''}
                        </div>
                        <div class="notif-item-body">${safeBody}</div>
                        <div class="notif-item-meta">
                            <span>${timeAgo}</span>
                            ${actionUrl ? '<span style="color: #FFD700;">Open &rsaquo;</span>' : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Wire item clicks
        itemsList.querySelectorAll('.notif-item').forEach(el => {
            el.addEventListener('click', async () => {
                const id = el.getAttribute('data-id');
                const url = el.getAttribute('data-url');
                
                // Optimistic UI mark read
                el.classList.remove('unread');
                const dot = el.querySelector('.notif-unread-dot');
                if (dot) dot.remove();

                // Send mark read
                try {
                    await fetch('/portal/notifications/mark-read/' + id, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    // Refresh count
                    fetchNotifications();
                } catch(e) {}

                if (url && url !== '#' && url !== '') {
                    window.location.href = url;
                }
            });
        });
    }

    async function markAllAsRead() {
        try {
            await fetch('/portal/notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            badge.style.display = 'none';
            countPill.textContent = '0 New';
            if (itemsList) {
                itemsList.querySelectorAll('.notif-item').forEach(el => el.classList.remove('unread'));
                itemsList.querySelectorAll('.notif-unread-dot').forEach(el => el.remove());
            }
        } catch(e) {}
    }

    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', (e) => {
            e.stopPropagation();
            markAllAsRead();
        });
    }

    // Toggle dropdown
    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownCard.classList.toggle('active');
        if (dropdownCard.classList.contains('active')) {
            fetchNotifications();
        }
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdownCard.contains(e.target) && !bellBtn.contains(e.target)) {
            dropdownCard.classList.remove('active');
        }
    });

    // Initial load & background poll every 45s
    fetchNotifications();
    setInterval(fetchNotifications, 45000);
})();
</script>
</body>
</html>