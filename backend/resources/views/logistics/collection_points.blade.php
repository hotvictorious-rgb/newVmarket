@extends('layouts.dashboard')

@section('title', 'LGA Collection Points — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'LGA Collection Points')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">LGA Collection Points</span>
    </div>

    @if(session('status'))
        <div style="background: #ECFDF5; border: 1.5px solid #10B981; color: #065F46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div style="background: #FFFBEB; border: 1.5px solid #F59E0B; color: #92400E; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">⚠️</span>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📍</span>
                <span>LGA Collection Points (Designated Pickup Stations)</span>
            </h1>
            <p class="page-header-subtitle">
                Accredited physical pickup stations set per LGA where customers inspect and claim Inter-LGA and Inter-State deliveries using their secret 6-digit delivery PIN.
            </p>
        </div>
        <div>
            <button type="button" class="btn-dash btn-dash-primary" onclick="toggleAddModal()" style="display: flex; align-items: center; gap: 8px; font-weight: 800; padding: 10px 18px;">
                <span>➕</span>
                <span>Register Collection Point</span>
            </button>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="stat-grid-4">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Pickup Stations</span>
                <span class="stat-card-value">{{ $metrics['active_points'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Ready for customer pickup</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">📍</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Covered LGAs</span>
                <span class="stat-card-value">{{ $metrics['covered_lgas'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Across Nigeria</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🗺️</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Registered Stations</span>
                <span class="stat-card-value">{{ $metrics['total_points'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Corporate stations roster</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏢</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Handled Pickups</span>
                <span class="stat-card-value">{{ $metrics['total_orders_handled'] }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">PIN Verified Releases</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">📦</div>
        </div>
    </div>

    <!-- Collection Points Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Configured LGA Collection Points ({{ count($collectionPoints) }})</div>
                <span class="dash-card-subtitle">Active stations displayed to shoppers during Inter-LGA and Inter-State checkout</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Station Name &amp; Code</th>
                        <th>LGA &amp; State</th>
                        <th>Physical Address &amp; Landmark</th>
                        <th>Station Contact &amp; Hours</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($collectionPoints as $cp)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $cp->name }}</div>
                                <span class="dash-badge dash-badge-purple" style="font-size: 10.5px; font-family: monospace; font-weight: 800;">
                                    {{ $cp->code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-primary);">{{ $cp->lga->name ?? 'LGA' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $cp->state->name ?? 'State' }}</span>
                            </td>
                            <td>
                                <div style="font-size: 12.5px; color: var(--adm-text);">{{ $cp->address }}</div>
                                @if($cp->landmark)
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">📍 Landmark: {{ $cp->landmark }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text); font-size: 12px;">📞 {{ $cp->contact_phone }}</div>
                                @if($cp->contact_person)
                                    <span style="font-size: 11px; color: var(--adm-text-muted);">Attn: {{ $cp->contact_person }}</span><br>
                                @endif
                                <span style="font-size: 10.5px; color: #64748B;">🕒 {{ $cp->operating_hours }}</span>
                            </td>
                            <td>
                                @if($cp->is_active)
                                    <span class="dash-badge dash-badge-success">● Active (Open)</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">○ Inactive</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
                                    <form method="POST" action="{{ route('logistics.collection_points.toggle', $cp->id) }}" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn-dash {{ $cp->is_active ? 'btn-dash-warning' : 'btn-dash-success' }}" style="padding: 3px 8px; font-size: 11px; font-weight: 700;">
                                            {{ $cp->is_active ? 'Pause' : 'Activate' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('logistics.collection_points.delete', $cp->id) }}" style="margin: 0;" onsubmit="return confirm('Are you sure you want to remove this collection point?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-dash btn-dash-danger" style="padding: 3px 8px; font-size: 11px; font-weight: 700;">
                                            ✕
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">📍</div>
                                <div style="font-weight: 700; color: var(--adm-text);">No LGA Collection Points configured yet</div>
                                <div style="font-size: 12px; margin-top: 4px;">Click the button above to register your first station per Local Government Area.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Registration Modal / Drawer Backdrop -->
    <div id="addPointModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #FFFFFF; border-radius: 14px; max-width: 580px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden; animation: modalPop 0.2s ease-out;">
            <div style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); color: #FFF; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📍</span>
                    <div>
                        <div style="font-weight: 800; font-size: 16px;">Register LGA Collection Point</div>
                        <div style="font-size: 11.5px; opacity: 0.85;">Designate an accredited pickup station for an LGA</div>
                    </div>
                </div>
                <button type="button" onclick="toggleAddModal()" style="background: none; border: none; color: #FFF; font-size: 20px; cursor: pointer; line-height: 1;">✕</button>
            </div>

            <form method="POST" action="{{ route('logistics.collection_points.store') }}" style="padding: 24px;">
                @csrf

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        TARGET LGA &amp; STATE <span style="color: #DC2626;">*</span>
                    </label>
                    <select name="lga_id" required style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; font-weight: 600; color: #0F172A; outline: none; background: #FFF;">
                        <option value="">-- Choose LGA --</option>
                        @foreach($lgas as $lga)
                            <option value="{{ $lga->id }}">
                                {{ $lga->name }} — {{ $lga->state->name ?? 'Nigeria' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        STATION / COLLECTION POINT NAME <span style="color: #DC2626;">*</span>
                    </label>
                    <input type="text" name="name" required placeholder="e.g. Peace Mass Transit Terminal, Ikot Ekpene Plaza" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; font-weight: 600; color: #0F172A; outline: none; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        PHYSICAL STATION ADDRESS <span style="color: #DC2626;">*</span>
                    </label>
                    <input type="text" name="address" required placeholder="e.g. 14 Aba Road, Opposite Central Motor Park" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; font-weight: 600; color: #0F172A; outline: none; box-sizing: border-box;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            NOTABLE LANDMARK
                        </label>
                        <input type="text" name="landmark" placeholder="e.g. Beside Zenith Bank" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            STATION PHONE NUMBER <span style="color: #DC2626;">*</span>
                        </label>
                        <input type="text" name="contact_phone" required placeholder="e.g. 08033330001" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            STATION SUPERVISOR
                        </label>
                        <input type="text" name="contact_person" placeholder="e.g. Michael Akpan" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            OPERATING HOURS
                        </label>
                        <input type="text" name="operating_hours" value="08:00 AM - 06:00 PM" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid #CBD5E1; font-size: 13px; outline: none; box-sizing: border-box;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #E2E8F0; padding-top: 18px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="toggleAddModal()" style="padding: 9px 16px; font-weight: 700;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 9px 22px; font-weight: 800;">
                        Save Collection Point
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleAddModal() {
            const modal = document.getElementById('addPointModal');
            if (modal.style.display === 'none' || modal.style.display === '') {
                modal.style.display = 'flex';
            } else {
                modal.style.display = 'none';
            }
        }
    </script>
    @endpush
@endsection
