@extends('layouts.dashboard')

@section('title', 'Standard Delivery Fee Matrix & 3-Tier Distance Schedule — Super Admin')
@section('role_title', 'Super Admin')
@section('page_title', 'Standard Delivery Fee Matrix')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@push('css')
<style>
    /* Searchable LGA Autocomplete Combobox Engine */
    .vm-lga-field-wrapper {
        position: relative;
        margin-bottom: 12px;
    }
    .vm-lga-input-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .vm-lga-icon-prefix {
        position: absolute;
        left: 10px;
        font-size: 13px;
        color: #94A3B8;
        pointer-events: none;
        z-index: 2;
    }
    .vm-lga-search-box {
        width: 100%;
        padding: 9px 34px 9px 32px;
        border-radius: 7px;
        border: 1.5px solid var(--adm-border);
        background: #FFFFFF;
        font-size: 13px;
        font-weight: 600;
        color: var(--adm-text);
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }
    .vm-lga-search-box:focus {
        border-color: var(--adm-primary);
        box-shadow: 0 0 0 3px rgba(94, 23, 235, 0.12);
        background: #FFFFFF;
    }
    .vm-lga-clear-btn {
        position: absolute;
        right: 8px;
        background: #F1F5F9;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #64748B;
        cursor: pointer;
        transition: all 0.15s ease;
        z-index: 2;
    }
    .vm-lga-clear-btn:hover {
        background: #E2E8F0;
        color: #0F172A;
    }
    .vm-lga-floating-menu {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #FFFFFF;
        border: 1.5px solid #CBD5E1;
        border-radius: 8px;
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18), 0 4px 6px -2px rgba(15, 23, 42, 0.05);
        max-height: 230px;
        overflow-y: auto;
        z-index: 1050;
    }
    .vm-lga-option-row {
        padding: 8px 12px;
        border-bottom: 1px solid #F1F5F9;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: background 0.12s ease;
    }
    .vm-lga-option-row:hover, .vm-lga-option-row.active-row {
        background: #F5F3FF;
    }
    .vm-lga-option-row:last-child {
        border-bottom: none;
    }
    .vm-lga-option-name {
        font-weight: 700;
        color: #0F172A;
        font-size: 12.5px;
    }
    .vm-lga-option-state {
        font-size: 11px;
        color: #64748B;
        margin-left: 5px;
    }
    .vm-lga-badge-zone {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        white-space: nowrap;
    }
    .vm-quick-hub-btn {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 4px;
        padding: 2px 7px;
        font-size: 10.5px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .vm-quick-hub-btn:hover {
        background: rgba(94, 23, 235, 0.08);
        border-color: var(--adm-primary);
        color: var(--adm-primary);
    }
    .vm-swap-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #FFFFFF;
        border: 1px solid var(--adm-border);
        border-radius: 20px;
        padding: 4px 14px;
        font-size: 11px;
        font-weight: 700;
        color: var(--adm-primary);
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: all 0.2s;
    }
    .vm-swap-btn:hover {
        background: rgba(94, 23, 235, 0.08);
        border-color: var(--adm-primary);
        transform: translateY(-1px);
    }
</style>
@endpush

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🛣️</span>
                <span>Standard Delivery Fee Matrix &mdash; Directional LGA Delivery Lanes &amp; 3-Tier Distance Matrix</span>
            </h1>
            <p class="page-header-subtitle">
                Authoritative logistics pricing: Super Admin sets standard corridor fees and 3-tier zonal distance baselines (Municipal, Regional, Inter-State). Merchants individually configure physical shop delivery coverage (LGAs/states). Uncovered destinations fail closed to in-shop pickup.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                ⚡ Standard Rates &amp; Coverage Governed
            </span>
        </div>
    </div>

    <!-- 4 Zonal Stat Badges -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Active LGA Corridors</span>
                <span class="stat-card-value">{{ count($deliveryLanes) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Custom Overrides in Database</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🛣️</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Tier 1: Municipal (Intra-LGA)</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($zonalSettings['zone_intra_lga_fee'] ?? 500), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">{{ $zonalSettings['zone_intra_lga_eta'] ?? '2-4 hours' }}</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🏙️</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Tier 2: Regional (Inter-LGA)</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($zonalSettings['zone_inter_lga_fee'] ?? 1500), 2) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">{{ $zonalSettings['zone_inter_lga_eta'] ?? 'Same day / 12-24h' }}</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🚚</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Tier 3: Inter-State Freight</span>
                <span class="stat-card-value">&#8358;{{ number_format((float)($zonalSettings['zone_inter_state_fee'] ?? 3500), 2) }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">{{ $zonalSettings['zone_inter_state_eta'] ?? '2-4 business days' }}</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">🗺️</div>
        </div>
    </div>

    <!-- MASTER SECTION: 3-Tier Zonal Distance Routing & Directional Delivery Lanes Matrix -->
    <div class="dash-card" id="delivery-lanes" style="border: 1.5px solid rgba(94, 23, 235, 0.25);">
        <!-- Header with Sub-Tabs -->
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(243, 232, 255, 0.3) 100%);">
            <div>
                <div class="dash-card-title">
                    <span style="font-size: 18px;">🛣️</span>
                    <div>
                        <span style="font-weight: 800; color: var(--adm-text);">Standard Delivery Corridors &amp; Zonal Matrix</span>
                        <div class="dash-card-subtitle" style="margin-top: 2px;">
                            Authoritative standard delivery fee schedule with bidirectional synchronization and automatic 3-tier zonal distance baseline fallback for vendor-covered areas.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Switcher Pills -->
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn-dash btn-dash-primary" id="btnTabCorridors" onclick="switchLaneTab('corridors')" style="padding: 6px 14px; font-size: 12px; font-weight: 700;">
                    <span>📍 Specific Corridors</span>
                    <span style="background: rgba(255,255,255,0.25); padding: 1px 6px; border-radius: 10px; margin-left: 4px;">{{ count($deliveryLanes) }}</span>
                </button>
                <button type="button" class="btn-dash btn-dash-outline" id="btnTabZonal" onclick="switchLaneTab('zonal')" style="padding: 6px 14px; font-size: 12px; font-weight: 700; background: #FFF;">
                    <span>📐 3-Tier Distance Baseline</span>
                </button>
            </div>
        </div>

        <div style="padding: 20px;">
            <!-- ============================================================ -->
            <!-- TAB 1: 3-TIER ZONAL BASELINE MATRIX (Guarantees 100% Coverage) -->
            <!-- ============================================================ -->
            <div id="vmZonalRatesTab" style="display: none;">
                <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">🛡️</span>
                        <div>
                            <strong style="color: #166534; font-size: 13.5px;">Standard 3-Tier Distance Baseline Rates Active</strong>
                            <div style="font-size: 12px; color: #15803D;">When a vendor covers a destination LGA and no custom corridor fee exists, checkout automatically calculates delivery with these 3 zonal tiers. If a destination is outside the vendor's physical shop coverage, doorstep delivery is blocked and checkout fails closed to In-Shop Pickup.</div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.delivery_lanes.zonal') }}">
                    @csrf
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <!-- Tier 1: Intra-LGA (Municipal) -->
                        <div style="background: #FFF; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Tier 1: Municipal (Intra-LGA)</strong>
                                <span class="dash-badge dash-badge-success" style="font-size: 10px;">Same LGA</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">E.g. Uyo plaza shop to an address within Uyo municipality.</div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">BASE FEE (₦)</label>
                                <input type="number" step="0.01" name="zone_intra_lga_fee" value="{{ $zonalSettings['zone_intra_lga_fee'] ?? '500.00' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">ESTIMATED TIME (ETA)</label>
                                <input type="text" name="zone_intra_lga_eta" value="{{ $zonalSettings['zone_intra_lga_eta'] ?? '2-4 hours' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px;">
                            </div>
                        </div>

                        <!-- Tier 2: Inter-LGA (Regional Collection Points) -->
                        <div style="background: #FFF; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Tier 2: Inter-LGA (Collection Points)</strong>
                                <span class="dash-badge dash-badge-info" style="font-size: 10px;">Same State</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">E.g. Uyo merchant shipping to Eket, Ikot Ekpene, or Oron. Customers pick up at company Collection Point.</div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">BASE FEE (₦)</label>
                                <input type="number" step="0.01" name="zone_inter_lga_fee" value="{{ $zonalSettings['zone_inter_lga_fee'] ?? '1500.00' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">ESTIMATED TIME (ETA)</label>
                                <input type="text" name="zone_inter_lga_eta" value="{{ $zonalSettings['zone_inter_lga_eta'] ?? 'Same day / 12-24 hours' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px;">
                            </div>
                        </div>

                        <!-- Tier 3: Inter-State (National Collection Points) -->
                        <div style="background: #FFF; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Tier 3: Inter-State (Collection Points)</strong>
                                <span class="dash-badge dash-badge-purple" style="font-size: 10px;">Nationwide</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">E.g. Akwa Ibom to Lagos, Abuja, Rivers, or Abia. Customers pick up at company Collection Point.</div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">BASE FEE (₦)</label>
                                <input type="number" step="0.01" name="zone_inter_state_fee" value="{{ $zonalSettings['zone_inter_state_fee'] ?? '3500.00' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">ESTIMATED TIME (ETA)</label>
                                <input type="text" name="zone_inter_state_eta" value="{{ $zonalSettings['zone_inter_state_eta'] ?? '2-4 business days' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px;">
                            </div>
                        </div>

                        <!-- Bulky Cargo Surcharge Addon -->
                        <div style="background: #FFF; border: 1.5px solid #F59E0B; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Bulky Cargo Surcharge</strong>
                                <span class="dash-badge dash-badge-gold" style="font-size: 10px;">Weight &gt; 15kg</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">Added on top of base fee when products exceed standard rider bike capacity.</div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">DEFAULT BULKY ADDON (₦)</label>
                                <input type="number" step="0.01" name="zone_bulky_cargo_surcharge" value="{{ $zonalSettings['zone_bulky_cargo_surcharge'] ?? '1000.00' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                            </div>
                            <div style="font-size: 11.5px; color: #64748B; margin-top: 14px;">
                                Applies dynamically per parcel based on item physical dimensions.
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 10px 24px; font-size: 13px; font-weight: 700;">
                            ✓ Save 3-Tier Zonal Distance Baseline Rates
                        </button>
                    </div>
                </form>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: HUB-AND-SPOKE DIRECTIONAL CORRIDORS COMMAND CONSOLE   -->
            <!-- ============================================================ -->
            <div id="vmCorridorsTab">
                @include('admin.partials._hub_corridors')
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    // -------------------------------------------------------------
    // Tab Switcher Engine
    // -------------------------------------------------------------
    function switchLaneTab(tab) {
        const btnCorridors = document.getElementById('btnTabCorridors');
        const btnZonal = document.getElementById('btnTabZonal');
        const viewCorridors = document.getElementById('vmCorridorsTab');
        const viewZonal = document.getElementById('vmZonalRatesTab');

        if (tab === 'corridors') {
            btnCorridors.className = 'btn-dash btn-dash-primary';
            btnZonal.className = 'btn-dash btn-dash-outline';
            btnZonal.style.background = '#FFF';
            viewCorridors.style.display = 'block';
            viewZonal.style.display = 'none';
        } else {
            btnZonal.className = 'btn-dash btn-dash-primary';
            btnZonal.style.background = '';
            btnCorridors.className = 'btn-dash btn-dash-outline';
            btnCorridors.style.background = '#FFF';
            viewCorridors.style.display = 'none';
            viewZonal.style.display = 'block';
        }
    }

    // -------------------------------------------------------------
    // Tier 2: Inter-LGA Table Filtering & Search
    // -------------------------------------------------------------
    let currentInterFilter = 'all';

    function setInterLgaFilter(filter) {
        currentInterFilter = filter;
        document.querySelectorAll('.btn-inter-filter').forEach(btn => {
            if (btn.getAttribute('data-filter') === filter) {
                btn.className = 'btn-dash btn-dash-primary btn-inter-filter';
                btn.style.background = 'var(--adm-primary)';
                btn.style.color = '#FFF';
                btn.style.border = 'none';
            } else {
                btn.className = 'btn-dash btn-dash-outline btn-inter-filter';
                btn.style.background = '#FFF';
                btn.style.color = 'var(--adm-text)';
                btn.style.border = '1px solid var(--adm-border)';
            }
        });
        filterInterLgaRows();
    }

    function filterInterLgaRows() {
        const query = (document.getElementById('interLgaSearchInput')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#vmInterLgaTable .vm-inter-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const status = row.getAttribute('data-status') || '';

            const matchesQuery = !query || searchData.includes(query);
            const matchesFilter = (currentInterFilter === 'all') || (status === currentInterFilter);

            if (matchesQuery && matchesFilter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const badge = document.getElementById('interLgaCountBadge');
        if (badge) {
            badge.textContent = `${visibleCount} LGA${visibleCount === 1 ? '' : 's'} Visible`;
        }
    }

    // -------------------------------------------------------------
    // Tier 3: Out-of-State Table Filtering & Search
    // -------------------------------------------------------------
    let currentOutFilter = 'all';

    function setOutOfStateFilter(filter) {
        currentOutFilter = filter;
        document.querySelectorAll('.btn-state-filter').forEach(btn => {
            if (btn.getAttribute('data-filter') === filter) {
                btn.className = 'btn-dash btn-dash-primary btn-state-filter';
                btn.style.background = 'var(--adm-primary)';
                btn.style.color = '#FFF';
                btn.style.border = 'none';
            } else {
                btn.className = 'btn-dash btn-dash-outline btn-state-filter';
                btn.style.background = '#FFF';
                btn.style.color = 'var(--adm-text)';
                btn.style.border = '1px solid var(--adm-border)';
            }
        });
        filterOutOfStateRows();
    }

    function filterOutOfStateRows() {
        const query = (document.getElementById('outOfStateSearchInput')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#vmOutOfStateTable .vm-state-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const status = row.getAttribute('data-status') || '';

            const matchesQuery = !query || searchData.includes(query);
            const matchesFilter = (currentOutFilter === 'all') || (status === currentOutFilter);

            if (matchesQuery && matchesFilter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const badge = document.getElementById('outOfStateCountBadge');
        if (badge) {
            badge.textContent = `${visibleCount} State${visibleCount === 1 ? '' : 's'} Visible`;
        }
    }

    // -------------------------------------------------------------
    // Corridor Rate Configuration Modal Engine
    // -------------------------------------------------------------
    function openCorridorModal(data) {
        document.getElementById('modalLaneId').value = data.id || '';
        document.getElementById('modalOriginLgaId').value = data.origin_id;
        document.getElementById('modalDestLgaId').value = data.dest_id;
        document.getElementById('modalOriginDisplay').textContent = data.origin_name;
        document.getElementById('modalDestDisplay').textContent = data.dest_name;
        document.getElementById('modalTierBadge').textContent = data.tier_label || 'Regional Corridor';

        document.getElementById('modalBaseFee').value = parseFloat(data.base_fee || 0).toFixed(2);
        document.getElementById('modalBulkyFee').value = parseFloat(data.bulky || 0).toFixed(2);
        document.getElementById('modalMinEta').value = data.min_eta || 12;
        document.getElementById('modalMaxEta').value = data.max_eta || 24;

        const bidiCheckbox = document.getElementById('modalIsBidi');
        if (bidiCheckbox) {
            bidiCheckbox.checked = !!data.is_bidi;
        }

        const overlay = document.getElementById('vmCorridorModalOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function closeCorridorModal() {
        const overlay = document.getElementById('vmCorridorModalOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    // -------------------------------------------------------------
    // Hub Switcher Autocomplete Engine (0ms Client-Side Instant Search)
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        let allLgas = [];
        try {
            const dataElem = document.getElementById('vmAllLgasData');
            if (dataElem) {
                allLgas = JSON.parse(dataElem.textContent);
            }
        } catch (err) {
            console.error('Failed to parse canonical LGA dataset', err);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        function escapeRegExp(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        const hubInput = document.getElementById('vmHubSwitcherInput');
        const hubDropdown = document.getElementById('vmHubDropdown');
        const hubClear = document.getElementById('vmHubClearBtn');

        if (hubInput && hubDropdown) {
            function filterHubDataset(query) {
                const q = (query || '').trim().toLowerCase();
                if (!q) return allLgas.slice(0, 30);
                return allLgas.filter(l => 
                    l.name.toLowerCase().includes(q) || 
                    (l.state && l.state.toLowerCase().includes(q))
                ).slice(0, 30);
            }

            function renderHubDropdown(items, query = '') {
                if (!items.length) {
                    hubDropdown.innerHTML = `
                        <div style="padding: 12px; text-align: center; color: #94A3B8; font-size: 12px;">
                            No LGA found matching &ldquo;<strong>${escapeHtml(query)}</strong>&rdquo;
                        </div>
                    `;
                    hubDropdown.style.display = 'block';
                    return;
                }

                let html = '';
                items.forEach((lga, idx) => {
                    let highlightedName = escapeHtml(lga.name);
                    if (query) {
                        const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
                        highlightedName = highlightedName.replace(regex, '<mark style="background: rgba(94, 23, 235, 0.16); color: #5E17EB; padding: 0 2px; border-radius: 2px;">$1</mark>');
                    }

                    const targetUrl = `{{ route('admin.delivery_lanes.index') }}?hub_lga_id=${lga.id}`;
                    html += `
                        <a href="${targetUrl}" class="vm-lga-option-row" style="text-decoration: none; color: inherit; display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: baseline;">
                                <span class="vm-lga-option-name">${highlightedName}</span>
                                <span class="vm-lga-option-state">&mdash; ${escapeHtml(lga.state)}</span>
                            </div>
                            <span class="vm-lga-badge-zone" style="${lga.is_akwa ? 'background: #ECFDF5; color: #059669;' : 'background: #F1F5F9; color: #64748B;'}">
                                ${lga.is_akwa ? '⚡ Akwa Ibom' : escapeHtml(lga.state)}
                            </span>
                        </a>
                    `;
                });

                hubDropdown.innerHTML = html;
                hubDropdown.style.display = 'block';
            }

            hubInput.addEventListener('input', function () {
                renderHubDropdown(filterHubDataset(this.value), this.value);
            });

            hubInput.addEventListener('focus', function () {
                renderHubDropdown(filterHubDataset(this.value), this.value);
            });

            if (hubClear) {
                hubClear.addEventListener('click', function (e) {
                    e.stopPropagation();
                    hubInput.value = '';
                    hubInput.focus();
                    renderHubDropdown(filterHubDataset(''), '');
                });
            }

            document.addEventListener('click', function (e) {
                const wrapper = document.getElementById('hubSwitcherWrapper');
                if (wrapper && !wrapper.contains(e.target)) {
                    hubDropdown.style.display = 'none';
                }

                const modalOverlay = document.getElementById('vmCorridorModalOverlay');
                if (modalOverlay && e.target === modalOverlay) {
                    closeCorridorModal();
                }
            });
        }
    });
</script>
@endpush