@extends('layouts.dashboard')

@section('title', 'Physical Plaza Outlets & Delivery Coverage — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Physical Plaza Outlets & Delivery Coverage')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Physical Plaza Outlets</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>Physical Plaza Outlets &amp; Delivery Coverage</span>
            </h1>
            <p class="page-header-subtitle">
                Registered physical outlets with staffed counters. Each branch is mapped to an origin LGA; configure the specific LGAs and States your riders service.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-dash btn-dash-primary" onclick="document.getElementById('vmNewShopModal').style.display = 'flex';">
                + Register New Outlet
            </button>
        </div>
    </div>

    @if(session('status'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="dash-card" style="margin-bottom: 24px; border-left: 4px solid var(--adm-primary);">
        <div style="padding: 16px 20px; display: flex; align-items: flex-start; gap: 14px;">
            <span style="font-size: 24px;">⚖️</span>
            <div>
                <strong style="color: var(--adm-text); font-size: 14px;">Platform Delivery Rule</strong>
                <p style="font-size: 12.5px; color: var(--adm-text-muted); margin: 4px 0 0 0; line-height: 1.5;">
                    Standardized delivery fees are set centrally by <strong>Marketplace Admin</strong> to ensure fair, transparent pricing for shoppers. 
                    As a vendor, your physical shop is mapped to its municipal origin LGA. You select the <strong>individual LGAs and States</strong> your riders can physically cover. 
                    If a shopper's destination is outside your coverage, doorstep delivery fails closed (they can still reserve in-shop pickup).
                </p>
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Registered Plaza Outlets ({{ count($shops) }})</div>
                <span class="dash-card-subtitle">Branches enabled for customer walk-in inspection and rider dispatches</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Branch Name &amp; Plaza</th>
                        <th>Origin LGA</th>
                        <th>Delivery Coverage Areas</th>
                        <th>Inspection Terminal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shops as $shop)
                        @php
                            $activeCoverages = $shop->deliveryCoverages->where('is_active', true);
                            $coveredStateIds = $activeCoverages->whereNull('lga_id')->pluck('state_id')->toArray();
                            $coveredLgaIds = $activeCoverages->whereNotNull('lga_id')->pluck('lga_id')->toArray();
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $shop->name }}</div>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $shop->address }}</span>
                                @if($shop->phone)
                                    <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 2px;">📞 {{ $shop->phone }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info" style="font-weight: 700;">
                                    📍 {{ $shop->lga->name ?? 'Uyo' }} LGA ({{ $shop->state->name ?? 'Akwa Ibom' }})
                                </span>
                            </td>
                            <td>
                                @if($activeCoverages->isEmpty())
                                    <span class="dash-badge dash-badge-danger" style="font-size: 11.5px;">
                                        🚫 No Delivery Areas (In-Shop Pickup Only)
                                    </span>
                                @else
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px; max-width: 320px;">
                                        @foreach($activeCoverages->take(4) as $cov)
                                            @if($cov->lga)
                                                <span class="dash-badge dash-badge-success" style="font-size: 11px;">
                                                    ✓ {{ $cov->lga->name }}
                                                </span>
                                            @elseif($cov->state)
                                                <span class="dash-badge dash-badge-warning" style="font-size: 11px;">
                                                    🌐 All {{ $cov->state->name }}
                                                </span>
                                            @endif
                                        @endforeach
                                        @if($activeCoverages->count() > 4)
                                            <span class="dash-badge dash-badge-info" style="font-size: 11px;">
                                                +{{ $activeCoverages->count() - 4 }} more
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($shop->pickup_enabled)
                                    <span class="dash-badge dash-badge-success">● Counter Active</span>
                                @else
                                    <span class="dash-badge dash-badge-secondary">○ Disabled</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;" onclick="openCoverageModal({{ $shop->id }})">
                                    🗺️ Configure Coverage
                                </button>
                            </td>
                        </tr>

                        <!-- Modal for Coverage Management -->
                        <div id="vmCoverageModal-{{ $shop->id }}" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
                            <div style="background: white; border-radius: 14px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                                <div style="padding: 20px 24px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <h3 style="font-size: 16px; font-weight: 700; color: var(--adm-text); margin: 0;">
                                            Delivery Coverage — {{ $shop->name }}
                                        </h3>
                                        <span style="font-size: 12px; color: var(--adm-text-muted);">
                                            Origin: <strong>{{ $shop->lga->name ?? 'Uyo' }} LGA</strong>. Check the areas your riders can service.
                                        </span>
                                    </div>
                                    <button type="button" onclick="closeCoverageModal({{ $shop->id }})" style="border: none; background: none; font-size: 22px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
                                </div>

                                <form method="POST" action="{{ route('vendor.shops.coverage.update', $shop->id) }}" style="overflow-y: auto; padding: 20px 24px; flex: 1;">
                                    @csrf

                                    <div style="margin-bottom: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #475569;">
                                        💡 <strong>Tip:</strong> You can select individual LGAs where your riders travel, or select the entire state box to service all LGAs in that state. Standard Admin delivery rates apply.
                                    </div>

                                    @foreach($allStates as $st)
                                        @php
                                            $stateCovered = in_array($st->id, $coveredStateIds, true);
                                        @endphp
                                        <div style="border: 1px solid var(--adm-border); border-radius: 8px; margin-bottom: 14px; overflow: hidden;">
                                            <div style="background: #F1F5F9; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                                                <label style="font-weight: 700; font-size: 13px; color: var(--adm-text); cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                    <input type="checkbox" name="state_ids[]" value="{{ $st->id }}" {{ $stateCovered ? 'checked' : '' }} onchange="toggleStateLgas(this, {{ $shop->id }}, {{ $st->id }})">
                                                    <span>Cover Entire {{ $st->name }} State</span>
                                                </label>
                                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ count($st->lgas) }} LGAs</span>
                                            </div>

                                            <div id="lgaContainer-{{ $shop->id }}-{{ $st->id }}" style="padding: 12px 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; max-height: 180px; overflow-y: auto;">
                                                @foreach($st->lgas as $lga)
                                                    @php
                                                        $lgaCovered = $stateCovered || in_array($lga->id, $coveredLgaIds, true);
                                                    @endphp
                                                    <label style="font-size: 12px; color: var(--adm-text); display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 6px; border-radius: 4px; background: #FFF;">
                                                        <input type="checkbox" class="lga-checkbox-{{ $shop->id }}-{{ $st->id }}" name="lga_ids[]" value="{{ $lga->id }}" {{ $lgaCovered ? 'checked' : '' }} {{ $stateCovered ? 'disabled' : '' }}>
                                                        <span>{{ $lga->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach

                                    <div style="padding-top: 14px; border-top: 1px solid var(--adm-border); display: flex; justify-content: flex-end; gap: 10px;">
                                        <button type="button" class="btn-dash btn-dash-outline" onclick="closeCoverageModal({{ $shop->id }})">Cancel</button>
                                        <button type="submit" class="btn-dash btn-dash-primary">Save Delivery Coverage</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No physical branches registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for New Shop -->
    <div id="vmNewShopModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 17px; font-weight: 700; color: var(--adm-text); margin: 0;">Register New Plaza Outlet</h3>
                <button type="button" onclick="document.getElementById('vmNewShopModal').style.display = 'none';" style="border: none; background: none; font-size: 18px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('vendor.shops.store') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Branch / Plaza Name</label>
                    <input type="text" name="name" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="e.g. Victor Tech Ibom Plaza Outlet">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Physical Street Address</label>
                    <input type="text" name="address" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="e.g. Shop 14, Ibom Plaza Commercial Center">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Contact Phone</label>
                        <input type="text" name="phone" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="08012345678">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Origin LGA (Branch Location)</label>
                        <select name="lga_id" class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;">
                            @foreach($allLgas as $lga)
                                <option value="{{ $lga->id }}">{{ $lga->name }} ({{ $lga->state->name ?? 'Akwa Ibom' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--adm-text); cursor: pointer;">
                        <input type="checkbox" name="pickup_enabled" value="1" checked>
                        <span>Enable customer in-shop pickup &amp; counter inspection at this outlet</span>
                    </label>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="document.getElementById('vmNewShopModal').style.display = 'none';">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary">Register Outlet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCoverageModal(shopId) {
            const modal = document.getElementById('vmCoverageModal-' + shopId);
            if (modal) modal.style.display = 'flex';
        }

        function closeCoverageModal(shopId) {
            const modal = document.getElementById('vmCoverageModal-' + shopId);
            if (modal) modal.style.display = 'none';
        }

        function toggleStateLgas(stateCheckbox, shopId, stateId) {
            const lgaCheckboxes = document.querySelectorAll('.lga-checkbox-' + shopId + '-' + stateId);
            lgaCheckboxes.forEach(cb => {
                if (stateCheckbox.checked) {
                    cb.checked = true;
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                }
            });
        }
    </script>
@endsection