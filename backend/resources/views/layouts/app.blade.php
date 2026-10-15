<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Victorious MARKET — Premier Omnichannel Retail & Logistics')</title>
    
    <!-- Favicon & App Launcher Icon -->
    <link rel="icon" type="image/jpeg" href="{{ asset('assets/img/vm_icon.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/vm_icon.jpg') }}">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Victorious MARKET Theme Design System -->
    <link rel="stylesheet" href="{{ asset('assets/css/vmarket.css') }}">

    <!-- Brand Specific Custom Overrides -->
    <style>
        :root {
            --vm-primary: #5E17EB;
            --vm-primary-hover: #4D10C7;
            --vm-primary-light: #F5F0FF;
            --vm-gold: #FFD700;
            --vm-gold-hover: #E6C200;
            --vm-dark: #0F172A;
            --vm-dark-surface: #1E293B;
            --vm-info: #0284C7;
            --vm-font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            font-family: var(--vm-font);
            background-color: var(--vm-bg);
            color: var(--vm-text);
        }

        /* Top Announcement & Portals Bar */
        .vm-top-bar {
            background: #0F172A;
            color: #E2E8F0;
            font-size: 12px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .vm-top-bar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .vm-top-bar a {
            color: #CBD5E1;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .vm-top-bar a:hover {
            color: var(--vm-gold);
        }

        .vm-portal-dropdown {
            position: relative;
            display: inline-block;
        }

        .vm-portal-trigger {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            color: #FFD700 !important;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .vm-portal-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: #1E293B;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
            min-width: 220px;
            z-index: 1050;
            padding: 6px 0;
        }

        .vm-portal-dropdown:hover .vm-portal-menu {
            display: block;
        }

        .vm-portal-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            color: #E2E8F0 !important;
            font-size: 12px;
            transition: background 0.15s ease;
        }

        .vm-portal-item:hover {
            background: rgba(94, 23, 235, 0.3);
            color: #FFFFFF !important;
        }

        /* Cart Drawer */
        .vm-cart-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .vm-cart-drawer-overlay.active {
            display: block;
            opacity: 1;
        }

        .vm-cart-drawer {
            position: fixed;
            top: 0;
            right: -420px;
            width: 100%;
            max-width: 400px;
            height: 100vh;
            background: #FFFFFF;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.15);
            z-index: 1001;
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }

        .vm-cart-drawer.active {
            right: 0;
        }

        .vm-cart-drawer-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--vm-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F8FAFC;
        }

        .vm-cart-drawer-title {
            font-size: 17px;
            font-weight: 700;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vm-cart-drawer-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #64748B;
            cursor: pointer;
            line-height: 1;
        }

        .vm-cart-drawer-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .vm-cart-drawer-footer {
            padding: 20px;
            border-top: 1px solid var(--vm-border);
            background: #F8FAFC;
        }

        /* Modal Location Selector */
        .vm-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1020;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .vm-modal-overlay.active {
            display: flex;
        }

        .vm-modal-card {
            background: #FFFFFF;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 1px solid var(--vm-border);
        }

        .vm-modal-header {
            padding: 18px 22px;
            background: #F8FAFC;
            border-bottom: 1px solid var(--vm-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .vm-modal-body {
            padding: 22px;
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
    @stack('styles')
</head>
<body>

@php
    $sysPhone = \App\Models\SystemSetting::get('support_phone', '09118949035');
    $sysWhatsApp = \App\Models\SystemSetting::get('support_whatsapp', '09118949035');
    $sysEmail = \App\Models\SystemSetting::get('support_email', 'support@victoriousmarket.com');
    $sysBadge = \App\Models\SystemSetting::get('announcement_badge', '💬');
    $sysText = \App\Models\SystemSetting::get('announcement_text', 'Call or Whatsapp 09118949035 to order');
    $sysBg = \App\Models\SystemSetting::get('announcement_bg_color', '#0F172A');
    $sysLink = \App\Models\SystemSetting::get('announcement_link', 'https://wa.me/2349118949035');
    $sysActive = (bool)\App\Models\SystemSetting::get('announcement_is_active', true);
@endphp

    <!-- 1. Top Announcement & Ecosystem Portals Bar -->
    @if($sysActive)
    <div class="vm-top-bar" style="background: {{ $sysBg }};">
        <div class="vm-container vm-top-bar-inner">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 13px;">{{ $sysBadge }}</span>
                @if(!empty($sysLink))
                    <a href="{{ $sysLink }}" target="_blank" style="color: inherit; text-decoration: none; font-weight: 600;">
                        <span>{!! $sysText !!}</span>
                    </a>
                @else
                    <span style="font-weight: 600;">{!! $sysText !!}</span>
                @endif
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <a href="{{ route('vendor.login') }}">Become a Vendor</a>
                <span style="opacity: 0.4;">•</span>
                <a href="#footer-guarantees">Help & Support</a>
                <span style="opacity: 0.4;">•</span>
                <!-- 7 Connected Login Portals Switcher -->
                <div class="vm-portal-dropdown">
                    <span class="vm-portal-trigger">
                        <span>⚡ 7 Portals Gate</span>
                        <span>▾</span>
                    </span>
                    <div class="vm-portal-menu">
                        <a href="{{ route('customer.login') }}" class="vm-portal-item">
                            <span>🛍️</span>
                            <span>Customer Shopper Login</span>
                        </a>
                        <a href="{{ route('admin.login') }}" class="vm-portal-item">
                            <span>👑</span>
                            <span>Super Admin Gate</span>
                        </a>
                        <a href="{{ route('admin.staff.login') }}" class="vm-portal-item">
                            <span>🏢</span>
                            <span>Super Admin Staff Portal</span>
                        </a>
                        <a href="{{ route('vendor.login') }}" class="vm-portal-item">
                            <span>🏪</span>
                            <span>Vendor Merchant Desk</span>
                        </a>
                        <a href="{{ route('counter.login') }}" class="vm-portal-item">
                            <span>📟</span>
                            <span>Branch Counter Terminal</span>
                        </a>
                        <a href="{{ route('logistics.login') }}" class="vm-portal-item">
                            <span>🚚</span>
                            <span>Logistics Company Fleet</span>
                        </a>
                        <a href="{{ route('rider.login') }}" class="vm-portal-item">
                            <span>🛵</span>
                            <span>Delivery Rider Portal</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 2. Sticky Header with Brand Pill, Location Selector, Search, & Actions -->
    <header class="vm-header">
        <div class="vm-container vm-header-inner">
            
            <!-- Brand Wordmark & Capsule Logo -->
            <a href="{{ route('storefront') }}" class="vm-brand" title="Victorious MARKET">
                <div class="vm-brand-pill">
                    <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" class="vm-brand-icon-sq">
                    <span class="vm-brand-wordmark">
                        <span class="vm-word-victorious">Victorious</span>
                        <span class="vm-word-market">MARKET</span>
                    </span>
                </div>
            </a>

            <!-- Your Location Selector Pill (Desktop) -->
            <button type="button" class="vm-header-location-pill" id="vmHeaderLocationBtn" aria-label="Change Your Location">
                <span class="vm-location-pin-icon" style="color: var(--vm-primary);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </span>
                <div class="vm-location-labels">
                    <span class="vm-location-sub">Your Location</span>
                    <span class="vm-location-main">
                        <strong class="vm-active-city-label">{{ session('customer_city', 'Choose your location') }}</strong>
                        <span class="vm-caret">▾</span>
                    </span>
                </div>
            </button>

            <!-- Global Search Bar with Action Button -->
            <form action="{{ route('storefront') }}" method="GET" class="vm-search-form">
                <div class="vm-search-input-wrap">
                    <input type="text" name="search" class="vm-search-input" placeholder="Search for products, categories, or items..." value="{{ request('search') }}" autocomplete="off">
                    <button type="submit" class="vm-search-btn" style="background: var(--vm-info);">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <span>Search</span>
                    </button>
                </div>
            </form>

            <!-- Actions: Sign In, Register, Cart Drawer -->
            <div class="vm-header-actions">
                @auth
                    <a href="{{ route('customer.login') }}" class="vm-action-btn" title="My Account">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>{{ Str::limit(Auth::user()->name, 10) }}</span>
                    </a>
                @else
                    <a href="{{ route('customer.login') }}" class="vm-action-btn" title="Sign In">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        <span>Sign In</span>
                    </a>
                    <a href="{{ route('customer.login') }}" class="vm-action-btn vm-register-btn" style="background: var(--vm-primary); color: #fff; padding: 6px 14px; border-radius: 999px;" title="Register">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <span style="font-weight: 600;">Register</span>
                    </a>
                @endauth

                <!-- Cart Button -->
                <button type="button" class="vm-action-btn" id="vmHeaderCartBtn" title="Cart" style="background: none; border: none; cursor: pointer;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="vm-action-badge" id="vmHeaderCartBadge" style="background: var(--vm-info);">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- 3. Desktop Navigation Bar -->
    <nav class="vm-nav-bar">
        <div class="vm-container">
            <ul class="vm-nav-list">
                <li><a href="{{ route('storefront') }}" class="vm-nav-link active">Home</a></li>
                <li><a href="{{ route('storefront') }}#catalog" class="vm-nav-link">All Products</a></li>
                <li class="vm-nav-dropdown-wrap">
                    <a href="{{ route('storefront') }}#categories" class="vm-nav-link">
                        <span>Categories</span>
                        <span class="vm-nav-caret">▾</span>
                    </a>
                </li>
                <li><a href="{{ route('storefront') }}#merchants" class="vm-nav-link">Verified Merchants</a></li>
                <li><a href="{{ route('storefront') }}#contact" class="vm-nav-link">Contact & Location</a></li>
            </ul>
        </div>
    </nav>

    <!-- 4. Main Page Content -->
    <main>
        @yield('content')
    </main>

    <!-- 5. Dark Footer with Brand Wordmark, Guarantees, & Office Info -->
    <footer class="vm-footer" id="contact">
        <div class="vm-container">
            <div class="vm-footer-grid">
                
                <!-- Brand Column -->
                <div>
                    <a href="{{ route('storefront') }}" class="vm-brand" style="margin-bottom: 16px;" title="Victorious MARKET">
                        <div class="vm-brand-pill">
                            <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" class="vm-brand-icon-sq">
                            <span class="vm-brand-wordmark">
                                <span class="vm-word-victorious">Victorious</span>
                                <span class="vm-word-market">MARKET</span>
                            </span>
                        </div>
                    </a>
                    <p style="font-size: 13.5px; line-height: 1.6; margin-bottom: 16px; color: #94A3B8;">
                        Nigeria’s omnichannel marketplace and delivery logistics ecosystem. Connecting verified merchants across Akwa Ibom with guaranteed in-shop inspection and swift door-to-door delivery.
                    </p>
                    <div style="font-size: 13.5px; line-height: 1.5; color: #CBD5E1; margin-bottom: 14px; display: flex; align-items: flex-start; gap: 8px;">
                        <span style="font-size: 16px; line-height: 1.2;">🏢</span>
                        <span><strong style="color: #FFFFFF;">Head Office:</strong> Lagos, Nigeria</span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px;">
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $sysWhatsApp ?? '09118949035') }}" target="_blank" style="color: #94A3B8; display: inline-flex; align-items: center; gap: 6px;">
                            <span>📞</span> {{ $sysPhone }}
                        </a>
                        <a href="mailto:{{ $sysEmail }}" style="color: #94A3B8; display: inline-flex; align-items: center; gap: 6px;">
                            <span>✉️</span> {{ $sysEmail }}
                        </a>
                    </div>
                </div>

                <!-- Quick Discovery Column -->
                <div>
                    <h4 class="vm-footer-title">Quick Discovery</h4>
                    <div class="vm-footer-links">
                        <a href="{{ route('storefront') }}#catalog">All Catalog Products</a>
                        <a href="{{ route('storefront') }}#categories">Product Categories</a>
                        <a href="{{ route('storefront') }}#merchants">Verified Merchants</a>
                        <a href="{{ route('storefront') }}#catalog">Official Brands</a>
                    </div>
                </div>

                <!-- Customer Care Column -->
                <div>
                    <h4 class="vm-footer-title">Customer Care</h4>
                    <div class="vm-footer-links">
                        <a href="#contact">Contact & Help Center</a>
                        <a href="#faq">Frequently Asked Questions</a>
                        <a href="#about">About Us</a>
                        <a href="#terms">Terms And Conditions</a>
                        <a href="#privacy">Privacy Policy</a>
                        <a href="#refund">Refund Policy</a>
                        <a href="#return">Return Policy</a>
                        <a href="#cancellation">Cancellation Policy</a>
                    </div>
                </div>

                <!-- Platform Guarantees Column -->
                <div id="footer-guarantees">
                    <h4 class="vm-footer-title">Platform Guarantees</h4>
                    <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13px;">
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <span style="font-size: 18px;">🛡️</span>
                            <div>
                                <strong style="color: #FFFFFF;">Paystack Verified Escrow</strong>
                                <p style="font-size: 12px; color: #94A3B8; margin-top: 2px;">100% secure payments via card, bank transfer, and USSD.</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <span style="font-size: 18px;">🏪</span>
                            <div>
                                <strong style="color: #FFFFFF;">In-Shop Inspection</strong>
                                <p style="font-size: 12px; color: #94A3B8; margin-top: 2px;">Reserve items and inspect physically before collection.</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <span style="font-size: 18px;">⚡</span>
                            <div>
                                <strong style="color: #FFFFFF;">Directional LGA Delivery</strong>
                                <p style="font-size: 12px; color: #94A3B8; margin-top: 2px;">Predictable logistics rates and dedicated rider tracking.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Copyright Bar -->
            <div class="vm-footer-bottom" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 24px; margin-top: 40px; font-size: 12.5px; color: #64748B;">
                <div>
                    <span>Copyright Victorious MARKET &copy; {{ date('Y') }}</span>
                </div>
                <div style="display: flex; gap: 20px; align-items: center;">
                    <span>🔒 SSL 256-Bit Encrypted</span>
                    <span>🇳🇬 Made for Nigeria</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive LGA Location Selection Modal -->
    <div class="vm-modal-overlay" id="vmLocationModal">
        <div class="vm-modal-card">
            <div class="vm-modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="VM" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; box-shadow: 0 2px 6px rgba(94, 23, 235, 0.25);">
                    <div>
                        <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0;">Select Your Location</h3>
                        <p style="font-size: 12px; color: #64748B; margin: 0;">Directional fulfillment & physical counter inspection</p>
                    </div>
                </div>
                <button type="button" id="vmCloseLocationModal" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748B;">&times;</button>
            </div>
            <div class="vm-modal-body">
                <p style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 12px;">Akwa Ibom LGAs:</p>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    @php
                        $sampleLgas = ['Uyo', 'Eket', 'Ikot Ekpene', 'Oron', 'Abak', 'Itu', 'Ibesikpo Asutan', 'Mkpat Enin', 'Onna', 'Nsit Ubium'];
                    @endphp
                    @foreach($sampleLgas as $sampleLga)
                        <button type="button" class="vm-lga-select-btn" data-city="{{ $sampleLga }}" style="text-align: left; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--vm-border); background: #F8FAFC; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.15s ease;">
                            📍 {{ $sampleLga }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Cart Drawer -->
    <div class="vm-cart-drawer-overlay" id="vmCartOverlay"></div>
    <div class="vm-cart-drawer" id="vmCartDrawer">
        <div class="vm-cart-drawer-header">
            <div class="vm-cart-drawer-title">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="VM" style="width: 24px; height: 24px; border-radius: 6px; object-fit: cover;">
                <span>Your Shopping Cart</span>
                <span class="vm-badge" id="vmCartBadgeCount" style="font-size: 12px; background: var(--vm-primary-light); color: var(--vm-primary); padding: 2px 8px; border-radius: 999px;">0 items</span>
            </div>
            <button type="button" class="vm-cart-drawer-close" id="vmCartCloseBtn">&times;</button>
        </div>
        <div class="vm-cart-drawer-body" id="vmCartItemsContainer">
            <div style="text-align: center; padding: 40px 10px; color: #94A3B8;">
                <span style="font-size: 48px; display: block; margin-bottom: 12px;">🛍️</span>
                <strong style="color: #334155; font-size: 16px; display: block; margin-bottom: 6px;">Your cart is empty</strong>
                <p style="font-size: 13px;">Explore verified items with 5% cashback reward</p>
            </div>
        </div>
        <div class="vm-cart-drawer-footer">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <span style="font-size: 14px; color: #64748B;">Estimated Subtotal:</span>
                <strong id="vmCartSubtotal" style="font-size: 18px; color: #0F172A;">₦0.00</strong>
            </div>
            <div style="display: flex; gap: 8px; margin-bottom: 16px; font-size: 12px; color: #059669; background: #ECFDF5; padding: 8px 12px; border-radius: 6px;">
                <span>🎁</span>
                <span>You will earn <strong>5% Cashback (₦0.00)</strong> upon receipt sign-off</span>
            </div>
            <a href="{{ route('customer.login') }}" style="display: block; text-align: center; background: var(--vm-primary); color: #FFFFFF; font-weight: 700; padding: 12px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(94, 23, 235, 0.25);">
                Proceed to Checkout
            </a>
        </div>
    </div>

    <!-- Interactive Client Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Location Modal Trigger
            const locBtn = document.getElementById('vmHeaderLocationBtn');
            const locModal = document.getElementById('vmLocationModal');
            const locClose = document.getElementById('vmCloseLocationModal');
            const activeCityLabel = document.querySelector('.vm-active-city-label');

            if (locBtn && locModal) {
                locBtn.addEventListener('click', () => locModal.classList.add('active'));
            }
            if (locClose && locModal) {
                locClose.addEventListener('click', () => locModal.classList.remove('active'));
            }

            // Location Selection
            document.querySelectorAll('.vm-lga-select-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const city = this.dataset.city;
                    fetch('{{ route("set.location") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ city: city, state: 'Akwa Ibom' })
                    }).then(res => res.json()).then(data => {
                        if (data.success) {
                            if (activeCityLabel) activeCityLabel.textContent = data.city;
                            const heroCityLabel = document.querySelector('.vm-proximity-highlight');
                            if (heroCityLabel) heroCityLabel.textContent = data.city + ', Akwa Ibom';
                            locModal.classList.remove('active');
                        }
                    });
                });
            });

            // Cart Drawer Trigger
            const cartBtn = document.getElementById('vmHeaderCartBtn');
            const cartDrawer = document.getElementById('vmCartDrawer');
            const cartOverlay = document.getElementById('vmCartOverlay');
            const cartClose = document.getElementById('vmCartCloseBtn');

            function toggleCart(open) {
                if (open) {
                    cartDrawer.classList.add('active');
                    cartOverlay.classList.add('active');
                } else {
                    cartDrawer.classList.remove('active');
                    cartOverlay.classList.remove('active');
                }
            }

            if (cartBtn) cartBtn.addEventListener('click', () => toggleCart(true));
            if (cartClose) cartClose.addEventListener('click', () => toggleCart(false));
            if (cartOverlay) cartOverlay.addEventListener('click', () => toggleCart(false));
        });
    </script>
    @stack('scripts')
</body>
</html>
