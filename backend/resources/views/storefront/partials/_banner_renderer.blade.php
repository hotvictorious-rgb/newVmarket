@php
    $style = $banner->template_style ?? 'luxury_split';
    $hasImage = !empty($banner->image_path);
    $bgGradient = $banner->bg_gradient ?? 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)';
    $textColor = $banner->text_color ?? '#0F172A';
    $featureTags = method_exists($banner, 'getFeatureTagsListAttribute') ? $banner->feature_tags_list : (!empty($banner->feature_tags) ? array_filter(array_map('trim', explode(',', $banner->feature_tags))) : []);
@endphp

@if($style === 'full_cover')
    {{-- TEMPLATE 2: FULL-BLEED GRAPHIC COVER (Canva / Photoshop Banner) --}}
    <div class="vm-hero-slide-item vm-banner-template-full-cover {{ (isset($index) && $index === 0) ? 'active' : '' }}"
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: block;' }} position: relative; border-radius: 14px; overflow: hidden; box-shadow: var(--vm-shadow-xs);"
         data-slide-index="{{ $index ?? 0 }}">
        <a href="{{ $banner->cta_url ?? '#catalog' }}" class="vm-banner-cover-wrap" style="text-decoration: none; display: block; position: relative;">
            @if($hasImage)
                <img src="{{ asset($banner->image_path) }}" alt="{{ $banner->title }}" class="vm-banner-cover-img" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            @else
                <div style="width: 100%; height: 100%; background: {{ $bgGradient }}; padding: 32px 36px; display: flex; flex-direction: column; justify-content: center; position: relative; min-height: 220px;">
                    @if(!empty($banner->badge_text))
                        <span style="display: inline-block; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); color: {{ $textColor }}; font-size: 11px; font-weight: 800; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; margin-bottom: 8px; width: fit-content;">
                            {{ $banner->badge_text }}
                        </span>
                    @endif
                    <h2 style="font-size: clamp(22px, 3.5vw, 32px); font-weight: 800; color: {{ $textColor }}; margin: 0 0 8px; line-height: 1.2;">
                        {{ $banner->title }}
                    </h2>
                    @if(!empty($banner->subtitle))
                        <p style="font-size: 14px; color: {{ $textColor }}; opacity: 0.85; margin: 0 0 16px; max-width: 550px;">
                            {{ $banner->subtitle }}
                        </p>
                    @endif
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: #FFD700; color: #0F172A; font-weight: 800; font-size: 13px; padding: 8px 18px; border-radius: 8px; width: fit-content;">
                        {{ $banner->cta_text ?? 'Shop Now' }} &rarr;
                    </span>
                </div>
            @endif

            @if(!empty($banner->discount_tag))
                <div class="vm-banner-discount-sticker" style="background: #EF4444; color: #FFFFFF;">
                    {{ $banner->discount_tag }}
                </div>
            @endif
        </a>
    </div>

@elseif($style === 'flash_urgency')
    {{-- TEMPLATE 3: CYBER FLASH DEAL & MIDNIGHT DROP --}}
    <div class="vm-hero-slide-item vm-banner-template-flash {{ (isset($index) && $index === 0) ? 'active' : '' }}" 
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: flex;' }} background: {{ $bgGradient }}; border: 1.5px solid rgba(239, 68, 68, 0.35); border-radius: 14px; padding: 26px 30px; box-shadow: 0 8px 30px rgba(239, 68, 68, 0.12); position: relative; overflow: hidden; justify-content: space-between; align-items: center; min-height: 220px;"
         data-slide-index="{{ $index ?? 0 }}">
        
        {{-- High-Voltage Background Watermark --}}
        <div style="position: absolute; right: -20px; bottom: -20px; font-size: 160px; font-weight: 900; opacity: 0.04; color: #FFFFFF; user-select: none; pointer-events: none; line-height: 1;">
            ⚡
        </div>

        <div style="max-width: 580px; position: relative; z-index: 2;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 10px; border-radius: 999px; background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.5); color: #FCA5A5;">
                    <span class="vm-beacon-ping"></span>
                    <span>{{ $banner->badge_text ?? '🔥 FLASH DEAL • LIVE NOW' }}</span>
                </div>

                @if(!empty($banner->discount_tag))
                    <span style="background: #FFD700; color: #0F172A; font-size: 11px; font-weight: 900; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.4px;">
                        {{ $banner->discount_tag }}
                    </span>
                @endif
            </div>

            <h2 class="vm-hero-title" style="font-size: clamp(22px, 3.5vw, 32px); font-weight: 900; color: {{ $textColor }}; line-height: 1.2; margin-bottom: 8px; letter-spacing: -0.5px;">
                {{ $banner->title }}
            </h2>

            @if(!empty($banner->subtitle))
                <p class="vm-hero-subtitle" style="font-size: clamp(12.5px, 2vw, 14px); color: rgba(255, 255, 255, 0.85); margin-bottom: 14px; line-height: 1.45;">
                    {{ $banner->subtitle }}
                </p>
            @endif

            @if(!empty($featureTags))
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                    @foreach($featureTags as $tag)
                        <span class="vm-banner-tag-chip" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: #FFFFFF;">
                            ⚡ {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="vm-hero-actions" style="display: flex; gap: 12px; align-items: center;">
                <a href="{{ $banner->cta_url ?? '#catalog' }}" class="vm-hero-cta-btn" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #FFFFFF; font-weight: 800; font-size: 13.5px; padding: 10px 22px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4); transition: all 0.2s ease;">
                    <span>{{ $banner->cta_text ?? 'Claim Deal Now' }}</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        {{-- Product Spotlight on Right --}}
        <div class="vm-hero-pedestal-wrap" style="position: relative; margin-left: 16px; flex-shrink: 0; z-index: 2;">
            <div style="width: 130px; height: 130px; border-radius: 28px; background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(12px); border: 2px solid rgba(239, 68, 68, 0.4); display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative;">
                <div style="position: absolute; inset: 0; border-radius: 28px; background: radial-gradient(circle, rgba(239, 68, 68, 0.25) 0%, transparent 70%);"></div>
                <img src="{{ asset($hasImage ? $banner->image_path : 'assets/img/vm_icon.jpg') }}" alt="{{ $banner->title }}" class="vm-hero-pedestal-float" style="width: 100px; height: 100px; border-radius: 20px; object-fit: cover; position: relative; z-index: 2;">
            </div>
        </div>
    </div>

@elseif($style === 'minimal_bento')
    {{-- TEMPLATE 4: APPLE / SSENSE MINIMALIST BENTO --}}
    <div class="vm-hero-slide-item vm-banner-template-bento {{ (isset($index) && $index === 0) ? 'active' : '' }}" 
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: flex;' }} background: {{ $bgGradient }}; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 14px; padding: 28px 34px; box-shadow: var(--vm-shadow-xs); position: relative; overflow: hidden; justify-content: space-between; align-items: center; min-height: 220px;"
         data-slide-index="{{ $index ?? 0 }}">
        
        <div style="max-width: 580px; position: relative; z-index: 2;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 2px 8px; border-radius: 4px; background: #0F172A; color: #FFFFFF;">
                    <span>{{ $banner->badge_text ?? 'CURATED EDIT' }}</span>
                </div>

                @if(!empty($banner->discount_tag))
                    <span style="font-size: 11px; font-weight: 800; color: #5E17EB; background: #EDE9FE; padding: 2px 8px; border-radius: 4px;">
                        {{ $banner->discount_tag }}
                    </span>
                @endif
            </div>

            <h2 class="vm-hero-title" style="font-size: clamp(22px, 3.5vw, 30px); font-weight: 800; color: {{ $textColor }}; line-height: 1.25; margin-bottom: 6px; letter-spacing: -0.3px;">
                {{ $banner->title }}
            </h2>

            @if(!empty($banner->subtitle))
                <p class="vm-hero-subtitle" style="font-size: clamp(12.5px, 2vw, 14px); color: #64748B; margin-bottom: 14px; line-height: 1.45;">
                    {{ $banner->subtitle }}
                </p>
            @endif

            @if(!empty($featureTags))
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                    @foreach($featureTags as $tag)
                        <span class="vm-banner-tag-chip" style="background: #FFFFFF; border: 1px solid #E2E8F0; color: #334155;">
                            ✓ {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            <a href="{{ $banner->cta_url ?? '#catalog' }}" style="display: inline-flex; align-items: center; gap: 6px; background: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 13px; padding: 9px 20px; border-radius: 6px; text-decoration: none; transition: all 0.2s ease;">
                <span>{{ $banner->cta_text ?? 'Explore Catalog' }}</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="vm-hero-pedestal-wrap" style="position: relative; margin-left: 16px; flex-shrink: 0; z-index: 2;">
            <div style="width: 120px; height: 120px; border-radius: 20px; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 28px -10px rgba(0,0,0,0.12); border: 1px solid #E2E8F0;">
                <img src="{{ asset($hasImage ? $banner->image_path : 'assets/img/vm_icon.jpg') }}" alt="{{ $banner->title }}" style="width: 95px; height: 95px; border-radius: 14px; object-fit: cover;">
            </div>
        </div>
    </div>

@elseif($style === 'fresh_market')
    {{-- TEMPLATE 5: VIBRANT HARVEST & LOCAL SUPERMARKET --}}
    <div class="vm-hero-slide-item vm-banner-template-fresh {{ (isset($index) && $index === 0) ? 'active' : '' }}" 
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: flex;' }} background: {{ $bgGradient }}; border: 1.5px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 26px 32px; box-shadow: 0 6px 24px rgba(16, 185, 129, 0.08); position: relative; overflow: hidden; justify-content: space-between; align-items: center; min-height: 220px;"
         data-slide-index="{{ $index ?? 0 }}">
        
        {{-- Organic Leaf Watermark --}}
        <div style="position: absolute; right: -15px; bottom: -20px; font-size: 140px; opacity: 0.06; color: #064E3B; user-select: none; pointer-events: none;">
            🌱
        </div>

        <div style="max-width: 580px; position: relative; z-index: 2;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 3px 10px; border-radius: 999px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46;">
                    <span>🌱</span>
                    <span>{{ $banner->badge_text ?? '100% FARM FRESH GUARANTEE' }}</span>
                </div>

                @if(!empty($banner->discount_tag))
                    <span style="background: #10B981; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px;">
                        {{ $banner->discount_tag }}
                    </span>
                @endif
            </div>

            <h2 class="vm-hero-title" style="font-size: clamp(22px, 3.5vw, 31px); font-weight: 800; color: {{ $textColor }}; line-height: 1.22; margin-bottom: 8px; letter-spacing: -0.4px;">
                {{ $banner->title }}
            </h2>

            @if(!empty($banner->subtitle))
                <p class="vm-hero-subtitle" style="font-size: clamp(12.5px, 2vw, 14px); color: #065F46; opacity: 0.9; margin-bottom: 14px; line-height: 1.45;">
                    {{ $banner->subtitle }}
                </p>
            @endif

            @if(!empty($featureTags))
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                    @foreach($featureTags as $tag)
                        <span class="vm-banner-tag-chip" style="background: #FFFFFF; border: 1px solid #A7F3D0; color: #065F46;">
                            🌾 {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            <a href="{{ $banner->cta_url ?? '#catalog' }}" style="display: inline-flex; align-items: center; gap: 6px; background: #059669; color: #FFFFFF; font-weight: 800; font-size: 13.5px; padding: 10px 22px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3); transition: all 0.2s ease;">
                <span>{{ $banner->cta_text ?? 'Browse Fresh Catalog' }}</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="vm-hero-pedestal-wrap" style="position: relative; margin-left: 16px; flex-shrink: 0; z-index: 2;">
            <div style="width: 125px; height: 125px; border-radius: 50%; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 24px rgba(5, 150, 105, 0.15); border: 3px solid #6EE7B7;">
                <img src="{{ asset($hasImage ? $banner->image_path : 'assets/img/vm_icon.jpg') }}" alt="{{ $banner->title }}" style="width: 95px; height: 95px; border-radius: 50%; object-fit: cover;">
            </div>
        </div>
    </div>

@elseif($style === 'brand_prestige')
    {{-- TEMPLATE 6: IMPERIAL BRAND & PRESTIGE FLAGSHIP --}}
    <div class="vm-hero-slide-item vm-banner-template-prestige {{ (isset($index) && $index === 0) ? 'active' : '' }}" 
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: flex;' }} background: {{ $bgGradient }}; border: 1.5px solid rgba(255, 215, 0, 0.45); border-radius: 14px; padding: 28px 34px; box-shadow: 0 8px 32px rgba(46, 8, 84, 0.25); position: relative; overflow: hidden; justify-content: space-between; align-items: center; min-height: 220px;"
         data-slide-index="{{ $index ?? 0 }}">
        
        {{-- Golden Crown Crest Watermark --}}
        <div style="position: absolute; right: 80px; bottom: -30px; font-size: 180px; opacity: 0.05; color: #FFD700; user-select: none; pointer-events: none;">
            👑
        </div>

        <div style="max-width: 580px; position: relative; z-index: 2;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 12px; border-radius: 999px; background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.45); color: #FDE047;">
                    <span>👑</span>
                    <span>{{ $banner->badge_text ?? 'OFFICIAL FLAGSHIP MARKETPLACE' }}</span>
                </div>

                @if(!empty($banner->discount_tag))
                    <span style="background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-size: 11px; font-weight: 900; padding: 3px 9px; border-radius: 6px; letter-spacing: 0.3px;">
                        {{ $banner->discount_tag }}
                    </span>
                @endif
            </div>

            <h2 class="vm-hero-title" style="font-size: clamp(22px, 3.5vw, 32px); font-weight: 900; color: {{ $textColor }}; line-height: 1.22; margin-bottom: 8px; letter-spacing: -0.5px;">
                {{ $banner->title }}
            </h2>

            @if(!empty($banner->subtitle))
                <p class="vm-hero-subtitle" style="font-size: clamp(12.5px, 2vw, 14px); color: rgba(255, 255, 255, 0.85); margin-bottom: 14px; line-height: 1.45;">
                    {{ $banner->subtitle }}
                </p>
            @endif

            @if(!empty($featureTags))
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                    @foreach($featureTags as $tag)
                        <span class="vm-banner-tag-chip" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 215, 0, 0.3); color: #FEF08A;">
                            ★ {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            <a href="{{ $banner->cta_url ?? '#catalog' }}" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #FFD700 0%, #F59E0B 100%); color: #0F172A; font-weight: 900; font-size: 13.5px; padding: 10px 22px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 16px rgba(255, 215, 0, 0.4); transition: all 0.2s ease;">
                <span>{{ $banner->cta_text ?? 'Shop Verified Items' }}</span>
                <span>&rarr;</span>
            </a>
        </div>

        {{-- Imperial Golden Concentric Ring Pedestal --}}
        <div class="vm-hero-pedestal-wrap" style="position: relative; margin-left: 16px; flex-shrink: 0; z-index: 2;">
            <div style="width: 130px; height: 130px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); border: 3px solid rgba(255, 215, 0, 0.7); display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 28px rgba(0,0,0,0.3); position: relative;">
                <div style="width: 110px; height: 110px; border-radius: 50%; border: 1px dashed rgba(255, 215, 0, 0.4); display: flex; align-items: center; justify-content: center; background: #FFFFFF;">
                    <img src="{{ asset($hasImage ? $banner->image_path : 'assets/img/vm_icon.jpg') }}" alt="{{ $banner->title }}" style="width: 82px; height: 82px; border-radius: 50%; object-fit: cover;">
                </div>
                <div style="position: absolute; bottom: -8px; background: #0F172A; border: 1.5px solid #FFD700; color: #FFD700; font-size: 9px; font-weight: 900; padding: 2px 7px; border-radius: 999px; letter-spacing: 0.4px;">
                    ★ OFFICIAL
                </div>
            </div>
        </div>
    </div>

@else
    {{-- TEMPLATE 1: LUXURY SPLIT SHOWCASE (Default E-Commerce) --}}
    <div class="vm-hero-slide-item vm-banner-template-split {{ (isset($index) && $index === 0) ? 'active' : '' }}" 
         style="{{ (isset($index) && $index !== 0) ? 'display: none;' : 'display: flex;' }} background: {{ $bgGradient }}; border: 1px solid rgba(94, 23, 235, 0.12); border-radius: 14px; padding: 28px 32px; box-shadow: var(--vm-shadow-xs); position: relative; overflow: hidden; justify-content: space-between; align-items: center; min-height: 220px;"
         data-slide-index="{{ $index ?? 0 }}">
        
        <div style="max-width: 580px; position: relative; z-index: 2;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">
                @if(!empty($banner->badge_text))
                    <div class="vm-hero-badge-pill" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 10px; border-radius: 999px; background: #FFFBE6; color: #D97706;">
                        <span class="vm-badge-dot" style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                        <span>{{ $banner->badge_text }}</span>
                    </div>
                @endif

                @if(!empty($banner->discount_tag))
                    <span style="background: #5E17EB; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px;">
                        {{ $banner->discount_tag }}
                    </span>
                @endif
            </div>

            <h2 class="vm-hero-title" style="font-size: clamp(22px, 3.5vw, 32px); font-weight: 800; color: {{ $textColor }}; line-height: 1.22; margin-bottom: 8px; letter-spacing: -0.5px;">
                {{ $banner->title }}
            </h2>

            @if(!empty($banner->subtitle))
                <p class="vm-hero-subtitle" style="font-size: clamp(12.5px, 2vw, 14.5px); color: #64748B; margin-bottom: 14px; line-height: 1.45;">
                    {{ $banner->subtitle }}
                </p>
            @endif

            @if(!empty($featureTags))
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 16px;">
                    @foreach($featureTags as $tag)
                        <span class="vm-banner-tag-chip" style="background: #FFFFFF; border: 1px solid rgba(94, 23, 235, 0.15); color: #475569;">
                            ✦ {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="vm-hero-actions" style="display: flex; gap: 12px; align-items: center;">
                <a href="{{ $banner->cta_url ?? '#catalog' }}" class="vm-hero-cta-btn" style="display: inline-flex; align-items: center; gap: 6px; background: var(--vm-gold); color: #0F172A; font-weight: 800; font-size: 13.5px; padding: 10px 22px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35); transition: all 0.2s ease;">
                    <span>{{ $banner->cta_text ?? 'Shop Verified Items' }}</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Brand Emblem / Product Pedestal -->
        <div class="vm-hero-pedestal-wrap" style="display: flex; align-items: center; justify-content: center; position: relative; margin-left: 16px; flex-shrink: 0; z-index: 2;">
            <div style="width: 120px; height: 120px; border-radius: 24px; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 28px rgba(94, 23, 235, 0.14); border: 2px solid rgba(255, 215, 0, 0.45);">
                <img src="{{ asset($hasImage ? $banner->image_path : 'assets/img/vm_icon.jpg') }}" alt="{{ $banner->title }}" style="width: 95px; height: 95px; border-radius: 18px; object-fit: cover;">
            </div>
        </div>
    </div>
@endif
