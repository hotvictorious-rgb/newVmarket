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

<!-- ===================================================================== -->
<!-- 1. HUB ORIGIN SELECTOR & DIAGNOSTIC COMMAND BAR                       -->
<!-- ===================================================================== -->
<div class="dash-card" style="background: linear-gradient(135deg, #F8FAFC 0%, #EEF2FF 100%); border: 1.5px solid #CBD5E1; border-radius: 12px; padding: 20px 22px; margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #5E17EB; color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.25);">
                📍
            </div>
            <div>
                <div style="font-size: 11px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.6px;">ACTIVE DISPATCH HUB</div>
                <div style="font-size: 22px; font-weight: 800; color: #0F172A; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span>{{ $currentHub->name }}</span>
                    <span style="font-size: 14px; font-weight: 600; color: #64748B;">({{ $currentHub->state->name ?? 'Akwa Ibom' }})</span>
                    <span class="dash-badge dash-badge-purple" style="font-size: 11px; padding: 2px 8px;">Origin Base</span>
                </div>
            </div>
        </div>

        <!-- Searchable Hub Switcher Combobox Engine -->
        <div style="min-width: 320px; max-width: 420px; flex: 1; position: relative;" id="hubSwitcherWrapper">
            <div style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 5px; display: flex; justify-content: space-between;">
                <span>SWITCH ORIGIN HUB LGA:</span>
                <span style="color: var(--adm-primary); font-weight: 800;">774 LGAs Nationwide</span>
            </div>
            <div class="vm-lga-input-container">
                <span class="vm-lga-icon-prefix">🔍</span>
                <input 
                    type="text" 
                    id="vmHubSwitcherInput" 
                    class="vm-lga-search-box" 
                    placeholder="Search any LGA in Nigeria (e.g. Uyo, Eket, Ikeja)..."
                    value="{{ $currentHub->name }} — {{ $currentHub->state->name ?? 'Akwa Ibom' }}"
                    autocomplete="off"
                >
                <button type="button" class="vm-lga-clear-btn" id="vmHubClearBtn" title="Clear and search">✕</button>
            </div>
            <div class="vm-lga-floating-menu" id="vmHubDropdown"></div>
        </div>
    </div>

    <!-- Quick Hub Chips -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding-top: 12px; border-top: 1px dashed #CBD5E1;">
        <span style="font-size: 11px; font-weight: 700; color: #64748B;">⚡ Quick Hubs:</span>
        @php
            $quickHubNames = ['Uyo', 'Eket', 'Ikot Ekpene', 'Oron', 'Abak', 'Ibesikpo Asutan', 'Calabar Municipal', 'Port Harcourt', 'Ikeja', 'Abuja Municipal'];
            $quickHubList = $allLgas->filter(fn($l) => in_array($l->name, $quickHubNames));
        @endphp
        @foreach($quickHubList as $qh)
            <a href="{{ route('admin.delivery_lanes.index', ['hub_lga_id' => $qh->id]) }}" 
               class="btn-dash {{ $qh->id === $currentHub->id ? 'btn-dash-primary' : 'btn-dash-outline' }}" 
               style="padding: 3px 10px; font-size: 11px; text-decoration: none; border-radius: 6px; {{ $qh->id === $currentHub->id ? '' : 'background: #FFF;' }}">
                {{ $qh->name }}
            </a>
        @endforeach
    </div>

    <!-- Hub Real-Time Routing Diagnostics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-top: 16px;">
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">TIER 1: INTRA-LGA</div>
                <div style="font-size: 14.5px; font-weight: 800; color: {{ $intraActive ? '#059669' : '#DC2626' }};">
                    {{ $intraActive ? '● Serviced (Active)' : '○ Suspended' }}
                </div>
                <div style="font-size: 10.5px; color: #94A3B8;">Within {{ $currentHub->name }}</div>
            </div>
            <span style="font-size: 22px;">🏙️</span>
        </div>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">TIER 2: INTER-LGA COLLECTION POINTS</div>
                <div style="font-size: 14.5px; font-weight: 800; color: #2563EB;">
                    {{ $interActiveCount }} / {{ $stateLgas->count() }} LGAs Serviced
                </div>
                <div style="font-size: 10.5px; color: #94A3B8;">Across {{ $currentHub->state->name ?? 'State' }}</div>
            </div>
            <span style="font-size: 22px;">🚚</span>
        </div>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">TIER 3: INTER-STATE COLLECTION POINTS</div>
                <div style="font-size: 14.5px; font-weight: 800; color: #D97706;">
                    {{ $outOfStateActiveCount }} / {{ $otherStates->count() }} States Serviced
                </div>
                <div style="font-size: 10.5px; color: #94A3B8;">Nationwide Long-Haul</div>
            </div>
            <span style="font-size: 22px;">🗺️</span>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- 2. TIER 1: INTRA-LGA MUNICIPAL DISPATCH CARD                          -->
<!-- ===================================================================== -->
<div style="background: #FFFFFF; border: 1.5px solid {{ $intraActive ? '#10B981' : '#F59E0B' }}; border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 24px;">🏙️</span>
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <strong style="font-size: 15px; color: #0F172A;">Tier 1: Municipal Dispatch (Within {{ $currentHub->name }})</strong>
                    @if($intraActive)
                        <span class="dash-badge dash-badge-success" style="font-size: 10.5px;">● Serviced (Active)</span>
                    @else
                        <span class="dash-badge dash-badge-danger" style="font-size: 10.5px;">○ Suspended</span>
                    @endif
                    <span class="dash-badge dash-badge-info" style="font-size: 10px;">Rapid Rider (2–4 hrs)</span>
                </div>
                <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                    Same-LGA fulfillment originating and delivering within {{ $currentHub->name }} municipality.
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <!-- 1-Click Toggle Form -->
            <form method="POST" action="{{ route('admin.delivery_lanes.toggle_corridor') }}" style="margin: 0;">
                @csrf
                <input type="hidden" name="origin_lga_id" value="{{ $currentHub->id }}">
                <input type="hidden" name="destination_lga_id" value="{{ $currentHub->id }}">
                <input type="hidden" name="set_active" value="{{ $intraActive ? '0' : '1' }}">
                <button type="submit" class="btn-dash {{ $intraActive ? 'btn-dash-danger' : 'btn-dash-success' }}" style="padding: 6px 14px; font-size: 11.5px; font-weight: 700;">
                    {{ $intraActive ? '🔴 Suspend Intra-LGA' : '🟢 Restore Intra-LGA' }}
                </button>
            </form>

            <!-- Edit Rate Modal Trigger -->
            <button type="button" class="btn-dash btn-dash-outline" style="padding: 6px 14px; font-size: 11.5px; font-weight: 700; background: #FFF;" 
                    onclick="openCorridorModal({
                        id: '{{ $intraLane->id ?? '' }}',
                        origin_id: '{{ $currentHub->id }}',
                        origin_name: '{{ $currentHub->name }} ({{ $currentHub->state->name ?? 'Akwa Ibom' }})',
                        dest_id: '{{ $currentHub->id }}',
                        dest_name: '{{ $currentHub->name }} (Same LGA)',
                        base_fee: '{{ $intraLane->base_fee ?? $zonalSettings['zone_intra_lga_fee'] }}',
                        bulky: '{{ $intraLane->bulky_surcharge ?? $zonalSettings['zone_bulky_cargo_surcharge'] }}',
                        min_eta: '{{ $intraLane->estimated_delivery_hours_min ?? 2 }}',
                        max_eta: '{{ $intraLane->estimated_delivery_hours_max ?? 4 }}',
                        tier_label: 'Tier 1: Municipal (Intra-LGA)',
                        is_bidi: 1
                    })">
                ✏️ Edit Intra Rates
            </button>
        </div>
    </div>

    <!-- Intra Rates Metrics Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; background: #F8FAFC; border-radius: 8px; padding: 12px 16px;">
        <div>
            <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">BASE DELIVERY FEE</div>
            <div style="font-size: 16px; font-weight: 800; color: #047857;">
                ₦{{ number_format((float)($intraLane->base_fee ?? $zonalSettings['zone_intra_lga_fee']), 2) }}
            </div>
            <div style="font-size: 10.5px; color: #94A3B8;">{{ $intraLane ? 'Custom corridor rate' : 'Tier 1 baseline rate' }}</div>
        </div>
        <div>
            <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">BULKY CARGO SURCHARGE</div>
            <div style="font-size: 16px; font-weight: 800; color: #B45309;">
                +₦{{ number_format((float)($intraLane->bulky_surcharge ?? $zonalSettings['zone_bulky_cargo_surcharge']), 2) }}
            </div>
            <div style="font-size: 10.5px; color: #94A3B8;">Applied when parcel &gt; 15kg</div>
        </div>
        <div>
            <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">SPEED ESTIMATE (ETA)</div>
            <div style="font-size: 16px; font-weight: 800; color: #5E17EB;">
                ⏱️ {{ $intraLane ? "{$intraLane->estimated_delivery_hours_min}-{$intraLane->estimated_delivery_hours_max} hrs" : $zonalSettings['zone_intra_lga_eta'] }}
            </div>
            <div style="font-size: 10.5px; color: #94A3B8;">Rapid municipal courier</div>
        </div>
        <div>
            <div style="font-size: 10.5px; font-weight: 700; color: #64748B;">CHECKOUT BEHAVIOR</div>
            <div style="font-size: 13.5px; font-weight: 700; color: {{ $intraActive ? '#059669' : '#DC2626' }};">
                {{ $intraActive ? '✓ Doorstep Delivery Active' : '⚠ Auto-Fallback to Free In-Store Pickup' }}
            </div>
            <div style="font-size: 10.5px; color: #94A3B8;">{{ $intraActive ? 'Rider dispatched immediately' : 'Zero checkout drop-off' }}</div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- 3. TIER 2: INTER-LGA REGIONAL CORRIDORS (ALL LGAS IN SAME STATE)      -->
<!-- ===================================================================== -->
<div class="dash-card" style="margin-bottom: 24px;">
    <div class="dash-card-header" style="background: #FFFFFF;">
        <div>
            <div class="dash-card-title">
                <span style="font-size: 18px;">🚚</span>
                <div>
                    <span style="font-weight: 800; color: var(--adm-text);">Tier 2: Inter-LGA Regional Corridors (Collection Points)</span>
                    <span class="dash-badge dash-badge-info" style="font-size: 11px; margin-left: 6px;">{{ $stateLgas->count() }} LGAs in {{ $currentHub->state->name ?? 'State' }}</span>
                    <div class="dash-card-subtitle" style="margin-top: 2px;">
                        Delivery corridors originating from <strong>{{ $currentHub->name }}</strong> to every LGA across {{ $currentHub->state->name ?? 'State' }}. Customers pick up at accredited company Collection Points.
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Buttons & Search -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn-dash btn-dash-primary btn-inter-filter" data-filter="all" onclick="setInterLgaFilter('all')" style="padding: 5px 12px; font-size: 11px; font-weight: 700;">
                All ({{ $stateLgas->count() }})
            </button>
            <button type="button" class="btn-dash btn-dash-outline btn-inter-filter" data-filter="active" onclick="setInterLgaFilter('active')" style="padding: 5px 12px; font-size: 11px; font-weight: 700; background: #FFF;">
                🟢 Serviced ({{ $interActiveCount }})
            </button>
            <button type="button" class="btn-dash btn-dash-outline btn-inter-filter" data-filter="suspended" onclick="setInterLgaFilter('suspended')" style="padding: 5px 12px; font-size: 11px; font-weight: 700; background: #FFF;">
                🔴 Suspended ({{ $stateLgas->count() - $interActiveCount }})
            </button>
        </div>
    </div>

    <!-- Live Search Bar -->
    <div style="padding: 12px 18px; background: #F8FAFC; border-bottom: 1px solid var(--adm-border); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div style="position: relative; flex: 1; min-width: 240px;">
            <span style="position: absolute; left: 10px; top: 8px; font-size: 12px; color: #94A3B8;">🔍</span>
            <input type="text" id="interLgaSearchInput" onkeyup="filterInterLgaRows()" placeholder="Filter {{ $currentHub->state->name ?? 'State' }} LGAs (e.g. Eket, Oron, Ikot Ekpene, Abak)..." style="width: 100%; padding: 7px 12px 7px 30px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px; outline: none; box-sizing: border-box;">
        </div>
        <span class="dash-badge dash-badge-info" id="interLgaCountBadge" style="font-size: 11px; padding: 5px 10px; white-space: nowrap;">
            {{ $stateLgas->count() }} LGAs Available
        </span>
    </div>

    <!-- Inter-LGA Table -->
    <div class="dash-table-responsive" style="max-height: 480px; overflow-y: auto;">
        <table class="dash-table" id="vmInterLgaTable">
            <thead>
                <tr>
                    <th style="width: 24%;">Destination LGA</th>
                    <th style="width: 14%;">Flow &amp; Symmetry</th>
                    <th style="width: 14%;">Base Fee</th>
                    <th style="width: 13%;">Bulky Cargo</th>
                    <th style="width: 13%;">Speed ETA</th>
                    <th style="width: 11%;">Status</th>
                    <th style="width: 11%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stateLgas as $lga)
                    @php
                        $lane = $interLanes->get($lga->id);
                        $isServiced = $lane ? (bool)$lane->is_active : true;
                        $hasReverse = $reverseInterLanes->has($lga->id);
                        $fee = $lane ? $lane->base_fee : $zonalSettings['zone_inter_lga_fee'];
                        $bulky = $lane ? ($lane->bulky_surcharge ?? $zonalSettings['zone_bulky_cargo_surcharge']) : $zonalSettings['zone_bulky_cargo_surcharge'];
                        $etaText = $lane ? "{$lane->estimated_delivery_hours_min}-{$lane->estimated_delivery_hours_max} hrs" : $zonalSettings['zone_inter_lga_eta'];
                        $minEta = $lane ? $lane->estimated_delivery_hours_min : 12;
                        $maxEta = $lane ? $lane->estimated_delivery_hours_max : 24;
                    @endphp
                    <tr class="vm-inter-row" data-status="{{ $isServiced ? 'active' : 'suspended' }}" data-search="{{ strtolower($lga->name . ' ' . ($currentHub->state->name ?? '')) }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">{{ $lga->name }}</strong>
                            </div>
                            <div style="font-size: 11px; color: #64748B; margin-top: 2px;">
                                <span style="font-weight: 600;">{{ $currentHub->name }}</span>
                                <span style="color: var(--adm-primary); font-weight: 800;">➔</span>
                                <span>{{ $lga->name }}</span>
                            </div>
                        </td>
                        <td>
                            @if($hasReverse)
                                <span class="dash-badge dash-badge-success" title="Return corridor exists and active ({{ $lga->name }} ➔ {{ $currentHub->name }})" style="font-size: 10px; cursor: help;">
                                    ⇄ Bidirectional
                                </span>
                            @else
                                <span class="dash-badge dash-badge-info" title="One-way route (Asymmetry: {{ $currentHub->name }} ➔ {{ $lga->name }} only)" style="font-size: 10px; cursor: help;">
                                    &rarr; One-Way
                                </span>
                            @endif
                        </td>
                        <td>
                            <strong style="font-size: 13px; color: #047857;">₦{{ number_format((float)$fee, 2) }}</strong>
                            <div style="font-size: 10px; color: #94A3B8;">{{ $lane ? 'Custom rate' : 'Tier 2 baseline' }}</div>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #64748B;">+₦{{ number_format((float)$bulky, 2) }}</span>
                        </td>
                        <td>
                            <span class="dash-badge dash-badge-purple" style="font-size: 10.5px;">
                                ⏱️ {{ $etaText }}
                            </span>
                        </td>
                        <td>
                            @if($isServiced)
                                <span class="dash-badge dash-badge-success" style="font-size: 10px;">
                                    ● {{ $lane ? 'Serviced' : 'Baseline' }}
                                </span>
                            @else
                                <span class="dash-badge dash-badge-danger" style="font-size: 10px;">
                                    ○ Suspended
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                <!-- 1-Click Fast Toggle -->
                                <form method="POST" action="{{ route('admin.delivery_lanes.toggle_corridor') }}" style="margin: 0;">
                                    @csrf
                                    <input type="hidden" name="origin_lga_id" value="{{ $currentHub->id }}">
                                    <input type="hidden" name="destination_lga_id" value="{{ $lga->id }}">
                                    <input type="hidden" name="set_active" value="{{ $isServiced ? '0' : '1' }}">
                                    <button type="submit" class="btn-dash {{ $isServiced ? 'btn-dash-danger' : 'btn-dash-success' }}" style="padding: 2px 7px; font-size: 10.5px; font-weight: 700;" title="{{ $isServiced ? 'Suspend Doorstep Delivery along this corridor' : 'Restore Doorstep Delivery along this corridor' }}">
                                        {{ $isServiced ? '🔴 Suspend' : '🟢 Restore' }}
                                    </button>
                                </form>

                                <!-- Edit Rates Modal Trigger -->
                                <button type="button" class="btn-dash btn-dash-outline" style="padding: 2px 7px; font-size: 10.5px; font-weight: 700; background: #FFF;" title="Configure Custom Corridor Rate &amp; ETA" 
                                        onclick="openCorridorModal({
                                            id: '{{ $lane->id ?? '' }}',
                                            origin_id: '{{ $currentHub->id }}',
                                            origin_name: '{{ $currentHub->name }} ({{ $currentHub->state->name ?? 'Akwa Ibom' }})',
                                            dest_id: '{{ $lga->id }}',
                                            dest_name: '{{ $lga->name }} ({{ $currentHub->state->name ?? 'Akwa Ibom' }})',
                                            base_fee: '{{ $fee }}',
                                            bulky: '{{ $bulky }}',
                                            min_eta: '{{ $minEta }}',
                                            max_eta: '{{ $maxEta }}',
                                            tier_label: 'Tier 2: Regional (Inter-LGA)',
                                            is_bidi: {{ $hasReverse ? 1 : 0 }}
                                        })">
                                    ✏️ Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- 4. TIER 3: OUT-OF-STATE CORRIDORS (ALL OTHER STATES ACROSS NIGERIA)   -->
<!-- ===================================================================== -->
<div class="dash-card" style="margin-bottom: 24px;">
    <div class="dash-card-header" style="background: #FFFFFF;">
        <div>
            <div class="dash-card-title">
                <span style="font-size: 18px;">🗺️</span>
                <div>
                    <span style="font-weight: 800; color: var(--adm-text);">Tier 3: Out-of-State Corridors (Collection Points)</span>
                    <span class="dash-badge dash-badge-gold" style="font-size: 11px; margin-left: 6px;">{{ $otherStates->count() }} States Across Nigeria</span>
                    <div class="dash-card-subtitle" style="margin-top: 2px;">
                        Long-haul freight originating from <strong>{{ $currentHub->name }} ({{ $currentHub->state->name ?? 'Akwa Ibom' }})</strong> to all other states across the federation. Customers pick up at designated company Collection Points.
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Buttons & Search -->
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn-dash btn-dash-primary btn-state-filter" data-filter="all" onclick="setOutOfStateFilter('all')" style="padding: 5px 12px; font-size: 11px; font-weight: 700;">
                All ({{ $otherStates->count() }})
            </button>
            <button type="button" class="btn-dash btn-dash-outline btn-state-filter" data-filter="active" onclick="setOutOfStateFilter('active')" style="padding: 5px 12px; font-size: 11px; font-weight: 700; background: #FFF;">
                🟢 Serviced ({{ $outOfStateActiveCount }})
            </button>
            <button type="button" class="btn-dash btn-dash-outline btn-state-filter" data-filter="suspended" onclick="setOutOfStateFilter('suspended')" style="padding: 5px 12px; font-size: 11px; font-weight: 700; background: #FFF;">
                🔴 Suspended ({{ $otherStates->count() - $outOfStateActiveCount }})
            </button>
        </div>
    </div>

    <!-- Live Search Bar -->
    <div style="padding: 12px 18px; background: #F8FAFC; border-bottom: 1px solid var(--adm-border); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div style="position: relative; flex: 1; min-width: 240px;">
            <span style="position: absolute; left: 10px; top: 8px; font-size: 12px; color: #94A3B8;">🔍</span>
            <input type="text" id="outOfStateSearchInput" onkeyup="filterOutOfStateRows()" placeholder="Filter Nigerian States (e.g. Abia, Rivers, Cross River, Lagos, Abuja)..." style="width: 100%; padding: 7px 12px 7px 30px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 12.5px; outline: none; box-sizing: border-box;">
        </div>
        <span class="dash-badge dash-badge-gold" id="outOfStateCountBadge" style="font-size: 11px; padding: 5px 10px; white-space: nowrap;">
            {{ $otherStates->count() }} States Configured
        </span>
    </div>

    <!-- Out-of-State Table -->
    <div class="dash-table-responsive" style="max-height: 480px; overflow-y: auto;">
        <table class="dash-table" id="vmOutOfStateTable">
            <thead>
                <tr>
                    <th style="width: 24%;">Destination State</th>
                    <th style="width: 14%;">Freight Corridor</th>
                    <th style="width: 14%;">Base Freight</th>
                    <th style="width: 13%;">Bulky Cargo</th>
                    <th style="width: 13%;">Transit ETA</th>
                    <th style="width: 11%;">Status</th>
                    <th style="width: 11%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($otherStates as $st)
                    @php
                        $stLanes = $outOfStateLanes->get($st->id);
                        $firstLane = $stLanes ? $stLanes->first() : null;
                        $hasSuspendedLane = $stLanes && $stLanes->contains('is_active', false);
                        $isStateServiced = !$hasSuspendedLane;
                        $stFee = $firstLane ? $firstLane->base_fee : $zonalSettings['zone_inter_state_fee'];
                        $stBulky = $firstLane ? ($firstLane->bulky_surcharge ?? $zonalSettings['zone_bulky_cargo_surcharge']) : $zonalSettings['zone_bulky_cargo_surcharge'];
                        $stEta = $firstLane ? "{$firstLane->estimated_delivery_hours_min}-{$firstLane->estimated_delivery_hours_max} hrs" : $zonalSettings['zone_inter_state_eta'];
                        $stMinEta = $firstLane ? $firstLane->estimated_delivery_hours_min : 48;
                        $stMaxEta = $firstLane ? $firstLane->estimated_delivery_hours_max : 96;
                        $capitalLga = $st->lgas->first();
                    @endphp
                    <tr class="vm-state-row" data-status="{{ $isStateServiced ? 'active' : 'suspended' }}" data-search="{{ strtolower($st->name) }}">
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <strong style="font-size: 13px; color: var(--adm-text);">{{ $st->name }} State</strong>
                                <span class="dash-badge" style="background: #F1F5F9; color: #475569; font-size: 9.5px; padding: 1px 5px;">
                                    {{ $st->lgas->count() }} LGAs
                                </span>
                            </div>
                            <div style="font-size: 10.5px; color: #94A3B8; margin-top: 2px;">
                                Hub: {{ $capitalLga->name ?? 'Statewide' }}
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 11.5px; color: #475569; font-weight: 600;">
                                <span>{{ $currentHub->name }}</span>
                                <span style="color: var(--adm-primary); font-weight: 800;">➔</span>
                                <span>{{ $st->name }}</span>
                            </div>
                            <div style="font-size: 10px; color: #94A3B8;">Inter-State National Freight</div>
                        </td>
                        <td>
                            <strong style="font-size: 13px; color: #047857;">₦{{ number_format((float)$stFee, 2) }}</strong>
                            <div style="font-size: 10px; color: #94A3B8;">{{ $firstLane ? 'Custom corridor rate' : 'Tier 3 baseline' }}</div>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: #64748B;">+₦{{ number_format((float)$stBulky, 2) }}</span>
                        </td>
                        <td>
                            <span class="dash-badge dash-badge-gold" style="font-size: 10.5px;">
                                ⏱️ {{ $stEta }}
                            </span>
                        </td>
                        <td>
                            @if($isStateServiced)
                                <span class="dash-badge dash-badge-success" style="font-size: 10px;">
                                    ● {{ $firstLane ? 'Serviced' : 'Baseline' }}
                                </span>
                            @else
                                <span class="dash-badge dash-badge-danger" style="font-size: 10px;">
                                    ○ Suspended
                                </span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;">
                                <!-- 1-Click Fast Toggle for State Corridor -->
                                <form method="POST" action="{{ route('admin.delivery_lanes.toggle_corridor') }}" style="margin: 0;">
                                    @csrf
                                    <input type="hidden" name="origin_lga_id" value="{{ $currentHub->id }}">
                                    <input type="hidden" name="destination_state_id" value="{{ $st->id }}">
                                    <input type="hidden" name="set_active" value="{{ $isStateServiced ? '0' : '1' }}">
                                    <button type="submit" class="btn-dash {{ $isStateServiced ? 'btn-dash-danger' : 'btn-dash-success' }}" style="padding: 2px 7px; font-size: 10.5px; font-weight: 700;" title="{{ $isStateServiced ? 'Suspend delivery to this State' : 'Restore delivery to this State' }}">
                                        {{ $isStateServiced ? '🔴 Suspend' : '🟢 Restore' }}
                                    </button>
                                </form>

                                <!-- Configure State Rates Modal Trigger -->
                                <button type="button" class="btn-dash btn-dash-outline" style="padding: 2px 7px; font-size: 10.5px; font-weight: 700; background: #FFF;" title="Configure Freight Rates &amp; ETA for this State" 
                                        onclick="openCorridorModal({
                                            id: '{{ $firstLane->id ?? '' }}',
                                            origin_id: '{{ $currentHub->id }}',
                                            origin_name: '{{ $currentHub->name }} ({{ $currentHub->state->name ?? 'Akwa Ibom' }})',
                                            dest_id: '{{ $capitalLga->id ?? '' }}',
                                            dest_name: '{{ $st->name }} (Hub: {{ $capitalLga->name ?? 'Statewide' }})',
                                            base_fee: '{{ $stFee }}',
                                            bulky: '{{ $stBulky }}',
                                            min_eta: '{{ $stMinEta }}',
                                            max_eta: '{{ $stMaxEta }}',
                                            tier_label: 'Tier 3: Inter-State Freight',
                                            is_bidi: 0
                                        })">
                                    ⚙️ Rates
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- ===================================================================== -->
<!-- 5. QUICK MODAL FOR CORRIDOR RATE & ETA CONFIGURATION                  -->
<!-- ===================================================================== -->
<div id="vmCorridorModalOverlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1100; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #FFFFFF; border-radius: 14px; max-width: 520px; width: 100%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25); overflow: hidden; border: 1.5px solid #CBD5E1; animation: modalPop 0.2s ease-out;">
        <!-- Modal Header -->
        <div style="padding: 16px 20px; background: linear-gradient(135deg, #F8FAFC 0%, #EEF2FF 100%); border-bottom: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🛣️</span>
                <div>
                    <strong style="font-size: 14px; color: #0F172A;" id="modalTitle">Configure Delivery Corridor Rates</strong>
                    <div style="font-size: 11px; color: #64748B;" id="modalSubtitle">Custom pricing and ETA override</div>
                </div>
            </div>
            <button type="button" onclick="closeCorridorModal()" style="background: none; border: none; font-size: 18px; color: #64748B; cursor: pointer; padding: 4px;">✕</button>
        </div>

        <!-- Modal Form Body -->
        <form method="POST" action="{{ route('admin.delivery_lanes.store') }}" id="vmModalCorridorForm">
            @csrf
            <input type="hidden" name="lane_id" id="modalLaneId" value="">
            <input type="hidden" name="origin_lga_id" id="modalOriginLgaId" value="{{ $currentHub->id }}">
            <input type="hidden" name="destination_lga_id" id="modalDestLgaId" value="">
            <input type="hidden" name="hub_lga_id" value="{{ $currentHub->id }}">

            <div style="padding: 20px;">
                <!-- Route Topology Card -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;">
                    <div style="font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase;">DIRECTIONAL ROUTE</div>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 800; color: #0F172A; margin-top: 4px;">
                        <span id="modalOriginDisplay">{{ $currentHub->name }}</span>
                        <span style="color: #5E17EB; font-weight: 900;">➔</span>
                        <span id="modalDestDisplay">Destination</span>
                    </div>
                    <div style="margin-top: 6px;">
                        <span class="dash-badge dash-badge-info" id="modalTierBadge" style="font-size: 10px;">Regional Corridor</span>
                    </div>
                </div>

                <!-- Rates Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">BASE DELIVERY FEE (₦) *</label>
                        <input type="number" step="0.01" name="base_fee" id="modalBaseFee" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1.5px solid var(--adm-border); font-size: 13.5px; font-weight: 700; color: #047857; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">BULKY CARGO SURCHARGE (₦)</label>
                        <input type="number" step="0.01" name="bulky_surcharge" id="modalBulkyFee" style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1.5px solid var(--adm-border); font-size: 13px; color: #B45309; box-sizing: border-box;">
                    </div>
                </div>

                <!-- ETA Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">MIN ETA (HOURS) *</label>
                        <input type="number" name="estimated_delivery_hours_min" id="modalMinEta" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1.5px solid var(--adm-border); font-size: 13px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">MAX ETA (HOURS) *</label>
                        <input type="number" name="estimated_delivery_hours_max" id="modalMaxEta" required style="width: 100%; padding: 8px 10px; border-radius: 6px; border: 1.5px solid var(--adm-border); font-size: 13px; box-sizing: border-box;">
                    </div>
                </div>

                <!-- Bidirectional Checkbox -->
                <div style="background: rgba(94, 23, 235, 0.05); border: 1px dashed rgba(94, 23, 235, 0.3); border-radius: 6px; padding: 10px 12px; margin-bottom: 18px;" id="modalBidiWrapper">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--adm-primary); margin: 0;">
                        <input type="checkbox" name="is_bidirectional" id="modalIsBidi" value="1" style="accent-color: var(--adm-primary);">
                        <span>Sync Bidirectional Corridor (A ⇄ B)</span>
                    </label>
                    <div style="font-size: 10.5px; color: #64748B; margin-top: 4px; margin-left: 20px;">
                        When checked, simultaneously creates/updates the reverse return route with identical fee &amp; ETA.
                    </div>
                </div>

                <!-- Modal Actions -->
                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="closeCorridorModal()" style="padding: 8px 16px; font-size: 12.5px; background: #FFF;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 8px 20px; font-size: 12.5px; font-weight: 700;">
                        💾 Save Corridor Rates
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
