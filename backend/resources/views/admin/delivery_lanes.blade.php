@extends('layouts.dashboard')

@section('title', 'Directional LGA Delivery Lanes & 3-Tier Distance Matrix')
@section('role_title', 'Super Admin')
@section('page_title', 'Directional LGA Delivery Lanes')
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
                <span>Directional LGA Delivery Lanes &amp; 3-Tier Distance Matrix</span>
            </h1>
            <p class="page-header-subtitle">
                Authoritative logistics topology: Configure Origin ⇄ Destination LGA Corridors, 3-Tier Distance Baselines, and Bulky Cargo Surcharges.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                ⚡ Fail-Safe Coverage Guaranteed
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
                        <span style="font-weight: 800; color: var(--adm-text);">Directional Delivery Corridors &amp; Zonal Matrix</span>
                        <div class="dash-card-subtitle" style="margin-top: 2px;">
                            Authoritative directional logistics topology with bidirectional synchronization and automatic 3-tier zonal distance fallback.
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
                            <strong style="color: #166534; font-size: 13.5px;">100% Fail-Safe Nationwide Coverage Engine Active</strong>
                            <div style="font-size: 12px; color: #15803D;">If an Origin ⇄ Destination LGA corridor has no custom fee entered, the checkout automatically falls back to these 3 zonal tiers.</div>
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

                        <!-- Tier 2: Inter-LGA (Regional) -->
                        <div style="background: #FFF; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Tier 2: Regional (Inter-LGA)</strong>
                                <span class="dash-badge dash-badge-info" style="font-size: 10px;">Same State</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">E.g. Uyo merchant shipping to Eket, Ikot Ekpene, or Oron.</div>
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">BASE FEE (₦)</label>
                                <input type="number" step="0.01" name="zone_inter_lga_fee" value="{{ $zonalSettings['zone_inter_lga_fee'] ?? '1500.00' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                            </div>
                            <div>
                                <label style="font-size: 11px; font-weight: 700; color: #64748B;">ESTIMATED TIME (ETA)</label>
                                <input type="text" name="zone_inter_lga_eta" value="{{ $zonalSettings['zone_inter_lga_eta'] ?? 'Same day / 12-24 hours' }}" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px;">
                            </div>
                        </div>

                        <!-- Tier 3: Inter-State (National Freight) -->
                        <div style="background: #FFF; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">Tier 3: Inter-State Freight</strong>
                                <span class="dash-badge dash-badge-purple" style="font-size: 10px;">Nationwide</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-bottom: 12px;">E.g. Akwa Ibom to Lagos, Abuja, Rivers, or Cross River.</div>
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
            <!-- TAB 2: TWO-COLUMN MASTER-DETAIL CUSTOM OVERRIDE CORRIDORS    -->
            <!-- ============================================================ -->
            <div id="vmCorridorsTab">
                @php
                    $defaultOrigin = $allLgas->firstWhere('name', 'Uyo') ?? ($allLgas->first() ?? null);
                    $defaultDest = $allLgas->firstWhere('name', 'Eket') ?? ($allLgas->count() > 1 ? $allLgas->values()->get(1) : $allLgas->first());
                    $defaultOriginId = $defaultOrigin->id ?? 69;
                    $defaultOriginName = ($defaultOrigin->name ?? 'Uyo') . ' — ' . ($defaultOrigin->state->name ?? 'Akwa Ibom');
                    $defaultDestId = $defaultDest->id ?? 68;
                    $defaultDestName = ($defaultDest->name ?? 'Eket') . ' — ' . ($defaultDest->state->name ?? 'Akwa Ibom');

                    $commonHubs = ['Uyo', 'Eket', 'Ikot Ekpene', 'Oron', 'Abak', 'Ibesikpo Asutan'];
                    $quickOriginLgas = $allLgas->filter(fn($l) => in_array($l->name, $commonHubs))->values();
                    $quickDestLgas = $allLgas->filter(fn($l) => in_array($l->name, ['Eket', 'Ikot Ekpene', 'Oron', 'Abak', 'Uyo', 'Ikono']))->values();
                @endphp

                <!-- Embedded Canonical LGAs JSON Data for Instantaneous 0ms Client-Side Autocomplete -->
                <script id="vmAllLgasData" type="application/json">
                    {!! json_encode($allLgas->map(function($l) {
                        return [
                            'id' => $l->id,
                            'name' => $l->name,
                            'state' => $l->state->name ?? 'Akwa Ibom',
                            'is_akwa' => strtolower($l->state->name ?? '') === 'akwa ibom',
                        ];
                    })) !!}
                </script>

                <div class="vm-corridors-grid">
                    <!-- LEFT COLUMN: ADD CUSTOM OVERRIDE CORRIDOR FORM -->
                    <div style="background: #F8FAFC; border: 1px solid var(--adm-border); border-radius: 10px; padding: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                            <strong style="font-size: 13.5px; color: var(--adm-text); display: flex; align-items: center; gap: 6px;">
                                <span>➕</span> Add Custom Corridor
                            </strong>
                            <span class="dash-badge dash-badge-purple" style="font-size: 10px;">LGA ⇄ LGA</span>
                        </div>

                        <form method="POST" action="{{ route('admin.delivery_lanes.store') }}" id="vmAddCorridorForm">
                            @csrf
                            
                            <!-- 1. ORIGIN LGA SEARCHABLE FIELD -->
                            <div class="vm-lga-field-wrapper" id="originLgaWrapper">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin: 0; display: flex; align-items: center; gap: 4px;">
                                        <span>📍</span> ORIGIN LGA (DISPATCH FROM)
                                    </label>
                                    <span id="originSelectedIndicator" style="font-size: 10.5px; font-weight: 700; color: var(--adm-primary); background: rgba(94, 23, 235, 0.08); padding: 2px 7px; border-radius: 4px;">
                                        Selected: {{ $defaultOrigin->name ?? 'Uyo' }}
                                    </span>
                                </div>

                                <!-- Server-Bound Hidden Input -->
                                <input type="hidden" name="origin_lga_id" id="laneOriginSelect" value="{{ $defaultOriginId }}" required>

                                <!-- Interactive Autocomplete Input Box -->
                                <div class="vm-lga-input-container">
                                    <span class="vm-lga-icon-prefix">🔍</span>
                                    <input 
                                        type="text" 
                                        id="laneOriginSearchInput" 
                                        class="vm-lga-search-box" 
                                        placeholder="Type LGA or State (e.g. Uyo, Eket, Abak)..."
                                        value="{{ $defaultOriginName }}"
                                        autocomplete="off"
                                        required
                                    >
                                    <button type="button" class="vm-lga-clear-btn" id="laneOriginClearBtn" title="Clear and search LGA">✕</button>
                                </div>

                                <!-- Fast Hub Shortcuts -->
                                <div style="display: flex; gap: 4px; margin-top: 5px; flex-wrap: wrap; align-items: center;">
                                    <span style="font-size: 10px; color: #94A3B8; font-weight: 600;">Hubs:</span>
                                    @foreach($quickOriginLgas as $hub)
                                        <button 
                                            type="button" 
                                            class="vm-quick-hub-btn vm-quick-origin-hub" 
                                            data-id="{{ $hub->id }}" 
                                            data-name="{{ $hub->name }}" 
                                            data-state="{{ $hub->state->name ?? 'Akwa Ibom' }}"
                                        >
                                            {{ $hub->name }}
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Floating Filter Results Dropdown -->
                                <div class="vm-lga-floating-menu" id="laneOriginDropdown"></div>
                            </div>

                            <!-- 2. SWAP ORIGIN ⇄ DESTINATION BUTTON -->
                            <div style="display: flex; justify-content: center; margin: 2px 0 8px;">
                                <button type="button" class="vm-swap-btn" id="btnSwapOriginDest" title="Swap Origin and Destination LGAs">
                                    <span>⇅</span>
                                    <span>Swap Origin ⇄ Destination</span>
                                </button>
                            </div>

                            <!-- 3. DESTINATION LGA SEARCHABLE FIELD -->
                            <div class="vm-lga-field-wrapper" id="destLgaWrapper">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin: 0; display: flex; align-items: center; gap: 4px;">
                                        <span>🎯</span> DESTINATION LGA (DELIVER TO)
                                    </label>
                                    <span id="destSelectedIndicator" style="font-size: 10.5px; font-weight: 700; color: #0284C7; background: rgba(2, 132, 199, 0.08); padding: 2px 7px; border-radius: 4px;">
                                        Selected: {{ $defaultDest->name ?? 'Eket' }}
                                    </span>
                                </div>

                                <!-- Server-Bound Hidden Input -->
                                <input type="hidden" name="destination_lga_id" id="laneDestSelect" value="{{ $defaultDestId }}" required>

                                <!-- Interactive Autocomplete Input Box -->
                                <div class="vm-lga-input-container">
                                    <span class="vm-lga-icon-prefix">🔍</span>
                                    <input 
                                        type="text" 
                                        id="laneDestSearchInput" 
                                        class="vm-lga-search-box" 
                                        placeholder="Type LGA or State (e.g. Eket, Ikot Ekpene, Oron)..."
                                        value="{{ $defaultDestName }}"
                                        autocomplete="off"
                                        required
                                    >
                                    <button type="button" class="vm-lga-clear-btn" id="laneDestClearBtn" title="Clear and search LGA">✕</button>
                                </div>

                                <!-- Fast Hub Shortcuts -->
                                <div style="display: flex; gap: 4px; margin-top: 5px; flex-wrap: wrap; align-items: center;">
                                    <span style="font-size: 10px; color: #94A3B8; font-weight: 600;">Hubs:</span>
                                    @foreach($quickDestLgas as $hub)
                                        <button 
                                            type="button" 
                                            class="vm-quick-hub-btn vm-quick-dest-hub" 
                                            data-id="{{ $hub->id }}" 
                                            data-name="{{ $hub->name }}" 
                                            data-state="{{ $hub->state->name ?? 'Akwa Ibom' }}"
                                        >
                                            {{ $hub->name }}
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Floating Filter Results Dropdown -->
                                <div class="vm-lga-floating-menu" id="laneDestDropdown"></div>
                            </div>

                            <!-- 4. LIVE ROUTE TOPOLOGY INTELLIGENCE PREVIEW -->
                            <div id="vmCorridorLiveTopology" style="background: #FFFFFF; border: 1px solid var(--adm-border); border-left: 4px solid var(--adm-primary); border-radius: 7px; padding: 10px 12px; margin-bottom: 14px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700;">
                                        <span id="previewOriginLabel" style="color: var(--adm-text);">{{ $defaultOrigin->name ?? 'Uyo' }}</span>
                                        <span style="color: var(--adm-primary); font-weight: 900;">➔</span>
                                        <span id="previewDestLabel" style="color: var(--adm-text);">{{ $defaultDest->name ?? 'Eket' }}</span>
                                    </div>
                                    <span id="previewTierBadge" class="dash-badge dash-badge-info" style="font-size: 10px;">
                                        Tier 2: Regional (Inter-LGA)
                                    </span>
                                </div>
                                <div id="previewClassificationText" style="font-size: 11px; color: #64748B; margin-top: 3px;">
                                    Intra-state corridor within Akwa Ibom logistics grid.
                                </div>
                            </div>

                            <!-- Terms -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                                <div>
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">BASE FEE (₦)</label>
                                    <input type="number" step="0.01" name="base_fee" value="1200.00" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px; font-weight: 700;">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">BULKY ADDON (₦)</label>
                                    <input type="number" step="0.01" name="bulky_surcharge" value="1000.00" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                                <div>
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">MIN ETA (HRS)</label>
                                    <input type="number" name="estimated_delivery_hours_min" value="6" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">MAX ETA (HRS)</label>
                                    <input type="number" name="estimated_delivery_hours_max" value="18" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                                </div>
                            </div>

                            <!-- Bidirectional Checkbox -->
                            <div style="background: rgba(94, 23, 235, 0.05); border: 1px dashed rgba(94, 23, 235, 0.3); border-radius: 6px; padding: 10px 12px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--adm-primary); margin: 0;">
                                    <input type="checkbox" name="is_bidirectional" value="1" checked style="accent-color: var(--adm-primary);">
                                    <span>Sync Bidirectional (A ⇄ B)</span>
                                </label>
                                <div style="font-size: 10.5px; color: #64748B; margin-top: 4px; margin-left: 20px;">
                                    Automatically configures the reverse corridor with identical fee and ETA.
                                </div>
                            </div>

                            <button type="submit" class="btn-dash btn-dash-primary" style="width: 100%; padding: 10px; font-size: 13px; font-weight: 700; justify-content: center;">
                                🚀 Publish Delivery Corridor
                            </button>
                        </form>
                    </div>

                    <!-- RIGHT COLUMN: ACTIVE CORRIDORS LIST & SEARCH -->
                    <div style="min-width: 0;">
                        <!-- Search and Quick Filter Bar -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap;">
                            <div style="position: relative; flex: 1; min-width: 200px;">
                                <span style="position: absolute; left: 12px; top: 10px; font-size: 13px; color: #94A3B8;">🔍</span>
                                <input type="text" id="vmLaneSearchInput" onkeyup="filterLaneRows()" placeholder="Filter corridors by LGA name or state..." style="width: 100%; padding: 9px 14px 9px 36px; border-radius: 8px; border: 1px solid var(--adm-border); font-size: 13px; outline: none; box-sizing: border-box;">
                            </div>
                            <span class="dash-badge dash-badge-info" style="font-size: 11.5px; padding: 6px 12px; white-space: nowrap;">
                                {{ count($deliveryLanes) }} Custom Corridors
                            </span>
                        </div>

                        <!-- Corridors Table -->
                        <div class="dash-table-responsive" style="border: 1px solid var(--adm-border); border-radius: 8px; overflow-x: auto;">
                            <table class="dash-table" id="vmLanesTable">
                                <thead>
                                    <tr>
                                        <th>Directional Route</th>
                                        <th>Type</th>
                                        <th>Base Fee</th>
                                        <th>Bulky Cargo</th>
                                        <th>Speed ETA</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($deliveryLanes as $lane)
                                        <tr class="vm-lane-row" data-search="{{ strtolower(($lane->originLga->name ?? '') . ' ' . ($lane->destinationLga->name ?? '') . ' ' . ($lane->originLga->state->name ?? '') . ' ' . ($lane->destinationLga->state->name ?? '')) }}">
                                            <td class="route-cell">
                                                <div style="display: flex; align-items: center; gap: 6px; font-size: 13.5px; flex-wrap: wrap;">
                                                    <span style="font-weight: 700; color: var(--adm-text);">{{ $lane->originLga->name ?? "LGA #{$lane->origin_lga_id}" }}</span>
                                                    <span style="color: var(--adm-primary); font-weight: 800;">&rarr;</span>
                                                    <span style="font-weight: 700; color: var(--adm-text);">{{ $lane->destinationLga->name ?? "LGA #{$lane->destination_lga_id}" }}</span>
                                                </div>
                                                <div style="font-size: 11px; color: var(--adm-text-muted); margin-top: 3px;">
                                                    <span style="color: #64748B;">{{ $lane->originLga->state->name ?? 'Akwa Ibom' }}</span>
                                                    <span style="color: #94A3B8; margin: 0 4px;">&rarr;</span>
                                                    <span style="color: #64748B;">{{ $lane->destinationLga->state->name ?? 'Akwa Ibom' }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($lane->is_bidirectional)
                                                    <span class="dash-badge dash-badge-success" title="Bidirectional return corridor exists" style="font-size: 10px; cursor: help;">
                                                        ⇄ Bidirectional
                                                    </span>
                                                @else
                                                    <span class="dash-badge dash-badge-info" style="font-size: 10px;">
                                                        &rarr; One-Way
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong style="font-size: 13.5px; color: #047857;">&#8358;{{ number_format((float)$lane->base_fee, 2) }}</strong>
                                            </td>
                                            <td>
                                                <span style="font-size: 12px; color: #64748B;">+&#8358;{{ number_format((float)($lane->bulky_surcharge ?? 1000.00), 2) }}</span>
                                            </td>
                                            <td>
                                                <span class="dash-badge dash-badge-purple" style="font-size: 11px;">
                                                    ⏱️ {{ $lane->estimated_delivery_hours_min }}-{{ $lane->estimated_delivery_hours_max }} hrs
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.delivery_lanes.toggle', $lane->id) }}" style="margin: 0;">
                                                    @csrf
                                                    <button type="submit" class="btn-dash {{ $lane->is_active ? 'btn-dash-success' : 'btn-dash-outline' }}" style="padding: 3px 8px; font-size: 10.5px;">
                                                        {{ $lane->is_active ? '● Active' : '○ Inactive' }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.delivery_lanes.delete', $lane->id) }}" onsubmit="return confirm('Delete this custom corridor? Fallback rates will apply.');" style="margin: 0;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-dash btn-dash-danger" style="padding: 3px 8px; font-size: 10.5px;" title="Delete Corridor">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                                No custom overrides registered yet. System is operating seamlessly on 3-tier zonal distance baseline rates!
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
    // Corridors Table Live Filter
    // -------------------------------------------------------------
    function filterLaneRows() {
        const query = document.getElementById('vmLaneSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#vmLanesTable .vm-lane-row');
        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            if (searchData.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // -------------------------------------------------------------
    // Searchable LGA Autocomplete & Combobox Architecture
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Parse Embedded Canonical LGAs Dataset
        let allLgas = [];
        try {
            const dataElem = document.getElementById('vmAllLgasData');
            if (dataElem) {
                allLgas = JSON.parse(dataElem.textContent);
            }
        } catch (err) {
            console.error('Failed to parse canonical LGA dataset', err);
        }

        // Helper string sanitizers
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

        // DOM Element References
        const originInput = document.getElementById('laneOriginSearchInput');
        const originHidden = document.getElementById('laneOriginSelect');
        const originClear = document.getElementById('laneOriginClearBtn');
        const originDropdown = document.getElementById('laneOriginDropdown');
        const originIndicator = document.getElementById('originSelectedIndicator');

        const destInput = document.getElementById('laneDestSearchInput');
        const destHidden = document.getElementById('laneDestSelect');
        const destClear = document.getElementById('laneDestClearBtn');
        const destDropdown = document.getElementById('laneDestDropdown');
        const destIndicator = document.getElementById('destSelectedIndicator');

        const btnSwap = document.getElementById('btnSwapOriginDest');

        // Dynamic Route Topology Indicator
        function updateTopologyPreview() {
            const originId = originHidden.value;
            const destId = destHidden.value;

            const originObj = allLgas.find(l => String(l.id) === String(originId));
            const destObj = allLgas.find(l => String(l.id) === String(destId));

            if (!originObj || !destObj) return;

            const originName = originObj.name;
            const destName = destObj.name;
            const originState = originObj.state;
            const destState = destObj.state;

            const labelOrigin = document.getElementById('previewOriginLabel');
            const labelDest = document.getElementById('previewDestLabel');
            const badgeTier = document.getElementById('previewTierBadge');
            const textDesc = document.getElementById('previewClassificationText');

            if (labelOrigin) labelOrigin.textContent = originName;
            if (labelDest) labelDest.textContent = destName;

            if (String(originId) === String(destId)) {
                if (badgeTier) {
                    badgeTier.className = 'dash-badge dash-badge-success';
                    badgeTier.textContent = 'Tier 1: Municipal (Intra-LGA)';
                }
                if (textDesc) {
                    textDesc.textContent = `Intra-LGA municipal corridor within ${originName}. Hyper-local same-day dispatch.`;
                }
            } else if (originState.toLowerCase() === destState.toLowerCase()) {
                if (badgeTier) {
                    badgeTier.className = 'dash-badge dash-badge-info';
                    badgeTier.textContent = 'Tier 2: Regional (Inter-LGA)';
                }
                if (textDesc) {
                    textDesc.textContent = `Inter-LGA regional corridor between ${originName} and ${destName} within ${originState}.`;
                }
            } else {
                if (badgeTier) {
                    badgeTier.className = 'dash-badge dash-badge-purple';
                    badgeTier.textContent = 'Tier 3: Inter-State Freight';
                }
                if (textDesc) {
                    textDesc.textContent = `Inter-state cargo corridor spanning ${originState} to ${destState}.`;
                }
            }
        }

        // Generic LGA Combobox Binder
        function bindLgaCombobox({
            inputElem,
            hiddenElem,
            clearBtnElem,
            dropdownElem,
            indicatorElem,
            quickHubSelector,
            onSelect
        }) {
            let activeNavIndex = -1;

            function filterDataset(query) {
                const q = (query || '').trim().toLowerCase();
                if (!q) {
                    return allLgas.slice(0, 35);
                }
                return allLgas.filter(l => 
                    l.name.toLowerCase().includes(q) || 
                    (l.state && l.state.toLowerCase().includes(q))
                ).slice(0, 35);
            }

            function renderDropdown(items, query = '') {
                if (!items.length) {
                    dropdownElem.innerHTML = `
                        <div style="padding: 14px 12px; text-align: center; color: #94A3B8; font-size: 12px;">
                            No Local Government Area found matching &ldquo;<strong>${escapeHtml(query)}</strong>&rdquo;
                        </div>
                    `;
                    dropdownElem.style.display = 'block';
                    return;
                }

                let html = '';
                items.forEach((lga, idx) => {
                    const isSelected = String(hiddenElem.value) === String(lga.id);
                    const isAkwa = lga.is_akwa;
                    
                    let highlightedName = escapeHtml(lga.name);
                    if (query) {
                        const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
                        highlightedName = highlightedName.replace(regex, '<mark style="background: rgba(94, 23, 235, 0.16); color: #5E17EB; padding: 0 2px; border-radius: 2px;">$1</mark>');
                    }

                    html += `
                        <div class="vm-lga-option-row ${isSelected ? 'active-row' : ''}" data-idx="${idx}" data-id="${lga.id}" data-name="${escapeHtml(lga.name)}" data-state="${escapeHtml(lga.state)}">
                            <div style="display: flex; align-items: baseline;">
                                <span class="vm-lga-option-name">${highlightedName}</span>
                                <span class="vm-lga-option-state">&mdash; ${escapeHtml(lga.state)}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                ${isSelected ? '<span style="color: #059669; font-weight: 800; font-size: 13px;">✓</span>' : ''}
                                <span class="vm-lga-badge-zone" style="${isAkwa ? 'background: #ECFDF5; color: #059669;' : 'background: #F1F5F9; color: #64748B;'}">
                                    ${isAkwa ? '⚡ Akwa Ibom' : 'Nationwide'}
                                </span>
                            </div>
                        </div>
                    `;
                });

                dropdownElem.innerHTML = html;
                dropdownElem.style.display = 'block';

                // Row Click Handlers
                dropdownElem.querySelectorAll('.vm-lga-option-row').forEach(row => {
                    row.addEventListener('click', function (e) {
                        e.stopPropagation();
                        selectLga({
                            id: this.dataset.id,
                            name: this.dataset.name,
                            state: this.dataset.state
                        });
                    });
                });
            }

            function selectLga(lga) {
                hiddenElem.value = lga.id;
                inputElem.value = `${lga.name} — ${lga.state}`;
                if (indicatorElem) {
                    indicatorElem.textContent = `Selected: ${lga.name}`;
                }
                dropdownElem.style.display = 'none';
                activeNavIndex = -1;
                if (typeof onSelect === 'function') {
                    onSelect(lga);
                }
            }

            // Input Event (Typing Live Filter)
            inputElem.addEventListener('input', function () {
                const query = this.value;
                const matches = filterDataset(query);
                renderDropdown(matches, query);
            });

            // Focus Event (Open Full or Filtered List)
            inputElem.addEventListener('focus', function () {
                const matches = filterDataset(this.value);
                renderDropdown(matches, this.value);
            });

            // Clear Button Event
            if (clearBtnElem) {
                clearBtnElem.addEventListener('click', function (e) {
                    e.stopPropagation();
                    inputElem.value = '';
                    inputElem.focus();
                    renderDropdown(filterDataset(''), '');
                });
            }

            // Keyboard Navigation (ArrowUp, ArrowDown, Enter, Escape)
            inputElem.addEventListener('keydown', function (e) {
                const rows = dropdownElem.querySelectorAll('.vm-lga-option-row');
                if (!rows.length || dropdownElem.style.display === 'none') {
                    if (e.key === 'ArrowDown' || e.key === 'Enter') {
                        renderDropdown(filterDataset(inputElem.value), inputElem.value);
                    }
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeNavIndex = (activeNavIndex + 1) % rows.length;
                    highlightRow(rows, activeNavIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeNavIndex = (activeNavIndex - 1 + rows.length) % rows.length;
                    highlightRow(rows, activeNavIndex);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeNavIndex >= 0 && rows[activeNavIndex]) {
                        rows[activeNavIndex].click();
                    } else if (rows[0]) {
                        rows[0].click();
                    }
                } else if (e.key === 'Escape') {
                    dropdownElem.style.display = 'none';
                }
            });

            function highlightRow(rows, idx) {
                rows.forEach((r, i) => {
                    if (i === idx) {
                        r.classList.add('active-row');
                        r.scrollIntoView({ block: 'nearest' });
                    } else {
                        r.classList.remove('active-row');
                    }
                });
            }

            // Quick Hub Click Shortcuts
            if (quickHubSelector) {
                document.querySelectorAll(quickHubSelector).forEach(btn => {
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        selectLga({
                            id: this.dataset.id,
                            name: this.dataset.name,
                            state: this.dataset.state
                        });
                    });
                });
            }
        }

        // 2. Initialize Origin LGA Combobox
        bindLgaCombobox({
            inputElem: originInput,
            hiddenElem: originHidden,
            clearBtnElem: originClear,
            dropdownElem: originDropdown,
            indicatorElem: originIndicator,
            quickHubSelector: '.vm-quick-origin-hub',
            onSelect: updateTopologyPreview
        });

        // 3. Initialize Destination LGA Combobox
        bindLgaCombobox({
            inputElem: destInput,
            hiddenElem: destHidden,
            clearBtnElem: destClear,
            dropdownElem: destDropdown,
            indicatorElem: destIndicator,
            quickHubSelector: '.vm-quick-dest-hub',
            onSelect: updateTopologyPreview
        });

        // 4. Swap Origin ⇄ Destination Button Engine
        if (btnSwap) {
            btnSwap.addEventListener('click', function () {
                const prevOriginId = originHidden.value;
                const prevOriginText = originInput.value;
                const prevDestId = destHidden.value;
                const prevDestText = destInput.value;

                // Swap values
                originHidden.value = prevDestId;
                originInput.value = prevDestText;

                destHidden.value = prevOriginId;
                destInput.value = prevOriginText;

                // Update indicators
                const newOriginObj = allLgas.find(l => String(l.id) === String(prevDestId));
                const newDestObj = allLgas.find(l => String(l.id) === String(prevOriginId));

                if (newOriginObj && originIndicator) {
                    originIndicator.textContent = `Selected: ${newOriginObj.name}`;
                }
                if (newDestObj && destIndicator) {
                    destIndicator.textContent = `Selected: ${newDestObj.name}`;
                }

                updateTopologyPreview();
            });
        }

        // 5. Global Document Click Outside Closer
        document.addEventListener('click', function (e) {
            const originWrapper = document.getElementById('originLgaWrapper');
            const destWrapper = document.getElementById('destLgaWrapper');

            if (originDropdown && originWrapper && !originWrapper.contains(e.target)) {
                originDropdown.style.display = 'none';
            }
            if (destDropdown && destWrapper && !destWrapper.contains(e.target)) {
                destDropdown.style.display = 'none';
            }
        });

        // 6. Form Submission Safeguard
        const addCorridorForm = document.getElementById('vmAddCorridorForm');
        if (addCorridorForm) {
            addCorridorForm.addEventListener('submit', function (e) {
                if (!originHidden.value || !destHidden.value) {
                    e.preventDefault();
                    alert('Please select a valid Origin and Destination LGA from the searchable dropdown list.');
                }
            });
        }

        // Initialize topology summary on load
        updateTopologyPreview();
    });
</script>
@endpush