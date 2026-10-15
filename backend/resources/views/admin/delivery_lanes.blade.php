@extends('layouts.dashboard')

@section('title', 'Directional LGA Delivery Lanes & 3-Tier Distance Matrix')
@section('role_title', 'Super Admin')
@section('page_title', 'Directional LGA Delivery Lanes')
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
                <div style="display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start;">
                    <!-- LEFT COLUMN: ADD CUSTOM OVERRIDE CORRIDOR FORM -->
                    <div style="background: #F8FAFC; border: 1px solid var(--adm-border); border-radius: 10px; padding: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                            <strong style="font-size: 13.5px; color: var(--adm-text); display: flex; align-items: center; gap: 6px;">
                                <span>➕</span> Add Custom Corridor
                            </strong>
                            <span class="dash-badge dash-badge-purple" style="font-size: 10px;">LGA ⇄ LGA</span>
                        </div>

                        <form method="POST" action="{{ route('admin.delivery_lanes.store') }}">
                            @csrf
                            
                            <!-- Origin Card -->
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">ORIGIN LGA (DISPATCH FROM)</label>
                                <select name="origin_lga_id" id="laneOriginSelect" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF; font-size: 13px;">
                                    @foreach($allLgas ?? [] as $lga)
                                        <option value="{{ $lga->id }}" {{ $lga->name == 'Uyo' ? 'selected' : '' }}>{{ $lga->name }} ({{ $lga->state ?? 'Akwa Ibom' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Destination Card -->
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 4px; display: block;">DESTINATION LGA (DELIVER TO)</label>
                                <select name="destination_lga_id" id="laneDestSelect" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: #FFF; font-size: 13px;">
                                    @foreach($allLgas ?? [] as $lga)
                                        <option value="{{ $lga->id }}" {{ $lga->name == 'Eket' ? 'selected' : '' }}>{{ $lga->name }} ({{ $lga->state ?? 'Akwa Ibom' }})</option>
                                    @endforeach
                                </select>
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
                    <div>
                        <!-- Search and Quick Filter Bar -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
                            <div style="position: relative; flex: 1;">
                                <span style="position: absolute; left: 12px; top: 10px; font-size: 13px; color: #94A3B8;">🔍</span>
                                <input type="text" id="vmLaneSearchInput" onkeyup="filterLaneRows()" placeholder="Filter corridors by LGA name or state..." style="width: 100%; padding: 9px 14px 9px 36px; border-radius: 8px; border: 1px solid var(--adm-border); font-size: 13px; outline: none;">
                            </div>
                            <span class="dash-badge dash-badge-info" style="font-size: 11.5px; padding: 6px 12px; white-space: nowrap;">
                                {{ count($deliveryLanes) }} Custom Corridors
                            </span>
                        </div>

                        <!-- Corridors Table -->
                        <div class="dash-table-responsive" style="max-height: 540px; overflow-y: auto; border: 1px solid var(--adm-border); border-radius: 8px;">
                            <table class="dash-table" id="vmLanesTable">
                                <thead style="position: sticky; top: 0; background: #F8FAFC; z-index: 10;">
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
                                        <tr class="vm-lane-row" data-search="{{ strtolower(($lane->originLga->name ?? '') . ' ' . ($lane->destinationLga->name ?? '')) }}">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                                    <span style="font-weight: 700; color: var(--adm-text);">{{ $lane->originLga->name ?? "LGA #{$lane->origin_lga_id}" }}</span>
                                                    <span style="color: var(--adm-primary); font-weight: 800;">&rarr;</span>
                                                    <span style="font-weight: 700; color: var(--adm-text);">{{ $lane->destinationLga->name ?? "LGA #{$lane->destination_lga_id}" }}</span>
                                                </div>
                                                <div style="font-size: 11px; color: var(--adm-text-muted);">
                                                    {{ $lane->originLga->state ?? 'Akwa Ibom' }} to {{ $lane->destinationLga->state ?? 'Akwa Ibom' }}
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
</script>
@endpush