@extends('layouts.dashboard')

@section('title', 'Storefront Announcement Bar & Customer Care Settings')
@section('role_title', 'Super Admin')
@section('page_title', 'Announcement Bar & Care')
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
                <span style="font-size: 26px;">📢</span>
                <span>Storefront Announcement Bar &amp; Customer Care Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Configure the live top banner across public storefront and mobile apps, customer care hotlines, and WhatsApp ordering.
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

    <!-- Announcement & Customer Care Settings Card -->
    <div class="dash-card" id="announcement-settings" style="border: 1.5px solid rgba(94, 23, 235, 0.25);">
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(243, 232, 255, 0.3) 100%);">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">📢</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Storefront Announcement &amp; Support Control Center</span>
                        <div class="dash-card-subtitle" style="margin-top: 2px;">
                            Controls the live top banner on the public storefront, customer care phone numbers, and WhatsApp call-to-order hotline.
                        </div>
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Storefront Top Bar Live</span>
        </div>

        <div style="padding: 24px;">
            <!-- Real-Time Top Bar Live Preview -->
            <div style="margin-bottom: 24px;">
                <label style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;">
                    🖥️ Live Storefront Top Bar Preview (Dynamic Visual Simulation):
                </label>
                <div id="vmTopBarPreview" style="background: {{ $announcementSettings['announcement_bg_color'] ?? '#0F172A' }}; color: #E2E8F0; padding: 12px 20px; border-radius: 8px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255, 255, 255, 0.12); box-shadow: var(--adm-shadow-md);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="previewBadge" style="font-size: 15px;">{{ $announcementSettings['announcement_badge'] ?? '💬' }}</span>
                        <span id="previewText" style="font-weight: 600;">{!! $announcementSettings['announcement_text'] ?? 'Call or Whatsapp 09118949035 to order' !!}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: #CBD5E1;">
                        <span>📞 Care: <strong>{{ $announcementSettings['support_phone'] ?? '09118949035' }}</strong></span>
                        <span>💬 WhatsApp: <strong>{{ $announcementSettings['support_whatsapp'] ?? '09118949035' }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Quick Campaign Presets -->
            <div style="margin-bottom: 24px; background: #F8FAFC; border: 1px solid var(--adm-border); border-radius: 8px; padding: 14px 18px;">
                <label style="font-size: 11.5px; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 8px; display: block;">
                    ⚡ One-Click Campaign Presets:
                </label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn-dash btn-dash-outline preset-btn" data-type="phone_order" data-badge="💬" data-text="Call or Whatsapp 09118949035 to order directly from Plaza merchants!" data-bg="#0F172A" style="font-size: 11.5px; padding: 5px 12px; background: #FFF;">
                        📞 Call to Order Preset
                    </button>
                    <button type="button" class="btn-dash btn-dash-outline preset-btn" data-type="delivery_special" data-badge="🚚" data-text="Swift Dispatch: Same-Day Delivery across Uyo, Eket, and Ikot Ekpene LGA Corridors!" data-bg="#5E17EB" style="font-size: 11.5px; padding: 5px 12px; background: #FFF;">
                        🚚 Delivery Corridor Special
                    </button>
                    <button type="button" class="btn-dash btn-dash-outline preset-btn" data-type="cashback_promo" data-badge="🪙" data-text="Victorious Cashback: Get instant 5% cashback on all prepaid marketplace orders!" data-bg="#065F46" style="font-size: 11.5px; padding: 5px 12px; background: #FFF;">
                        🪙 5% Cashback Guarantee
                    </button>
                    <button type="button" class="btn-dash btn-dash-outline preset-btn" data-type="flash_sale" data-badge="⚡" data-text="Freshness Certified: 7-Day Vendor Stock Freshness Guaranteed on all listings!" data-bg="#7C2D12" style="font-size: 11.5px; padding: 5px 12px; background: #FFF;">
                        ⚡ Certified Freshness
                    </button>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" action="{{ route('admin.settings.announcement') }}">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 20px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">CUSTOMER CARE PHONE</label>
                        <input type="text" name="support_phone" id="inputPhone" value="{{ $announcementSettings['support_phone'] }}" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">WHATSAPP ORDERING LINE</label>
                        <input type="text" name="support_whatsapp" id="inputWhatsapp" value="{{ $announcementSettings['support_whatsapp'] }}" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">SUPPORT EMAIL</label>
                        <input type="email" name="support_email" value="{{ $announcementSettings['support_email'] }}" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                    </div>

                    <div>
                        <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">ANNOUNCEMENT CAMPAIGN TYPE</label>
                        <select name="announcement_type" id="selectType" style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; background: #FFF; font-size: 13.5px;">
                            <option value="phone_order" {{ $announcementSettings['announcement_type'] == 'phone_order' ? 'selected' : '' }}>📞 Phone &amp; WhatsApp Direct Order</option>
                            <option value="flash_sale" {{ $announcementSettings['announcement_type'] == 'flash_sale' ? 'selected' : '' }}>⚡ Flash Sale &amp; Promotional Notice</option>
                            <option value="delivery_special" {{ $announcementSettings['announcement_type'] == 'delivery_special' ? 'selected' : '' }}>🚚 Delivery Corridor Notice</option>
                            <option value="cashback_promo" {{ $announcementSettings['announcement_type'] == 'cashback_promo' ? 'selected' : '' }}>🪙 5% Cashback Rewards Notice</option>
                            <option value="custom_notice" {{ $announcementSettings['announcement_type'] == 'custom_notice' ? 'selected' : '' }}>📢 Custom Announcement</option>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1; display: grid; grid-template-columns: 80px 1fr 140px; gap: 14px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">EMOJI</label>
                            <input type="text" name="announcement_badge" id="inputBadge" value="{{ $announcementSettings['announcement_badge'] }}" style="width: 100%; padding: 10px; text-align: center; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 16px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BANNER MESSAGE (LIVE TICKER)</label>
                            <input type="text" name="announcement_text" id="inputText" value="{{ $announcementSettings['announcement_text'] }}" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-size: 13.5px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: var(--adm-text); margin-bottom: 6px; display: block;">BACKGROUND</label>
                            <input type="color" name="announcement_bg_color" id="inputBg" value="{{ $announcementSettings['announcement_bg_color'] }}" style="width: 100%; height: 42px; padding: 2px; border: 1px solid var(--adm-border); border-radius: 8px; cursor: pointer;">
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 16px; border-top: 1px solid var(--adm-border);">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--adm-text); margin: 0;">
                        <input type="checkbox" name="announcement_is_active" value="1" {{ !empty($announcementSettings['announcement_is_active']) ? 'checked' : '' }} style="accent-color: var(--adm-primary); width: 16px; height: 16px;">
                        <span>Show Announcement Bar on Public Storefront</span>
                    </label>

                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 24px; font-size: 13px; font-weight: 700;">
                        ✓ Save &amp; Publish Top Bar Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script')
<script>
    // Live preview interactions for announcement control center
    const inputText = document.getElementById('inputText');
    const inputBadge = document.getElementById('inputBadge');
    const inputBg = document.getElementById('inputBg');
    const previewText = document.getElementById('previewText');
    const previewBadge = document.getElementById('previewBadge');
    const vmTopBarPreview = document.getElementById('vmTopBarPreview');

    if (inputText && previewText) {
        inputText.addEventListener('input', (e) => { previewText.textContent = e.target.value; });
    }
    if (inputBadge && previewBadge) {
        inputBadge.addEventListener('input', (e) => { previewBadge.textContent = e.target.value; });
    }
    if (inputBg && vmTopBarPreview) {
        inputBg.addEventListener('input', (e) => { vmTopBarPreview.style.background = e.target.value; });
    }

    // Preset buttons
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (inputText) inputText.value = btn.dataset.text;
            if (inputBadge) inputBadge.value = btn.dataset.badge;
            if (inputBg) inputBg.value = btn.dataset.bg;
            if (previewText) previewText.textContent = btn.dataset.text;
            if (previewBadge) previewBadge.textContent = btn.dataset.badge;
            if (vmTopBarPreview) vmTopBarPreview.style.background = btn.dataset.bg;
            const selectType = document.getElementById('selectType');
            if (selectType) selectType.value = btn.dataset.type;
        });
    });
</script>
@endpush