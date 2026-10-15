@extends('layouts.dashboard')

@section('title', 'Storefront Multi-Template Banners & Hero Studio Desk')
@section('role_title', 'Super Admin')
@section('page_title', 'Banners & Hero Studio')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🎨</span>
                <span>Storefront Multi-Template Banners &amp; Hero Studio Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Launch studio-grade banners across public web and mobile storefronts without needing a graphics designer. Choose from 6 smart templates with live visual simulation.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <a href="{{ route('storefront') }}" target="_blank" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 14px;">
                View Live Storefront 🌐
            </a>
        </div>
    </div>

    <!-- Quick Metrics Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="dash-card" style="padding: 18px 20px;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">Hero Slider Banners</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ $heroBannersCount ?? 0 }}</div>
            <span style="font-size: 11.5px; color: #5E17EB; font-weight: 600;">Rotates alongside category sidebar</span>
        </div>

        <div class="dash-card" style="padding: 18px 20px;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">Promotional Strips</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ $promoBannersCount ?? 0 }}</div>
            <span style="font-size: 11.5px; color: #D97706; font-weight: 600;">Inter-section conversion drivers</span>
        </div>

        <div class="dash-card" style="padding: 18px 20px;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">Active Live Banners</div>
            <div style="font-size: 26px; font-weight: 800; color: #10B981; margin: 4px 0;">{{ $activeBannersCount ?? 0 }}</div>
            <span style="font-size: 11.5px; color: #059669; font-weight: 600;">Currently rendering on storefront</span>
        </div>

        <div class="dash-card" style="padding: 18px 20px;">
            <div style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">Dynamic Studio Shield</div>
            <div style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 8px 0;">⚡ Fast Cache 3600s</div>
            <span style="font-size: 11.5px; color: #64748B; font-weight: 600;">Auto-purged on banner updates</span>
        </div>
    </div>

    <!-- Live Interactive Visual Simulation Preview -->
    <div class="dash-card" style="border: 1.5px solid rgba(94, 23, 235, 0.25); margin-bottom: 24px;">
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(243, 232, 255, 0.3) 100%);">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">🖥️</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Real-Time Storefront Banner Live Simulation</span>
                        <div class="dash-card-subtitle">Visual preview automatically reflects template changes, typography, badges, and image uploads before saving</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple" id="simCurrentTemplateBadge">✨ Luxury Split Mode</span>
        </div>

        <div style="padding: 24px;">
            <div id="simPreviewContainer">
                <div id="bannerSimulationCard" style="background: linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%); border: 1px solid rgba(94, 23, 235, 0.15); border-radius: 14px; padding: 28px 34px; box-shadow: 0 4px 20px rgba(94, 23, 235, 0.08); display: flex; justify-content: space-between; align-items: center; min-height: 200px; position: relative; overflow: hidden;">
                    
                    {{-- Decorative Watermark --}}
                    <div id="simWatermark" style="position: absolute; right: 20px; bottom: -20px; font-size: 160px; font-weight: 900; opacity: 0.04; user-select: none; pointer-events: none; line-height: 1;">
                        ✦
                    </div>

                    <div style="max-width: 580px; z-index: 2; position: relative;">
                        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                            <div id="simBadge" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 10px; border-radius: 999px; background: #FFFBE6; color: #D97706;">
                                <span id="simBadgeDot" style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                                <span id="simBadgeText">VERIFIED OMNICHANNEL MARKET</span>
                            </div>

                            <span id="simDiscountPill" style="display: none; background: #5E17EB; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px;">
                                -30% OFF
                            </span>
                        </div>

                        <h2 id="simTitle" style="font-size: clamp(22px, 3.5vw, 30px); font-weight: 800; color: #0F172A; line-height: 1.25; margin-bottom: 8px;">
                            Seamless Shopping, Swift Logistics
                        </h2>

                        <p id="simSubtitle" style="font-size: 14px; color: #64748B; margin-bottom: 14px; line-height: 1.45;">
                            Akwa Ibom's Premier Platform • Physical In-Shop Inspection • Escrow Protected
                        </p>

                        <div id="simFeatureTagsWrap" style="display: none; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                            {{-- Injected dynamically via JS --}}
                        </div>

                        <a id="simCtaBtn" href="#catalog" style="display: inline-flex; align-items: center; gap: 6px; background: #FFD700; color: #0F172A; font-weight: 800; font-size: 13.5px; padding: 10px 20px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(255, 215, 0, 0.35);">
                            <span id="simCtaText">Shop Verified Items</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                    {{-- Image / Pedestal Spotlight --}}
                    <div id="simPedestalWrap" style="position: relative; margin-left: 20px; flex-shrink: 0; z-index: 2;">
                        <div id="simPedestalBox" style="width: 120px; height: 120px; border-radius: 24px; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(94, 23, 235, 0.15); border: 2px solid rgba(255, 215, 0, 0.45);">
                            <img id="simImage" src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 95px; height: 95px; border-radius: 18px; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create New Banner Form Card -->
    <div class="dash-card" style="border: 1.5px solid var(--adm-border); margin-bottom: 32px;">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">➕</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Publish New Storefront Banner</span>
                        <div class="dash-card-subtitle">Choose an intelligent agency template or upload a full graphic cover</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Studio Publisher</span>
        </div>

        <div style="padding: 24px;">
            <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Visual Template Selector Grid (6 Elite Scenarios) -->
                <div style="margin-bottom: 24px;">
                    <label style="font-size: 12px; font-weight: 800; color: var(--adm-text); margin-bottom: 10px; display: block; text-transform: uppercase; letter-spacing: 0.5px;">
                        1. SELECT CREATIVE BANNER TEMPLATE (AUTOMATIC AGENCY STYLING)
                    </label>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 14px;">
                        
                        <!-- Template 1: Luxury Split -->
                        <label class="vm-tpl-card" style="border: 2px solid #5E17EB; border-radius: 12px; padding: 14px; background: #FAF5FF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="luxury_split" checked style="accent-color: #5E17EB;" onchange="updateSimTemplate('luxury_split')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">✨ Luxury Split Showcase</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">Editorial split card, 3D product pedestal, ambient blur halo, arrow CTA.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #5E17EB; margin-top: 6px; background: rgba(94, 23, 235, 0.1); padding: 2px 6px; border-radius: 4px;">DEFAULT / POPULAR</span>
                        </label>

                        <!-- Template 2: Full-Bleed Graphic Cover -->
                        <label class="vm-tpl-card" style="border: 1px solid var(--adm-border); border-radius: 12px; padding: 14px; background: #FFFFFF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="full_cover" style="accent-color: #5E17EB;" onchange="updateSimTemplate('full_cover')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">🖼️ Full-Bleed Graphic Cover</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">100% Canva / Photoshop ready. Image spans edge-to-edge as a clickable card.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #059669; margin-top: 6px; background: #ECFDF5; padding: 2px 6px; border-radius: 4px;">DESIGNER UPLOAD</span>
                        </label>

                        <!-- Template 3: Cyber Flash Deal -->
                        <label class="vm-tpl-card" style="border: 1px solid var(--adm-border); border-radius: 12px; padding: 14px; background: #FFFFFF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="flash_urgency" style="accent-color: #5E17EB;" onchange="updateSimTemplate('flash_urgency')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">⚡ Cyber Flash Deal</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">High-voltage urgency, pulsating red beacon, neon discount tag & strike price.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #DC2626; margin-top: 6px; background: #FEF2F2; padding: 2px 6px; border-radius: 4px;">FLASH SALES / CLEARANCE</span>
                        </label>

                        <!-- Template 4: Minimalist Bento -->
                        <label class="vm-tpl-card" style="border: 1px solid var(--adm-border); border-radius: 12px; padding: 14px; background: #FFFFFF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="minimal_bento" style="accent-color: #5E17EB;" onchange="updateSimTemplate('minimal_bento')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">◻️ Minimalist Bento Studio</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">Apple/SSENSE clean aesthetic, soft studio shadow, feature checkmark chips.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #0F172A; margin-top: 6px; background: #F1F5F9; padding: 2px 6px; border-radius: 4px;">TECH & LIFESTYLE</span>
                        </label>

                        <!-- Template 5: Fresh Harvest & Market -->
                        <label class="vm-tpl-card" style="border: 1px solid var(--adm-border); border-radius: 12px; padding: 14px; background: #FFFFFF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="fresh_market" style="accent-color: #5E17EB;" onchange="updateSimTemplate('fresh_market')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">🌱 Fresh Harvest &amp; Groceries</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">Lush emerald &amp; mint tones, local farm direct badge, circular organic platter.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #065F46; margin-top: 6px; background: #ECFDF5; padding: 2px 6px; border-radius: 4px;">FOOD & GROCERIES</span>
                        </label>

                        <!-- Template 6: Brand Prestige Flagship -->
                        <label class="vm-tpl-card" style="border: 1px solid var(--adm-border); border-radius: 12px; padding: 14px; background: #FFFFFF; cursor: pointer; display: block; transition: all 0.2s ease;">
                            <input type="radio" name="template_style" value="brand_prestige" style="accent-color: #5E17EB;" onchange="updateSimTemplate('brand_prestige')">
                            <strong style="display: block; font-size: 13.5px; color: #0F172A; margin-top: 6px;">👑 Imperial Brand Prestige</strong>
                            <span style="display: block; font-size: 11.5px; color: #64748B; margin-top: 2px;">Royal purple &amp; imperial gold, concentric golden seal rings, verified partner star.</span>
                            <span style="display: inline-block; font-size: 10px; font-weight: 700; color: #B45309; margin-top: 6px; background: #FEF3C7; padding: 2px 6px; border-radius: 4px;">FLAGSHIP / VERIFIED</span>
                        </label>

                    </div>
                </div>

                <!-- Form Fields Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 18px;">
                    
                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BANNER PLACEMENT SLOT</label>
                        <select name="banner_type" id="inputBannerType" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px; background: #FFF;">
                            <option value="hero_slider">Hero Slider (Top Storefront Deck)</option>
                            <option value="promo_strip">Promotional Strip (Between Sections)</option>
                            <option value="category_banner">Category Header Banner</option>
                            <option value="popup_modal">Announcement Popup Modal</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BADGE PILL TEXT</label>
                        <input type="text" name="badge_text" id="inputBadge" value="VERIFIED OMNICHANNEL MARKET" placeholder="e.g. FLASH DEAL • LIVE NOW or 100% FARM FRESH" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">DISCOUNT STICKER / CALLOUT (OPTIONAL)</label>
                        <input type="text" name="discount_tag" id="inputDiscountTag" placeholder="e.g. SAVE 40%, ₦5,000 OFF, or ★ OFFICIAL" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BANNER HEADLINE / TITLE *</label>
                        <input type="text" name="title" id="inputTitle" value="Seamless Shopping, Swift Logistics" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">SUBTITLE / PROMOTIONAL COPY</label>
                        <input type="text" name="subtitle" id="inputSubtitle" value="Akwa Ibom's Premier Platform • Physical In-Shop Inspection • Escrow Protected" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">FEATURE BULLET CHIPS (COMMA-SEPARATED)</label>
                        <input type="text" name="feature_tags" id="inputFeatureTags" placeholder="e.g. In-Shop Verified, Same-Day Uyo, Dual-OTP Safe" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">CALL TO ACTION BUTTON TEXT</label>
                        <input type="text" name="cta_text" id="inputCtaText" value="Shop Verified Items" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">TARGET DESTINATION URL / ANCHOR</label>
                        <input type="text" name="cta_url" id="inputCtaUrl" value="#catalog" placeholder="e.g. #catalog or /category/electronics" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BACKGROUND GRADIENT PRESET</label>
                        <select name="bg_gradient" id="inputBgGradient" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px; background: #FFF;">
                            <option value="linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)">Victorious Lavender & White (Default)</option>
                            <option value="linear-gradient(135deg, #2E0854 0%, #4C1D95 100%)">Royal Purple & Imperial Gold</option>
                            <option value="linear-gradient(135deg, #0F172A 0%, #311042 100%)">Obsidian Cyber & Neon Crimson</option>
                            <option value="linear-gradient(135deg, #F8FAFC 0%, #EDE9FE 100%)">Bento Studio Ice & Slate</option>
                            <option value="linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)">Fresh Produce Emerald & Mint</option>
                            <option value="linear-gradient(135deg, #EFF6FF 0%, #F5F3FF 100%)">Corridor Transit Sky Blue & Indigo</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">CUSTOM IMAGE UPLOAD (OPTIONAL)</label>
                        <input type="file" name="image" id="inputImage" accept="image/*" style="width: 100%; padding: 8px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 12px; background: #F8FAFC;">
                        <span style="display: block; font-size: 11px; color: #64748B; margin-top: 3px;">Full cover image for Template 2, or product cutout photo for split templates.</span>
                    </div>

                    <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" checked style="width: 18px; height: 18px; accent-color: #5E17EB;">
                            <span style="font-size: 13px; font-weight: 700; color: #0F172A;">Set Active on Storefront Immediately</span>
                        </label>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">SORT PRIORITY ORDER</label>
                        <input type="number" name="sort_order" value="1" min="0" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--adm-border); padding-top: 18px;">
                    <button type="reset" class="btn-dash btn-dash-outline">Reset Form</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 24px; font-size: 13.5px; font-weight: 800;">
                        Publish Banner to Storefront 🚀
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Banners Directory Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">📑</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Live Storefront Banners Directory</span>
                        <div class="dash-card-subtitle">Manage visibility, order sequence, and template configurations across all slots</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-gray">{{ $banners->count() }} Configured</span>
        </div>

        <div class="dash-table-container">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Slot</th>
                        <th>Template Style</th>
                        <th>Preview &amp; Title</th>
                        <th>Subtitle / Badge</th>
                        <th>Action Target</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th style="text-align: right;">Controls</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($banners as $banner)
                        <tr>
                            <td>
                                <span class="dash-badge {{ $banner->banner_type === 'hero_slider' ? 'dash-badge-purple' : 'dash-badge-gold' }}" style="font-size: 10.5px;">
                                    {{ strtoupper(str_replace('_', ' ', $banner->banner_type)) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $styleLabels = [
                                        'luxury_split' => ['label' => '✨ Luxury Split', 'class' => 'dash-badge-purple'],
                                        'full_cover' => ['label' => '🖼️ Full Cover', 'class' => 'dash-badge-green'],
                                        'flash_urgency' => ['label' => '⚡ Flash Deal', 'class' => 'dash-badge-red'],
                                        'minimal_bento' => ['label' => '◻️ Bento Studio', 'class' => 'dash-badge-gray'],
                                        'fresh_market' => ['label' => '🌱 Fresh Market', 'class' => 'dash-badge-green'],
                                        'brand_prestige' => ['label' => '👑 Prestige', 'class' => 'dash-badge-gold'],
                                    ];
                                    $cur = $styleLabels[$banner->template_style] ?? ['label' => '✨ Luxury Split', 'class' => 'dash-badge-purple'];
                                @endphp
                                <span class="dash-badge {{ $cur['class'] }}" style="font-size: 10.5px; font-weight: 700;">
                                    {{ $cur['label'] }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 48px; height: 48px; border-radius: 8px; background: {{ $banner->bg_gradient ?? '#FFF' }}; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.08); flex-shrink: 0; overflow: hidden;">
                                        <img src="{{ asset($banner->image_path ?: 'assets/img/vm_icon.jpg') }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <strong style="color: #0F172A; font-size: 13.5px; display: block;">{{ $banner->title }}</strong>
                                        <span style="font-size: 11px; color: #64748B;">ID #{{ $banner->id }} • Created {{ $banner->created_at ? $banner->created_at->diffForHumans() : 'Recently' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px; align-items: center; margin-bottom: 3px; flex-wrap: wrap;">
                                    <span style="font-size: 10.5px; font-weight: 700; color: #D97706; background: #FFFBE6; padding: 2px 7px; border-radius: 4px; display: inline-block;">
                                        {{ $banner->badge_text }}
                                    </span>
                                    @if(!empty($banner->discount_tag))
                                        <span style="font-size: 10px; font-weight: 800; background: #5E17EB; color: #FFFFFF; padding: 2px 6px; border-radius: 4px;">
                                            {{ $banner->discount_tag }}
                                        </span>
                                    @endif
                                </div>
                                <div style="font-size: 12px; color: #64748B; max-width: 260px; line-height: 1.3;">
                                    {{ Str::limit($banner->subtitle, 65) }}
                                </div>
                            </td>
                            <td>
                                <code style="font-size: 11px; background: #F1F5F9; padding: 3px 6px; border-radius: 4px; color: #5E17EB;">
                                    {{ $banner->cta_url }}
                                </code>
                                <div style="font-size: 11px; color: #64748B; margin-top: 2px;">{{ $banner->cta_text }}</div>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: #0F172A; font-size: 13px;">{{ $banner->sort_order }}</span>
                            </td>
                            <td>
                                @if($banner->is_active)
                                    <span class="dash-badge dash-badge-green">● Active</span>
                                @else
                                    <span class="dash-badge dash-badge-gray">Paused</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <form method="POST" action="{{ route('admin.banners.toggle', $banner->id) }}">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 10px;">
                                            {{ $banner->is_active ? 'Pause' : 'Activate' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.banners.destroy', $banner->id) }}" onsubmit="return confirm('Delete this storefront banner permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-dash" style="font-size: 11px; padding: 4px 10px; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #64748B;">
                                No promotional banners found. Use the studio publisher above to launch your first banner!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Simulation Interactive JavaScript Engine -->
    <script>
        let currentTemplate = 'luxury_split';

        function updateSimTemplate(tpl) {
            currentTemplate = tpl;
            const badge = document.getElementById('simCurrentTemplateBadge');
            const card = document.getElementById('bannerSimulationCard');
            const pedestal = document.getElementById('simPedestalWrap');
            const pedestalBox = document.getElementById('simPedestalBox');
            const watermark = document.getElementById('simWatermark');
            const bgSelect = document.getElementById('inputBgGradient');
            const title = document.getElementById('simTitle');
            const subtitle = document.getElementById('simSubtitle');
            const simBadge = document.getElementById('simBadge');
            const simBadgeDot = document.getElementById('simBadgeDot');
            const simCtaBtn = document.getElementById('simCtaBtn');

            // Reset border highlights on template cards
            document.querySelectorAll('.vm-tpl-card').forEach(el => {
                el.style.border = '1px solid var(--adm-border)';
                el.style.background = '#FFFFFF';
            });
            const selectedRadio = document.querySelector(`input[name="template_style"][value="${tpl}"]`);
            if (selectedRadio) {
                const parent = selectedRadio.closest('.vm-tpl-card');
                if (parent) {
                    parent.style.border = '2px solid #5E17EB';
                    parent.style.background = '#FAF5FF';
                }
            }

            if (tpl === 'full_cover') {
                badge.textContent = '🖼️ Full-Bleed Graphic Cover';
                badge.className = 'dash-badge dash-badge-green';
                watermark.style.display = 'none';
                pedestal.style.display = 'none';
                card.style.background = 'linear-gradient(135deg, #1E1B4B 0%, #311042 100%)';
                title.style.color = '#FFFFFF';
                subtitle.style.color = 'rgba(255, 255, 255, 0.85)';
                simBadge.style.background = 'rgba(255, 255, 255, 0.2)';
                simBadge.style.color = '#FFFFFF';
                simBadgeDot.style.background = '#FFD700';
                simCtaBtn.style.background = '#FFD700';
                simCtaBtn.style.color = '#0F172A';
            } else if (tpl === 'flash_urgency') {
                badge.textContent = '⚡ Cyber Flash Deal';
                badge.className = 'dash-badge dash-badge-red';
                watermark.style.display = 'block';
                watermark.textContent = '⚡';
                watermark.style.color = '#FFFFFF';
                pedestal.style.display = 'block';
                pedestalBox.style.borderRadius = '28px';
                pedestalBox.style.background = 'rgba(255, 255, 255, 0.1)';
                pedestalBox.style.border = '2px solid rgba(239, 68, 68, 0.5)';
                card.style.background = 'linear-gradient(135deg, #0F172A 0%, #311042 100%)';
                title.style.color = '#FFFFFF';
                subtitle.style.color = 'rgba(255, 255, 255, 0.85)';
                simBadge.style.background = 'rgba(239, 68, 68, 0.2)';
                simBadge.style.color = '#FCA5A5';
                simBadgeDot.style.background = '#EF4444';
                simCtaBtn.style.background = 'linear-gradient(135deg, #EF4444 0%, #DC2626 100%)';
                simCtaBtn.style.color = '#FFFFFF';
            } else if (tpl === 'minimal_bento') {
                badge.textContent = '◻️ Minimalist Bento Studio';
                badge.className = 'dash-badge dash-badge-gray';
                watermark.style.display = 'none';
                pedestal.style.display = 'block';
                pedestalBox.style.borderRadius = '20px';
                pedestalBox.style.background = '#FFFFFF';
                pedestalBox.style.border = '1px solid #E2E8F0';
                card.style.background = 'linear-gradient(135deg, #F8FAFC 0%, #EDE9FE 100%)';
                title.style.color = '#0F172A';
                subtitle.style.color = '#64748B';
                simBadge.style.background = '#0F172A';
                simBadge.style.color = '#FFFFFF';
                simBadgeDot.style.display = 'none';
                simCtaBtn.style.background = '#0F172A';
                simCtaBtn.style.color = '#FFFFFF';
            } else if (tpl === 'fresh_market') {
                badge.textContent = '🌱 Fresh Harvest & Groceries';
                badge.className = 'dash-badge dash-badge-green';
                watermark.style.display = 'block';
                watermark.textContent = '🌱';
                watermark.style.color = '#064E3B';
                pedestal.style.display = 'block';
                pedestalBox.style.borderRadius = '50%';
                pedestalBox.style.background = '#FFFFFF';
                pedestalBox.style.border = '3px solid #6EE7B7';
                card.style.background = 'linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)';
                title.style.color = '#064E3B';
                subtitle.style.color = '#065F46';
                simBadge.style.background = '#ECFDF5';
                simBadge.style.color = '#065F46';
                simBadgeDot.style.display = 'inline-block';
                simBadgeDot.style.background = '#059669';
                simCtaBtn.style.background = '#059669';
                simCtaBtn.style.color = '#FFFFFF';
            } else if (tpl === 'brand_prestige') {
                badge.textContent = '👑 Imperial Brand Prestige';
                badge.className = 'dash-badge dash-badge-gold';
                watermark.style.display = 'block';
                watermark.textContent = '👑';
                watermark.style.color = '#FFD700';
                pedestal.style.display = 'block';
                pedestalBox.style.borderRadius = '50%';
                pedestalBox.style.background = '#FFFFFF';
                pedestalBox.style.border = '3px solid #FFD700';
                card.style.background = 'linear-gradient(135deg, #2E0854 0%, #4C1D95 100%)';
                title.style.color = '#FFFFFF';
                subtitle.style.color = 'rgba(255, 255, 255, 0.85)';
                simBadge.style.background = 'rgba(255, 215, 0, 0.15)';
                simBadge.style.color = '#FDE047';
                simBadgeDot.style.display = 'inline-block';
                simBadgeDot.style.background = '#FFD700';
                simCtaBtn.style.background = 'linear-gradient(135deg, #FFD700 0%, #F59E0B 100%)';
                simCtaBtn.style.color = '#0F172A';
            } else {
                // Luxury Split Default
                badge.textContent = '✨ Luxury Split Showcase';
                badge.className = 'dash-badge dash-badge-purple';
                watermark.style.display = 'block';
                watermark.textContent = '✦';
                watermark.style.color = '#5E17EB';
                pedestal.style.display = 'block';
                pedestalBox.style.borderRadius = '24px';
                pedestalBox.style.background = '#FFFFFF';
                pedestalBox.style.border = '2px solid rgba(255, 215, 0, 0.45)';
                card.style.background = bgSelect ? bgSelect.value : 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)';
                title.style.color = '#0F172A';
                subtitle.style.color = '#64748B';
                simBadge.style.background = '#FFFBE6';
                simBadge.style.color = '#D97706';
                simBadgeDot.style.display = 'inline-block';
                simBadgeDot.style.background = '#D97706';
                simCtaBtn.style.background = '#FFD700';
                simCtaBtn.style.color = '#0F172A';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const inputTitle = document.getElementById('inputTitle');
            const inputSubtitle = document.getElementById('inputSubtitle');
            const inputBadge = document.getElementById('inputBadge');
            const inputDiscountTag = document.getElementById('inputDiscountTag');
            const inputFeatureTags = document.getElementById('inputFeatureTags');
            const inputCtaText = document.getElementById('inputCtaText');
            const inputBgGradient = document.getElementById('inputBgGradient');
            const inputImage = document.getElementById('inputImage');

            const simTitle = document.getElementById('simTitle');
            const simSubtitle = document.getElementById('simSubtitle');
            const simBadgeText = document.getElementById('simBadgeText');
            const simDiscountPill = document.getElementById('simDiscountPill');
            const simFeatureTagsWrap = document.getElementById('simFeatureTagsWrap');
            const simCtaText = document.getElementById('simCtaText');
            const bannerSimulationCard = document.getElementById('bannerSimulationCard');
            const simImage = document.getElementById('simImage');

            if (inputTitle && simTitle) {
                inputTitle.addEventListener('input', () => simTitle.textContent = inputTitle.value || 'Seamless Shopping, Swift Logistics');
            }
            if (inputSubtitle && simSubtitle) {
                inputSubtitle.addEventListener('input', () => simSubtitle.textContent = inputSubtitle.value || 'Akwa Ibom Premier Omnichannel Platform');
            }
            if (inputBadge && simBadgeText) {
                inputBadge.addEventListener('input', () => simBadgeText.textContent = inputBadge.value || 'VERIFIED OMNICHANNEL MARKET');
            }
            if (inputDiscountTag && simDiscountPill) {
                inputDiscountTag.addEventListener('input', () => {
                    if (inputDiscountTag.value.trim()) {
                        simDiscountPill.style.display = 'inline-block';
                        simDiscountPill.textContent = inputDiscountTag.value.trim();
                    } else {
                        simDiscountPill.style.display = 'none';
                    }
                });
            }
            if (inputFeatureTags && simFeatureTagsWrap) {
                inputFeatureTags.addEventListener('input', () => {
                    const tags = inputFeatureTags.value.split(',').map(t => t.trim()).filter(Boolean);
                    if (tags.length > 0) {
                        simFeatureTagsWrap.style.display = 'flex';
                        simFeatureTagsWrap.innerHTML = tags.map(t => `<span class="vm-banner-tag-chip" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(0,0,0,0.1); padding: 2px 7px; border-radius: 4px; font-size: 11px;">✦ ${t}</span>`).join('');
                    } else {
                        simFeatureTagsWrap.style.display = 'none';
                        simFeatureTagsWrap.innerHTML = '';
                    }
                });
            }
            if (inputCtaText && simCtaText) {
                inputCtaText.addEventListener('input', () => simCtaText.textContent = inputCtaText.value || 'Shop Verified Items');
            }
            if (inputBgGradient && bannerSimulationCard) {
                inputBgGradient.addEventListener('change', () => {
                    if (currentTemplate === 'luxury_split') {
                        bannerSimulationCard.style.background = inputBgGradient.value;
                    }
                });
            }
            if (inputImage && simImage) {
                inputImage.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = (e) => simImage.src = e.target.result;
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    </script>
@endsection
