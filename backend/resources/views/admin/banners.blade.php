@extends('layouts.dashboard')

@section('title', 'Storefront Promotional Banners & Hero Slider Desk')
@section('role_title', 'Super Admin')
@section('page_title', 'Banners & Hero Slider')
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
                <span>Storefront Promotional Banners &amp; Hero Slider Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Configure top visual hero cards, promotional middle strips, and campaign countdowns across public web and mobile storefronts.
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
            <div style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">Cache Invalidation Shield</div>
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
                        <div class="dash-card-subtitle">Visual preview reflecting real-time edits before saving</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Interactive Simulation</span>
        </div>

        <div style="padding: 24px;">
            <div id="bannerSimulationCard" style="background: linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%); border: 1px solid rgba(94, 23, 235, 0.15); border-radius: 14px; padding: 32px 36px; box-shadow: 0 4px 20px rgba(94, 23, 235, 0.08); display: flex; justify-content: space-between; align-items: center; min-height: 180px; position: relative; overflow: hidden;">
                <div style="max-width: 580px; z-index: 2;">
                    <div id="simBadge" style="display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 3px 10px; border-radius: 999px; background: #FFFBE6; color: #D97706; margin-bottom: 12px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                        <span id="simBadgeText">VERIFIED OMNICHANNEL MARKET</span>
                    </div>

                    <h2 id="simTitle" style="font-size: 28px; font-weight: 800; color: #0F172A; line-height: 1.25; margin-bottom: 8px;">
                        Seamless Shopping, Swift Logistics
                    </h2>

                    <p id="simSubtitle" style="font-size: 14px; color: #64748B; margin-bottom: 18px; line-height: 1.5;">
                        Akwa Ibom's Premier Platform • Physical In-Shop Inspection • Escrow Protected
                    </p>

                    <a id="simCtaBtn" href="#catalog" style="display: inline-flex; align-items: center; gap: 6px; background: #FFD700; color: #0F172A; font-weight: 800; font-size: 13.5px; padding: 10px 20px; border-radius: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(255, 215, 0, 0.35);">
                        <span id="simCtaText">Shop Verified Items</span>
                        <span>→</span>
                    </a>
                </div>

                <div style="width: 120px; height: 120px; border-radius: 24px; background: #FFFFFF; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(94, 23, 235, 0.15); border: 2px solid rgba(255, 215, 0, 0.45); flex-shrink: 0; margin-left: 20px;">
                    <img id="simImage" src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 100px; height: 100px; border-radius: 18px; object-fit: cover;">
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
                        <div class="dash-card-subtitle">Creates a dynamic hero slider or promo strip with instant cache refresh</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-gold">Banner Publisher</span>
        </div>

        <div style="padding: 24px;">
            <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data">
                @csrf
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
                        <input type="text" name="badge_text" id="inputBadge" value="VERIFIED OMNICHANNEL MARKET" placeholder="e.g. LIMITED TIME OFFER" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
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
                            <option value="linear-gradient(135deg, #EFF6FF 0%, #F5F3FF 100%)">Corridor Transit Sky Blue & Indigo</option>
                            <option value="linear-gradient(135deg, #ECFDF5 0%, #FFFFFF 100%)">Fresh Produce Emerald & Mint</option>
                            <option value="linear-gradient(135deg, #1E1B4B 0%, #311042 100%)">Midnight Sovereign Purple (Dark)</option>
                            <option value="linear-gradient(135deg, #FEF3C7 0%, #FFFBEB 100%)">Gold CashBack Reward Amber</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">CUSTOM IMAGE UPLOAD (OPTIONAL)</label>
                        <input type="file" name="image" id="inputImage" accept="image/*" style="width: 100%; padding: 8px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 12px; background: #F8FAFC;">
                    </div>

                    <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" checked style="width: 18px; height: 18px; accent-color: #5E17EB;">
                            <span style="font-size: 13px; font-weight: 700; color: #0F172A;">Set Active on Storefront Immediately</span>
                        </label>
                        <div>
                            <span style="font-size: 12px; color: #64748B;">Sort Order:</span>
                            <input type="number" name="sort_order" value="1" min="0" style="width: 65px; padding: 6px 10px; border: 1px solid var(--adm-border); border-radius: 6px; font-size: 13px;">
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 16px; border-top: 1px solid var(--adm-border);">
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 11px 24px; font-size: 14px; font-weight: 700;">
                        Publish Banner to Storefront 🚀
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Banners Catalog Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">📋</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Active Storefront Banners Roster</span>
                        <div class="dash-card-subtitle">Manage, reorder, pause, or remove live promotional slides</div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-blue">{{ $banners->count() }} Total Banners</span>
        </div>

        <div class="dash-table-container">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Slot</th>
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
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 44px; height: 44px; border-radius: 8px; background: {{ $banner->bg_gradient ?? '#FFF' }}; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0,0,0,0.08); flex-shrink: 0;">
                                        <img src="{{ asset($banner->image_path ?: 'assets/img/vm_icon.jpg') }}" alt="" style="width: 32px; height: 32px; border-radius: 6px; object-fit: cover;">
                                    </div>
                                    <div>
                                        <strong style="color: #0F172A; font-size: 13.5px; display: block;">{{ $banner->title }}</strong>
                                        <span style="font-size: 11px; color: #64748B;">ID #{{ $banner->id }} • Created {{ $banner->created_at ? $banner->created_at->diffForHumans() : 'Recently' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 10.5px; font-weight: 700; color: #D97706; background: #FFFBE6; padding: 2px 7px; border-radius: 4px; display: inline-block; margin-bottom: 3px;">
                                    {{ $banner->badge_text }}
                                </span>
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
                            <td colspan="7" style="text-align: center; padding: 40px; color: #64748B;">
                                No promotional banners found. Use the publisher form above to launch your first banner!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Simulation Interactive JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputTitle = document.getElementById('inputTitle');
            const inputSubtitle = document.getElementById('inputSubtitle');
            const inputBadge = document.getElementById('inputBadge');
            const inputCtaText = document.getElementById('inputCtaText');
            const inputBgGradient = document.getElementById('inputBgGradient');
            const inputImage = document.getElementById('inputImage');

            const simTitle = document.getElementById('simTitle');
            const simSubtitle = document.getElementById('simSubtitle');
            const simBadgeText = document.getElementById('simBadgeText');
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
            if (inputCtaText && simCtaText) {
                inputCtaText.addEventListener('input', () => simCtaText.textContent = inputCtaText.value || 'Shop Verified Items');
            }
            if (inputBgGradient && bannerSimulationCard) {
                inputBgGradient.addEventListener('change', () => bannerSimulationCard.style.background = inputBgGradient.value);
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