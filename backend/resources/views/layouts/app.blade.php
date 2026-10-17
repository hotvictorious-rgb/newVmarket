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
    <script src="{{ asset('assets/js/vm-cart.js') }}" defer></script>

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

                /* Location Header Pill Button */
        .vm-header-location-pill {
            background: #FFFFFF;
            border: 1px solid var(--vm-border);
            border-radius: 999px;
            padding: 5px 12px;
            display: none;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        @media (min-width: 860px) {
            .vm-header-location-pill {
                display: inline-flex;
            }
        }

        .vm-header-location-pill:hover {
            border-color: var(--vm-primary);
            background: var(--vm-primary-light);
            box-shadow: 0 2px 8px rgba(94, 23, 235, 0.15);
            transform: translateY(-1px);
        }

        .vm-location-pin-icon {
            color: var(--vm-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vm-location-labels {
            display: flex;
            flex-direction: column;
            text-align: left;
            line-height: 1.15;
        }

        .vm-location-sub {
            font-size: 10px;
            color: #64748B;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .vm-location-main {
            font-size: 13px;
            color: #0F172A;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .vm-location-main .vm-caret {
            font-size: 10px;
            color: #94A3B8;
        }

        /* Modal Location Selector */
        .vm-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1050;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: vmFadeIn 0.2s ease;
        }

        .vm-modal-overlay.active {
            display: flex;
        }

        @keyframes vmFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes vmSlideUp {
            from { transform: translateY(16px) scale(0.97); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .vm-location-modal-card {
            background: #FFFFFF;
            border-radius: 22px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(226, 232, 240, 0.9);
            padding: 24px;
            position: relative;
            max-height: 92vh;
            overflow-y: auto;
            animation: vmSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .vm-modal-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #F1F5F9;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748B;
            transition: all 0.2s ease;
        }

        .vm-modal-close-btn:hover {
            background: #E2E8F0;
            color: #0F172A;
            transform: scale(1.05);
        }

        .vm-modal-header-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-right: 36px;
        }

        .vm-modal-icon-pill {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(94, 23, 235, 0.08);
            color: #5E17EB;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vm-modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            margin: 0 0 3px;
        }

        .vm-modal-subtitle {
            font-size: 12.5px;
            color: #64748B;
            margin: 0;
            line-height: 1.4;
        }

        .vm-country-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FAFAFA;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }

        .vm-country-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: #0F172A;
        }

        .vm-flag-tag {
            font-size: 11px;
            font-weight: 800;
            background: #0F172A;
            color: #FFFFFF;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .vm-country-sub {
            font-size: 12px;
            color: #64748B;
            font-weight: 500;
        }

        .vm-form-group {
            position: relative;
            margin-bottom: 16px;
        }

        .vm-form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 6px;
            display: block;
        }

        .vm-input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .vm-input-lead-icon {
            position: absolute;
            left: 14px;
            color: #94A3B8;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .vm-lga-search-input {
            width: 100%;
            height: 44px;
            padding-left: 42px !important;
            padding-right: 36px !important;
            font-size: 13.5px !important;
            border-radius: 12px !important;
            border: 1.5px solid #CBD5E1 !important;
            background: #FFFFFF !important;
            color: #0F172A !important;
            font-weight: 500 !important;
            box-sizing: border-box;
            transition: all 0.2s ease !important;
        }

        .vm-lga-search-input:focus {
            border-color: #5E17EB !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(94, 23, 235, 0.12) !important;
        }

        .vm-input-clear-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            font-size: 18px;
            color: #94A3B8;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }

        .vm-input-clear-btn:hover {
            color: #0F172A;
        }

        .vm-lga-dropdown-list {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            max-height: 220px;
            overflow-y: auto;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            box-shadow: 0 14px 35px -5px rgba(0, 0, 0, 0.15);
            z-index: 150;
            padding: 6px;
        }

        .vm-lga-dropdown-item {
            padding: 9px 12px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-size: 13px;
            color: #1E293B;
            transition: all 0.15s ease;
        }

        .vm-lga-dropdown-item:hover, .vm-lga-dropdown-item.active {
            background: rgba(94, 23, 235, 0.07);
            color: #5E17EB;
        }

        .vm-chips-heading {
            font-size: 11px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }

        .vm-city-chips-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vm-city-chip {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .vm-city-chip:hover {
            background: #E2E8F0;
            border-color: #CBD5E1;
        }

        .vm-city-chip.active {
            background: #5E17EB;
            color: #FFFFFF;
            border-color: #5E17EB;
            box-shadow: 0 2px 8px rgba(94, 23, 235, 0.35);
        }

        .vm-chip-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #94A3B8;
        }

        .vm-city-chip.active .vm-chip-dot {
            background: #FFD700;
        }

        .vm-selected-location-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .vm-selected-loc-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .vm-selected-pin {
            font-size: 20px;
            flex-shrink: 0;
        }

        .vm-selected-city-text {
            display: block;
            font-size: 13.5px;
            color: #0F172A;
            font-weight: 700;
        }

        .vm-selected-status-text {
            display: block;
            font-size: 11.5px;
            color: #16A34A;
            font-weight: 600;
            margin-top: 2px;
        }

        .vm-modal-footer-single {
            border-top: 1px solid #F1F5F9;
            padding-top: 16px;
        }

        .vm-btn-done {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #5E17EB 0%, #4D0FD4 100%);
            color: #FFFFFF;
            border: none;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .vm-btn-done:hover {
            background: linear-gradient(135deg, #4D0FD4 0%, #3C08AB 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(94, 23, 235, 0.45);
        }

        .vm-btn-done:active {
            transform: translateY(0);
        }

        /* Mobile Bottom App Navigation Bar (Mockup Screen 5) */
        .vm-mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #FFFFFF;
            border-top: 1px solid #E2E8F0;
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
            z-index: 999;
            justify-content: space-around;
            align-items: center;
            padding: 4px 6px;
        }

        @media (max-width: 768px) {
            .vm-mobile-bottom-nav {
                display: flex;
            }
            body {
                padding-bottom: 64px;
            }
        }

        .vm-mob-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #64748B;
            font-size: 11px;
            font-weight: 600;
            flex: 1;
            padding: 4px 0;
            transition: color 0.15s ease;
        }

        .vm-mob-nav-item.active,
        .vm-mob-nav-item:hover {
            color: var(--vm-primary);
        }

        .vm-mob-nav-icon {
            font-size: 19px;
            line-height: 1.2;
            margin-bottom: 1px;
        }

        .vm-mob-nav-badge {
            position: absolute;
            top: -4px;
            right: -8px;
            background: var(--vm-primary);
            color: #FFFFFF;
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 5px;
            border-radius: 999px;
            display: none;
        }

        /* =========================================================================
         * RESPONSIVE STOREFRONT & MOBILE 2-COLUMN CATALOG ARCHITECTURE
         * ========================================================================= */
        .vm-hero-wrapper {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 24px;
        }

        .vm-category-sidebar {
            display: none;
        }

        .vm-mobile-category-track {
            display: flex;
            overflow-x: auto;
            gap: 8px;
            padding: 4px 2px 10px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .vm-mobile-category-track::-webkit-scrollbar {
            display: none;
        }

        .vm-mob-cat-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFFFFF;
            border: 1px solid var(--vm-border);
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .vm-mob-cat-chip.active,
        .vm-mob-cat-chip:hover {
            background: #5E17EB;
            color: #FFFFFF;
            border-color: #5E17EB;
        }

        /* Value Proposition Strip (2x2 on Mobile, 4-Col on Desktop) */
        .vm-val-prop-strip {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 14px;
        }

        /* Amazon Discovery Quad Grid (1-Col Mobile, 2-Col Tablet, 4-Col Desktop) */
        .vm-amazon-discovery-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 32px;
        }

        /* Products Grid (MANDATORY 2-COLUMNS ON MOBILE, 3 ON TABLET, 4 ON DESKTOP, 5 ON ULTRA-WIDE) */
        .vm-products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            transition: opacity 0.2s ease;
        }

        .vm-product-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
            transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.22s ease, border-color 0.22s ease;
            position: relative;
        }

        .vm-product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(94, 23, 235, 0.09);
            border-color: rgba(94, 23, 235, 0.3);
        }

        .vm-card-action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        /* Screen Sizes >= 641px (Tablets) */
        @media (min-width: 641px) {
            .vm-amazon-discovery-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            .vm-products-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 14px !important;
            }
        }

        /* Screen Sizes >= 769px */
        @media (min-width: 769px) {
            .vm-val-prop-strip {
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
            }
        }

        /* Screen Sizes >= 1024px (Standard Desktop) */
        @media (min-width: 1024px) {
            .vm-hero-wrapper {
                flex-direction: row;
                gap: 20px;
            }
            .vm-category-sidebar {
                display: flex;
                width: 260px;
                flex-shrink: 0;
            }
            .vm-mobile-category-track {
                display: none;
            }
            .vm-amazon-discovery-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
            }
            .vm-products-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 18px !important;
            }
            .vm-product-card {
                border-radius: 14px;
            }
        }

        /* Screen Sizes >= 1440px (Ultra-Wide Monitors) */
        @media (min-width: 1440px) {
            .vm-products-grid {
                grid-template-columns: repeat(5, 1fr) !important;
                gap: 20px !important;
            }
        }

        /* Mobile specific card adjustments */
        @media (max-width: 480px) {
            .vm-card-action-grid {
                grid-template-columns: 1fr;
            }
            .vm-product-name {
                font-size: 13px !important;
                min-height: 32px !important;
            }
            .vm-price-current {
                font-size: 15px !important;
            }
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
                <a href="{{ route('storefront') }}#footer-guarantees">Help & Support</a>
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

            <!-- Global Search Bar with Action Button (Scrolls straight to #catalog) -->
            <form action="{{ route('storefront') }}#catalog" method="GET" class="vm-search-form">
                @if(request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
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
                    <a href="{{ route('customer.dashboard') }}" class="vm-action-btn" title="My Account">
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
                    <a href="{{ route('customer.register') }}" class="vm-action-btn vm-register-btn" style="background: var(--vm-primary); color: #fff; padding: 6px 14px; border-radius: 999px;" title="Register">
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
                    <div class="vm-nav-dropdown-menu">
                        <div class="vm-nav-dropdown-grid">
                            <a href="{{ route('storefront', ['category' => 'electronics-gadgets']) }}" class="vm-nav-dropdown-item">
                                <span class="vm-nav-cat-bullet">●</span>
                                <span>📱 Electronics &amp; Gadgets</span>
                            </a>
                            <a href="{{ route('storefront', ['category' => 'home-appliances']) }}" class="vm-nav-dropdown-item">
                                <span class="vm-nav-cat-bullet">●</span>
                                <span>🔌 Home &amp; Kitchen Appliances</span>
                            </a>
                            <a href="{{ route('storefront', ['category' => 'groceries-farm-produce']) }}" class="vm-nav-dropdown-item">
                                <span class="vm-nav-cat-bullet">●</span>
                                <span>🌾 Groceries &amp; Produce</span>
                            </a>
                            <a href="{{ route('storefront') }}?search=Phone" class="vm-nav-dropdown-item">
                                <span class="vm-nav-cat-bullet">●</span>
                                <span>💻 Phones &amp; Laptops</span>
                            </a>
                        </div>
                        <div class="vm-nav-dropdown-footer">
                            <a href="{{ route('storefront') }}#categories" class="vm-nav-dropdown-all-link" style="font-size: 11.5px; font-weight: 700; color: var(--vm-primary); text-decoration: none;">
                                Browse All Categories &rarr;
                            </a>
                        </div>
                    </div>
                </li>
                <li><a href="{{ route('storefront') }}#merchants" class="vm-nav-link">Verified Merchants</a></li>
                <li><a href="{{ route('storefront') }}#contact" class="vm-nav-link">Contact & Location</a></li>
            </ul>
        </div>
    </nav>

    <!-- 4. Main Page Content (With Standardized Dismissible Flash Alerts) -->
    <main>
        @if(session('status') || session('info') || session('error') || session('warning'))
            <div class="vm-container" style="padding-top: 16px;">
                @if(session('status'))
                    <div style="background: #ECFDF5; border: 1.5px solid #10B981; color: #065F46; padding: 12px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.1);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">✅</span>
                            <span>{{ session('status') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #065F46; cursor: pointer; padding: 0 4px;">&times;</button>
                    </div>
                @endif
                @if(session('info'))
                    <div style="background: #EFF6FF; border: 1.5px solid #3B82F6; color: #1E40AF; padding: 12px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(59, 130, 246, 0.1);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">ℹ️</span>
                            <span>{{ session('info') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #1E40AF; cursor: pointer; padding: 0 4px;">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div style="background: #FEF2F2; border: 1.5px solid #EF4444; color: #991B1B; padding: 12px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.1);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">⚠️</span>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #991B1B; cursor: pointer; padding: 0 4px;">&times;</button>
                    </div>
                @endif
                @if(session('warning'))
                    <div style="background: #FFFBEB; border: 1.5px solid #F59E0B; color: #92400E; padding: 12px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.1);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;">🔔</span>
                            <span>{{ session('warning') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; color: #92400E; cursor: pointer; padding: 0 4px;">&times;</button>
                    </div>
                @endif
            </div>
        @endif
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
                        <a href="javascript:void(0)" onclick="vmOpenPolicyModal('contact')">Contact &amp; Help Center</a>
                        <a href="javascript:void(0)" onclick="vmOpenPolicyModal('faq')">Frequently Asked Questions</a>
                        <a href="javascript:void(0)" onclick="vmOpenPolicyModal('about')">About Us</a>
                        <a href="{{ route('terms') }}">Terms And Conditions</a>
                        <a href="{{ route('legal.show', 'privacy-policy') }}">Privacy Policy</a>
                        <a href="{{ route('legal.show', 'return-and-refund-policy') }}">Return &amp; Refund Policy</a>
                        <a href="javascript:void(0)" onclick="vmOpenPolicyModal('cancellation')">Cancellation Policy</a>
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

        <!-- Interactive LGA Location Selection Modal (Legacy-Matching High-Fidelity) -->
    <div class="vm-modal-overlay" id="vmLocationModal" role="dialog" aria-modal="true" aria-labelledby="vmLocationModalTitle">
        <div class="vm-location-modal-card">
            <!-- Close Button -->
            <button type="button" class="vm-modal-close-btn" id="vmCloseLocationModal" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Modal Header -->
            <div class="vm-modal-header-top">
                <div class="vm-modal-icon-pill">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <div>
                    <h3 class="vm-modal-title" id="vmLocationModalTitle">Choose your location</h3>
                    <p class="vm-modal-subtitle">Delivery options and delivery speeds may vary for different locations</p>
                </div>
            </div>

            <!-- Country Badge Bar -->
            <div class="vm-country-bar">
                <div class="vm-country-badge">
                    <span class="vm-flag-tag">NG</span>
                    <span class="vm-country-name">Nigeria</span>
                </div>
                <span class="vm-country-sub">Nationwide Local Governments</span>
            </div>

            <!-- Search Input with Autocomplete -->
            <div class="vm-form-group vm-lga-search-group">
                <label for="vmLgaSearchInput" class="vm-form-label">Type your residential lga</label>
                <div class="vm-input-icon-wrap">
                    <span class="vm-input-lead-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input type="text" id="vmLgaSearchInput" class="vm-modal-input vm-lga-search-input" placeholder="Type your residential lga" value="{{ session('customer_city', 'Uyo') }}" autocomplete="off">
                    <button type="button" class="vm-input-clear-btn" id="vmLgaClearBtn" aria-label="Clear">×</button>
                </div>

                <!-- Autocomplete Dropdown List -->
                <div class="vm-lga-dropdown-list" id="vmLgaDropdownList" style="display: none;"></div>
            </div>

            <!-- Popular Local Governments Chips -->
            <div class="vm-form-group" style="margin-bottom: 16px;">
                <span class="vm-chips-heading">POPULAR LOCAL GOVERNMENTS:</span>
                <div class="vm-city-chips-grid">
                    @php
                        $popularCities = ['Abak', 'Eket', 'Ikot Abasi', 'Ikot Ekpene', 'Oron', 'Uyo'];
                        $currentActiveCity = session('customer_city', 'Uyo');
                    @endphp
                    @foreach($popularCities as $pCity)
                        <button type="button" class="vm-city-chip {{ $currentActiveCity === $pCity ? 'active' : '' }}" data-city="{{ $pCity }}" data-state="Akwa Ibom">
                            <span class="vm-chip-dot"></span>
                            <span class="vm-chip-name">{{ $pCity }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Active Selected Location Feedback Card -->
            <div class="vm-selected-location-card" id="vmSelectedLocCard">
                <div class="vm-selected-loc-left">
                    <span class="vm-selected-pin">📍</span>
                    <div>
                        <strong class="vm-selected-city-text" id="vmPreviewCity">{{ session('customer_city', 'Uyo') }}, {{ session('customer_state', 'Akwa Ibom') }}</strong>
                        <small class="vm-selected-status-text" id="vmPreviewStatus">Pickup and delivery available in this local government</small>
                    </div>
                </div>
            </div>

            <!-- Done Button -->
            <div class="vm-modal-footer-single">
                <button type="button" class="vm-btn-done" id="vmLocationModalSubmit">
                    <span>Done</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Embedded Canonical LGA List Dataset -->
    @php
        $canonicalLgasList = \Illuminate\Support\Facades\Cache::remember('vmarket_canonical_lgas_min', 86400, function () {
            return \App\Models\Lga::where('is_active', true)
                ->with('state:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'state' => $l->state->name ?? 'Nigeria']);
        });
    @endphp
    <script id="vmCanonicalLgasJson" type="application/json">{!! json_encode($canonicalLgasList) !!}</script>

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
                <span>You will earn <strong>5% Cashback (<span id="vmCartCashbackEstimate">₦0.00</span>)</strong> upon receipt sign-off</span>
            </div>
            <a href="{{ route('checkout') }}" id="vmCartCheckoutBtn" style="display: block; text-align: center; background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-weight: 800; font-size: 14px; padding: 13px; border-radius: 10px; text-decoration: none; box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35); transition: transform 0.15s ease;">
                Proceed to Checkout &rarr;
            </a>
        </div>
    </div>

    <!-- Platform Policy & Customer Care Modal -->
    <div id="vmPolicyModal" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 16px;">
        <div style="background: #FFFFFF; border-radius: 16px; width: 100%; max-width: 620px; max-height: 85vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #E2E8F0; position: relative;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: #FFFFFF; z-index: 10;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span id="vmPolicyIcon" style="font-size: 24px;">📋</span>
                    <div>
                        <h3 id="vmPolicyTitle" style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0;">Policy Information</h3>
                        <p style="font-size: 12px; color: #64748B; margin: 2px 0 0 0;">Victorious Market — Akwa Ibom Trusted Commerce</p>
                    </div>
                </div>
                <button type="button" onclick="vmClosePolicyModal()" style="background: #F1F5F9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #64748B;">&times;</button>
            </div>
            <div id="vmPolicyBody" style="padding: 24px; font-size: 14px; line-height: 1.6; color: #334155;">
                <!-- Populated dynamically via vmOpenPolicyModal -->
            </div>
            <div style="padding: 14px 24px; border-top: 1px solid #F1F5F9; background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 16px 16px;">
                <span style="font-size: 12px; color: #64748B;">Support Line: {{ $sysPhone ?? '0800-VMARKET' }}</span>
                <button type="button" onclick="vmClosePolicyModal()" style="background: #1E293B; color: #FFFFFF; font-weight: 600; padding: 8px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px;">Got it</button>
            </div>
        </div>
    </div>

    <!-- Dedicated Customer Authentication Interceptor Modal (Checkout & Order Protection) -->
    <div id="vmAuthCheckoutModal" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 16px;">
        <div style="background: #FFFFFF; border-radius: 20px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid #E2E8F0; position: relative;">
            
            <!-- Modal Header Banner -->
            <div style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 50%, #4338CA 100%); padding: 22px 24px; color: #FFFFFF; position: relative; border-radius: 19px 19px 0 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.35); padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #FFD700; text-transform: uppercase; letter-spacing: 0.5px;">
                        <span>🔒 Customer Account Required</span>
                    </div>
                    <button type="button" onclick="vmCloseAuthModal()" style="background: rgba(255, 255, 255, 0.15); border: none; width: 30px; height: 30px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #FFFFFF;">&times;</button>
                </div>
                <h3 id="vmAuthModalTitle" style="font-size: 19px; font-weight: 800; color: #FFFFFF; margin: 0 0 4px; line-height: 1.3;">
                    Sign In to Complete Checkout
                </h3>
                <p id="vmAuthModalSubtitle" style="font-size: 12.5px; color: #E0E7FF; margin: 0; line-height: 1.45;">
                    Your cart items are saved! Sign in or register in 30 seconds to lock in your order with 100% Escrow Protection and claim 5% cashback.
                </p>

                <!-- Value Chips -->
                <div style="display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap;">
                    <span style="font-size: 11px; font-weight: 700; background: rgba(255, 255, 255, 0.12); padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>🛡️</span> Escrow Safe
                    </span>
                    <span style="font-size: 11px; font-weight: 700; background: rgba(255, 255, 255, 0.12); padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>🪙</span> 5% Cashback
                    </span>
                    <span style="font-size: 11px; font-weight: 700; background: rgba(255, 255, 255, 0.12); padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>📱</span> Delivery OTP
                    </span>
                </div>
            </div>

            <!-- Tab Switcher (Sign In vs Register) -->
            <div style="padding: 16px 24px 0;">
                <div style="display: flex; background: #F1F5F9; padding: 4px; border-radius: 12px; gap: 4px;">
                    <button type="button" id="vmAuthTabBtnLogin" onclick="vmSwitchAuthTab('login')" style="flex: 1; padding: 9px; border-radius: 8px; border: none; font-size: 13.5px; font-weight: 800; cursor: pointer; transition: all 0.2s ease; background: #FFFFFF; color: #0F172A; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                        Sign In
                    </button>
                    <button type="button" id="vmAuthTabBtnRegister" onclick="vmSwitchAuthTab('register')" style="flex: 1; padding: 9px; border-radius: 8px; border: none; font-size: 13.5px; font-weight: 800; cursor: pointer; transition: all 0.2s ease; background: transparent; color: #64748B;">
                        Create Account
                    </button>
                </div>
            </div>

            <!-- Modal Body Container -->
            <div style="padding: 20px 24px 24px;">

                <!-- ================= TAB 1: CUSTOMER SIGN IN ================= -->
                <div id="vmAuthTabLogin" style="display: block;">
                    
                    <!-- Quick 1-Click Google Sign In -->
                    <a href="{{ route('auth.google.redirect') }}" style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 11px; border: 1.5px solid #CBD5E1; border-radius: 10px; background: #FFFFFF; color: #1E293B; font-size: 13.5px; font-weight: 700; text-decoration: none; margin-bottom: 14px; box-sizing: border-box; transition: background 0.15s ease;">
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                        <span>Continue with Google</span>
                    </a>

                    <div style="display: flex; align-items: center; margin: 16px 0; color: #94A3B8; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                        <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
                        <span style="padding: 0 10px;">Or sign in with email / phone</span>
                        <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
                    </div>

                    <form method="POST" action="{{ route('customer.login.post') }}">
                        @csrf
                        <input type="hidden" name="return_to" id="vmAuthLoginReturnTo" value="{{ route('checkout') }}">
                        
                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                                Email or Phone Number
                            </label>
                            <input type="text" name="login" required placeholder="e.g. 09118949035 or customer@email.com" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                                Password
                            </label>
                            <input type="password" name="password" required placeholder="Enter your password" style="width: 100%; padding: 11px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>

                        <button type="submit" style="width: 100%; padding: 13px; border-radius: 10px; border: none; background: linear-gradient(135deg, #5E17EB 0%, #4D0FD4 100%); color: #FFFFFF; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span>Sign In &amp; Continue &rarr;</span>
                        </button>
                    </form>

                    <p style="text-align: center; margin: 16px 0 0; font-size: 12.5px; color: #64748B;">
                        New to Victorious MARKET? 
                        <a href="javascript:void(0)" onclick="vmSwitchAuthTab('register')" style="color: #5E17EB; font-weight: 700; text-decoration: none;">Create free account</a>
                    </p>
                </div>

                <!-- ================= TAB 2: CUSTOMER REGISTRATION ================= -->
                <div id="vmAuthTabRegister" style="display: none;">
                    
                    <form method="POST" action="{{ route('customer.register.post') }}">
                        @csrf
                        <input type="hidden" name="return_to" id="vmAuthRegisterReturnTo" value="{{ route('checkout') }}">
                        <input type="hidden" name="terms" value="1">

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                Full Name *
                            </label>
                            <input type="text" name="name" required placeholder="e.g. Victor Bassey" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Phone Number (For OTP) *
                                </label>
                                <input type="tel" name="phone" required placeholder="080... or 090..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Email Address *
                                </label>
                                <input type="email" name="email" required placeholder="e.g. name@email.com" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Password *
                                </label>
                                <input type="password" name="password" required minlength="8" placeholder="Min. 8 characters" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">
                                    Confirm Password *
                                </label>
                                <input type="password" name="password_confirmation" required minlength="8" placeholder="Repeat password" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13px; outline: none; box-sizing: border-box;">
                            </div>
                        </div>

                        <button type="submit" style="width: 100%; padding: 13px; border-radius: 10px; border: none; background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span>Create Customer Account &amp; Proceed &rarr;</span>
                        </button>
                    </form>

                    <p style="text-align: center; margin: 14px 0 0; font-size: 12.5px; color: #64748B;">
                        Already registered? 
                        <a href="javascript:void(0)" onclick="vmSwitchAuthTab('login')" style="color: #5E17EB; font-weight: 700; text-decoration: none;">Sign in here</a>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Mobile Bottom App Navigation Bar (Mockup Screen 5 Parity) -->
    <div class="vm-mobile-bottom-nav">
        <a href="{{ route('storefront') }}" class="vm-mob-nav-item {{ request()->routeIs('storefront') ? 'active' : '' }}">
            <span class="vm-mob-nav-icon">🏠</span>
            <span class="vm-mob-nav-text">Home</span>
        </a>
        <a href="{{ route('storefront') }}#merchants" class="vm-mob-nav-item">
            <span class="vm-mob-nav-icon">🏪</span>
            <span class="vm-mob-nav-text">Plazas</span>
        </a>
        <button type="button" class="vm-mob-nav-item vm-mob-cart-trigger" onclick="if(window.vmCart) window.vmCart.openDrawer();" style="background: none; border: none; cursor: pointer;">
            <div style="position: relative; display: inline-block;">
                <span class="vm-mob-nav-icon">🛒</span>
                <span class="vm-mob-nav-badge" id="vmMobCartBadge">0</span>
            </div>
            <span class="vm-mob-nav-text">Cart</span>
        </button>
        <a href="{{ route('customer.dashboard') }}" class="vm-mob-nav-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
            <span class="vm-mob-nav-icon">📦</span>
            <span class="vm-mob-nav-text">Orders</span>
        </a>
        <a href="{{ Auth::check() ? route('customer.dashboard') : route('customer.login') }}" class="vm-mob-nav-item {{ request()->routeIs('customer.login', 'customer.register') ? 'active' : '' }}">
            <span class="vm-mob-nav-icon">👤</span>
            <span class="vm-mob-nav-text">{{ Auth::check() ? 'Account' : 'Login' }}</span>
        </a>
    </div>

    <!-- Interactive Client Scripts -->
    <script>
                document.addEventListener('DOMContentLoaded', function () {
            // Location Modal Trigger
            const locBtn = document.getElementById('vmHeaderLocationBtn');
            const locModal = document.getElementById('vmLocationModal');
            const locClose = document.getElementById('vmCloseLocationModal');
            const lgaInput = document.getElementById('vmLgaSearchInput');
            const lgaClear = document.getElementById('vmLgaClearBtn');
            const lgaDropdown = document.getElementById('vmLgaDropdownList');
            const doneBtn = document.getElementById('vmLocationModalSubmit');
            const previewCity = document.getElementById('vmPreviewCity');
            const previewStatus = document.getElementById('vmPreviewStatus');
            const activeCityLabel = document.querySelector('.vm-active-city-label');

            let selectedCity = '{{ session('customer_city', 'Uyo') }}';
            let selectedState = '{{ session('customer_state', 'Akwa Ibom') }}';
            let selectedLgaId = {{ session('customer_lga_id', 69) }};

            // Parse embedded canonical LGAs
            let allLgas = [];
            try {
                const lgaScript = document.getElementById('vmCanonicalLgasJson');
                if (lgaScript) allLgas = JSON.parse(lgaScript.textContent);
            } catch (e) {
                console.error('Failed to parse canonical LGAs list', e);
            }

            // Open / Close modal
            function openLocationModal() {
                if (locModal) locModal.classList.add('active');
            }
            function closeLocationModal() {
                if (locModal) locModal.classList.remove('active');
            }

            if (locBtn) locBtn.addEventListener('click', openLocationModal);
            if (locClose) locClose.addEventListener('click', closeLocationModal);
            if (locModal) {
                locModal.addEventListener('click', (e) => {
                    if (e.target === locModal) closeLocationModal();
                });
            }

            // Global trigger hook for any "Change Location" button on page
            window.vmOpenLocationModal = openLocationModal;

            // Update preview feedback card
            function updatePreview(cityName, stateName, statusText) {
                selectedCity = cityName;
                selectedState = stateName || 'Akwa Ibom';
                if (previewCity) previewCity.textContent = `${selectedCity}, ${selectedState}`;
                if (lgaInput) lgaInput.value = selectedCity;
                
                // Highlight active chip
                document.querySelectorAll('.vm-city-chip').forEach(c => {
                    if (c.dataset.city.toLowerCase() === selectedCity.toLowerCase()) {
                        c.classList.add('active');
                    } else {
                        c.classList.remove('active');
                    }
                });

                if (previewStatus) {
                    if (statusText) {
                        previewStatus.textContent = statusText;
                    } else if (selectedCity.toLowerCase() === 'uyo') {
                        previewStatus.textContent = 'Pickup and delivery available in this local government';
                        previewStatus.style.color = '#16A34A';
                    } else if (['eket', 'ikot ekpene', 'oron'].includes(selectedCity.toLowerCase())) {
                        previewStatus.textContent = 'Delivery corridor active (12-24 hrs delivery speed)';
                        previewStatus.style.color = '#16A34A';
                    } else {
                        previewStatus.textContent = 'Delivery options and speeds vary by regional dispatch';
                        previewStatus.style.color = '#0284C7';
                    }
                }
            }

            // Quick City Chips Click
            document.querySelectorAll('.vm-city-chip').forEach(chip => {
                chip.addEventListener('click', function () {
                    const city = this.dataset.city;
                    const state = this.dataset.state || 'Akwa Ibom';
                    updatePreview(city, state);
                });
            });

            // Live Autocomplete Search
            if (lgaInput && lgaDropdown) {
                lgaInput.addEventListener('input', function () {
                    const q = this.value.trim().toLowerCase();
                    if (q.length === 0) {
                        lgaDropdown.style.display = 'none';
                        return;
                    }

                    const matches = allLgas.filter(l => l.name.toLowerCase().includes(q) || (l.state && l.state.toLowerCase().includes(q))).slice(0, 8);
                    if (matches.length === 0) {
                        lgaDropdown.innerHTML = '<div style="padding: 10px 12px; font-size: 12.5px; color: #94A3B8; text-align: center;">No matching LGA found</div>';
                        lgaDropdown.style.display = 'block';
                        return;
                    }

                    let html = '';
                    matches.forEach(m => {
                        const isAkwa = (m.state && m.state.toLowerCase() === 'akwa ibom');
                        html += `
                            <div class="vm-lga-dropdown-item" data-id="${m.id}" data-city="${m.name}" data-state="${m.state}">
                                <div>
                                    <strong>${m.name}</strong>
                                    <span class="vm-lga-dropdown-state">${m.state}</span>
                                </div>
                                <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; ${isAkwa ? 'background: #ECFDF5; color: #059669;' : 'background: #F1F5F9; color: #64748B;'}">
                                    ${isAkwa ? '⚡ Active Zone' : 'Nationwide'}
                                </span>
                            </div>
                        `;
                    });

                    lgaDropdown.innerHTML = html;
                    lgaDropdown.style.display = 'block';

                    // Attach click to dropdown items
                    lgaDropdown.querySelectorAll('.vm-lga-dropdown-item').forEach(item => {
                        item.addEventListener('click', function () {
                            const c = this.dataset.city;
                            const s = this.dataset.state;
                            selectedLgaId = this.dataset.id;
                            updatePreview(c, s);
                            lgaDropdown.style.display = 'none';
                        });
                    });
                });

                // Clear button
                if (lgaClear) {
                    lgaClear.addEventListener('click', function () {
                        lgaInput.value = '';
                        lgaInput.focus();
                        lgaDropdown.style.display = 'none';
                    });
                }

                // Hide dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!lgaInput.contains(e.target) && !lgaDropdown.contains(e.target)) {
                        lgaDropdown.style.display = 'none';
                    }
                });
            }

            // Done Button / Location Submit (Dynamic Product Swapper)
            if (doneBtn) {
                doneBtn.addEventListener('click', function () {
                    doneBtn.disabled = true;
                    doneBtn.innerHTML = '<span>Saving...</span>';

                    fetch('{{ route("set.location") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            city: selectedCity,
                            state: selectedState,
                            lga_id: selectedLgaId
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        doneBtn.disabled = false;
                        doneBtn.innerHTML = '<span>Done</span>';

                        if (data.success) {
                            // Update Header Pill
                            if (activeCityLabel) activeCityLabel.textContent = data.city;

                            // Update Proximity Strip on Homepage
                            const proximityCityText = document.getElementById('vmProximityCityText');
                            if (proximityCityText) proximityCityText.textContent = `${data.city}, ${data.state}`;
                            const proximityStatusTag = document.getElementById('vmProximityStatusTag');
                            if (proximityStatusTag && data.status_badge) proximityStatusTag.textContent = data.status_badge;

                            // Update Dynamic City Labels across Page
                            document.querySelectorAll('.vm-dynamic-city-label').forEach(el => el.textContent = data.city);

                            // Close modal
                            closeLocationModal();

                            // DYNAMICALLY FETCH PRODUCTS FOR THIS LGA
                            const productsGrid = document.getElementById('vmProductsGridContainer');
                            const shopsWrap = document.getElementById('vmShopsGridContainer');

                            if (productsGrid) {
                                productsGrid.style.opacity = '0.4';

                                fetch(`{{ route("storefront") }}?ajax=1&city=${encodeURIComponent(data.city)}&state=${encodeURIComponent(data.state)}`, {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                .then(r => r.json())
                                .then(catalogData => {
                                    productsGrid.style.opacity = '1';
                                    if (catalogData.products_html) {
                                        productsGrid.innerHTML = catalogData.products_html;
                                    }
                                    if (shopsWrap && catalogData.shops_html) {
                                        shopsWrap.innerHTML = catalogData.shops_html;
                                    }
                                })
                                .catch(err => {
                                    console.error('Error refreshing products dynamically:', err);
                                    productsGrid.style.opacity = '1';
                                });
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error setting location:', err);
                        doneBtn.disabled = false;
                        doneBtn.innerHTML = '<span>Done</span>';
                    });
                });
            }

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

            // Platform Policy Modal Engine
            const policyContent = {
                contact: {
                    icon: '📞',
                    title: 'Contact & Help Center',
                    html: `
                        <p style="margin-bottom: 12px;">Need help with your order, delivery, or merchant inquiry? Our Uyo-based operations desk is ready to support you.</p>
                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                            <strong>Customer Support Desk:</strong><br>
                            Phone: <a href="tel:{{ $sysPhone ?? '08008627538' }}" style="color: #6B21A8; font-weight: 600;">{{ $sysPhone ?? '0800-VMARKET' }}</a><br>
                            Email: <a href="mailto:{{ $sysEmail ?? 'support@victoriousmarket.ng' }}" style="color: #6B21A8; font-weight: 600;">{{ $sysEmail ?? 'support@victoriousmarket.ng' }}</a><br>
                            Hours: Mon – Sat, 8:00 AM – 6:00 PM (WAT)
                        </div>
                        <p style="font-size: 13px; color: #64748B;">For rider escalations or shop pickup verification, please quote your <strong>Order Ref Number</strong> when calling.</p>
                    `
                },
                faq: {
                    icon: '❓',
                    title: 'Frequently Asked Questions',
                    html: `
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <strong style="color: #0F172A;">How does Escrow Protection work?</strong>
                                <p style="margin-top: 4px; font-size: 13px;">Your payment is safely held by Victorious Market until you inspect and physically receive your order via secure OTP handoff.</p>
                            </div>
                            <div>
                                <strong style="color: #0F172A;">Can I inspect items before paying?</strong>
                                <p style="margin-top: 4px; font-size: 13px;">Yes! Choose <em>In-Shop Pickup (Pay on Inspection)</em> at checkout to reserve the product, visit the vendor plaza, inspect the item, and pay on the spot.</p>
                            </div>
                            <div>
                                <strong style="color: #0F172A;">How fast is delivery within Uyo & Akwa Ibom?</strong>
                                <p style="margin-top: 4px; font-size: 13px;">Intra-Uyo deliveries arrive within 1–3 hours. LGA-to-LGA and corridor orders are dispatched along verified transit corridors.</p>
                            </div>
                            <div>
                                <strong style="color: #0F172A;">What is the 5% Cashback Reward?</strong>
                                <p style="margin-top: 4px; font-size: 13px;">Every verified order earns 5% cashback credited directly to your customer balance upon confirmed OTP delivery sign-off.</p>
                            </div>
                        </div>
                    `
                },
                about: {
                    icon: '🏪',
                    title: 'About Victorious Market',
                    html: `
                        <p style="margin-bottom: 12px;">Victorious Market is Akwa Ibom\\'s premier multi-merchant marketplace connecting verified plaza shops in Uyo, Ikot Ekpene, Eket, and beyond with shoppers across Nigeria.</p>
                        <p style="margin-bottom: 12px;">We bridge physical shopping with digital convenience through accredited merchant verifications, directional LGA-to-LGA logistics, Paystack escrow security, and in-shop physical reservation.</p>
                        <div style="background: #FAF5FF; border: 1px solid #E9D5FF; padding: 12px; border-radius: 8px; font-size: 13px; color: #581C87;">
                            <strong>Our Core Values:</strong> 100% Genuine Products, Secure Escrow Settlement, Transparent LGA Logistics, and Empowering Local Nigerian Plaza Merchants.
                        </div>
                    `
                },
                terms: {
                    icon: '📜',
                    title: 'Terms & Conditions',
                    html: `
                        <p style="margin-bottom: 10px;">By using Victorious Market, you agree to fair and transparent marketplace guidelines:</p>
                        <ul style="padding-left: 20px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                            <li>All orders require OTP verification (Handover Code) between rider and customer or counter personnel to guarantee delivery integrity.</li>
                            <li>Delivery fees are calculated directionally based on LGA transit corridors and are distinct from product pricing.</li>
                            <li>Merchants are accredited business owners operating registered shops and commercial stalls in verified commercial plazas.</li>
                            <li>Fraudulent cancellation after rider dispatch may incur standard transit logistics recovery fees.</li>
                        </ul>
                    `
                },
                privacy: {
                    icon: '🔒',
                    title: 'Privacy Policy',
                    html: `
                        <p style="margin-bottom: 10px;">We take your data confidentiality and personal privacy seriously:</p>
                        <ul style="padding-left: 20px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                            <li>We collect necessary contact information (phone number, delivery LGA, street address) strictly for order fulfillment and OTP confirmation.</li>
                            <li>Financial credentials and card numbers are processed directly through Paystack\\'s PCI-DSS compliant payment gateway. Victorious Market never stores card CVVs or pins.</li>
                            <li>Your contact details are shared only with the assigned verified rider delivering your specific package.</li>
                        </ul>
                    `
                },
                refund: {
                    icon: '💰',
                    title: 'Refund Policy',
                    html: `
                        <p style="margin-bottom: 10px;">Our Paystack escrow system ensures you never lose money on unfulfilled or defective goods:</p>
                        <ul style="padding-left: 20px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                            <li>If an item is unavailable at the merchant\\'s plaza after payment, a full 100% refund is initiated immediately to your source account or customer wallet.</li>
                            <li>If a delivered item does not match product specifications upon arrival, reject the delivery OTP and notify our support desk within 2 hours.</li>
                            <li>Approved refunds are processed within 24 business hours back through Paystack gateway.</li>
                        </ul>
                    `
                },
                return: {
                    icon: '🔄',
                    title: 'Return Policy',
                    html: `
                        <p style="margin-bottom: 10px;">Returns are hassle-free with our verified merchant network:</p>
                        <ul style="padding-left: 20px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                            <li>Electronics, gadgets, and home appliances carry a minimum 48-hour testing window for manufacturer defects.</li>
                            <li>Returned items must be intact with original packaging, warranty seals, and accessories.</li>
                            <li>Items can be returned directly to the vendor\\'s physical shop address or collected by a verified Victorious Market rider.</li>
                        </ul>
                    `
                },
                cancellation: {
                    icon: '❌',
                    title: 'Cancellation Policy',
                    html: `
                        <p style="margin-bottom: 10px;">Order cancellation terms:</p>
                        <ul style="padding-left: 20px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                            <li>Orders can be cancelled with 100% automatic refund prior to merchant packaging and rider dispatch.</li>
                            <li>In-Shop reservations that are not claimed within the designated 24-hour pickup window are automatically cancelled with zero penalty.</li>
                            <li>To cancel an active order, navigate to your <strong>Customer Portal &rarr; Orders</strong> or call customer care.</li>
                        </ul>
                    `
                }
            };

            window.vmOpenPolicyModal = function(type) {
                const modal = document.getElementById('vmPolicyModal');
                const titleEl = document.getElementById('vmPolicyTitle');
                const iconEl = document.getElementById('vmPolicyIcon');
                const bodyEl = document.getElementById('vmPolicyBody');
                if (!modal || !bodyEl) return;

                const data = policyContent[type] || policyContent['about'];
                titleEl.textContent = data.title;
                iconEl.textContent = data.icon;
                bodyEl.innerHTML = data.html;
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.vmClosePolicyModal = function() {
                const modal = document.getElementById('vmPolicyModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            };

            const pModal = document.getElementById('vmPolicyModal');
            if (pModal) {
                pModal.addEventListener('click', function(e) {
                    if (e.target === pModal) vmClosePolicyModal();
                });
            }

            // Customer Authentication Interceptor Engine
            window.vmIsCustomerAuth = {{ Auth::check() && Auth::user()->user_type === 'customer' ? 'true' : 'false' }};

            window.vmOpenAuthModal = function(options) {
                options = options || {};
                const modal = document.getElementById('vmAuthCheckoutModal');
                if (!modal) return;

                const titleEl = document.getElementById('vmAuthModalTitle');
                const subtitleEl = document.getElementById('vmAuthModalSubtitle');
                const loginReturn = document.getElementById('vmAuthLoginReturnTo');
                const regReturn = document.getElementById('vmAuthRegisterReturnTo');

                const targetUrl = options.redirectUrl || '{{ route("checkout") }}';
                if (loginReturn) loginReturn.value = targetUrl;
                if (regReturn) regReturn.value = targetUrl;

                if (options.action === 'reservation') {
                    if (titleEl) titleEl.textContent = 'Sign In to Reserve for In-Shop Inspection';
                    if (subtitleEl) subtitleEl.textContent = 'Sign in or register so your 24-hour physical shop inspection pass is officially tied to your phone number.';
                } else {
                    if (titleEl) titleEl.textContent = options.title || 'Sign In to Complete Checkout';
                    if (subtitleEl) subtitleEl.textContent = options.message || 'You can freely browse and add items to your cart, but only customers with accounts can place orders and claim 5% cashback.';
                }

                if (options.tab === 'register') {
                    window.vmSwitchAuthTab('register');
                } else {
                    window.vmSwitchAuthTab('login');
                }

                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.vmCloseAuthModal = function() {
                const modal = document.getElementById('vmAuthCheckoutModal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            };

            window.vmSwitchAuthTab = function(tab) {
                const loginTab = document.getElementById('vmAuthTabLogin');
                const regTab = document.getElementById('vmAuthTabRegister');
                const loginBtn = document.getElementById('vmAuthTabBtnLogin');
                const regBtn = document.getElementById('vmAuthTabBtnRegister');

                if (tab === 'register') {
                    if (loginTab) loginTab.style.display = 'none';
                    if (regTab) regTab.style.display = 'block';
                    if (loginBtn) {
                        loginBtn.style.background = 'transparent';
                        loginBtn.style.color = '#64748B';
                        loginBtn.style.boxShadow = 'none';
                    }
                    if (regBtn) {
                        regBtn.style.background = '#FFFFFF';
                        regBtn.style.color = '#0F172A';
                        regBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.06)';
                    }
                } else {
                    if (loginTab) loginTab.style.display = 'block';
                    if (regTab) regTab.style.display = 'none';
                    if (loginBtn) {
                        loginBtn.style.background = '#FFFFFF';
                        loginBtn.style.color = '#0F172A';
                        loginBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.06)';
                    }
                    if (regBtn) {
                        regBtn.style.background = 'transparent';
                        regBtn.style.color = '#64748B';
                        regBtn.style.boxShadow = 'none';
                    }
                }
            };

            const aModal = document.getElementById('vmAuthCheckoutModal');
            if (aModal) {
                aModal.addEventListener('click', function(e) {
                    if (e.target === aModal) vmCloseAuthModal();
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
