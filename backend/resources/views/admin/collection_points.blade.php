@extends('layouts.dashboard')

@section('title', 'Collection Points & Returns Hubs — Super Admin')
@section('role_title', 'Super Admin Command Tower')
@section('page_title', 'Collection Points & Returns Hub Governance')
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
                <span style="font-size: 26px;">📍</span>
                <span>Collection Points &amp; Returns Hub Governance</span>
            </h1>
            <p class="page-header-subtitle">
                Super Admin administrative oversight of physical collection points and return drop-off hubs across Akwa Ibom State.
                Returns undergo physical custody and QA inspection here before any manual refund is released.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.returns.index') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                🔄 Returns Desk
            </a>
            <button onclick="document.getElementById('createPointModal').style.display='flex'" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 14px;">
                + Register Collection Point
            </button>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981; color: #047857; font-weight: 600; font-size: 13px;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Collection Hubs</span>
                <span class="stat-card-value">{{ $metrics['total_points'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Statewide Network</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">📍</div>
        </div>

        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Drop-off Points</span>
                <span class="stat-card-value">{{ $metrics['active_points'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Ready for Custody &amp; QA</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">✅</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Covered LGAs</span>
                <span class="stat-card-value">{{ $metrics['covered_lgas'] ?? 0 }} / 31</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Akwa Ibom Jurisdictions</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🗺️</div>
        </div>

        <div class="stat-card stat-card-border-gold">
            <div class="stat-card-info">
                <span class="stat-card-label">Returns Processed</span>
                <span class="stat-card-value">{{ $metrics['total_returns_handled'] ?? 0 }}</span>
                <span class="stat-card-subtext" style="color: #B45309;">Physical QA Audits</span>
            </div>
            <div class="stat-card-icon-box icon-box-gold">📦</div>
        </div>
    </div>

    <!-- Collection Points Table Card -->
    <div class="dash-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--adm-text);">Designated Return Drop-off &amp; Collection Points</h3>
                <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--adm-text-muted);">
                    Customers return items to these verified physical hubs within the 24-hour return window.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Code &amp; Name</th>
                        <th>Fleet Partner</th>
                        <th>LGA &amp; State</th>
                        <th>Physical Address</th>
                        <th>Contact / Phone</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($collectionPoints as $point)
                        <tr>
                            <td>
                                <strong style="color: var(--adm-text); font-size: 13.5px;">{{ $point->name }}</strong>
                                <div style="font-size: 11px; font-family: monospace; color: var(--adm-primary); font-weight: 700;">
                                    {{ $point->code }}
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 12.5px; color: var(--adm-text);">
                                    {{ $point->logisticsCompany->company_name ?? 'Victorious Fleet Hub' }}
                                </span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info" style="font-size: 11px;">
                                    {{ $point->lga->name ?? 'Uyo' }}, {{ $point->state->name ?? 'Akwa Ibom' }}
                                </span>
                            </td>
                            <td style="max-width: 220px; font-size: 12px; color: var(--adm-text-muted);">
                                {{ $point->address }}
                                @if($point->landmark)
                                    <div style="font-size: 11px; color: var(--adm-primary);">Near: {{ $point->landmark }}</div>
                                @endif
                            </td>
                            <td style="font-size: 12px;">
                                <div>📞 {{ $point->contact_phone }}</div>
                                @if($point->contact_person)
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">👤 {{ $point->contact_person }}</div>
                                @endif
                            </td>
                            <td style="font-size: 12px; color: var(--adm-text-muted);">
                                {{ $point->operating_hours ?? '08:00 AM - 06:00 PM' }}
                            </td>
                            <td>
                                @if($point->is_active)
                                    <span class="dash-badge dash-badge-success" style="font-size: 11px;">Active</span>
                                @else
                                    <span class="dash-badge dash-badge-neutral" style="font-size: 11px;">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <form action="{{ route('admin.collection_points.toggle', $point->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 8px;">
                                            {{ $point->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.collection_points.destroy', $point->id) }}" method="POST" onsubmit="return confirm('Delete this collection point?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-dash" style="font-size: 11px; padding: 4px 8px; background: rgba(239, 68, 68, 0.1); color: #DC2626; border: 1px solid rgba(239, 68, 68, 0.3);">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 32px; color: var(--adm-text-muted);">
                                No collection points registered yet. Click "+ Register Collection Point" above to establish return drop-off hubs.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Registration Modal -->
    <div id="createPointModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card-bg, #FFFFFF); border-radius: 12px; max-width: 580px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--adm-border); overflow: hidden;">
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--adm-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--adm-text);">Register New Return Collection Point</h3>
                <button type="button" onclick="document.getElementById('createPointModal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>
            <form action="{{ route('admin.collection_points.store') }}" method="POST" style="padding: 24px;">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Fleet Partner *</label>
                        <select name="logistics_company_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                            <option value="">Select Logistics Company</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">LGA Jurisdiction *</label>
                        <select name="lga_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                            <option value="">Select LGA</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->id }}">{{ $lga->name }} ({{ $lga->state->name ?? 'Akwa Ibom' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Collection Point Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Uyo Central Plaza Hub" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Physical Address *</label>
                    <input type="text" name="address" required placeholder="e.g. 14 Ikot Ekpene Road, Plaza Roundabout" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Landmark (Optional)</label>
                        <input type="text" name="landmark" placeholder="e.g. Opposite Ibom Plaza Fountain" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Operating Hours</label>
                        <input type="text" name="operating_hours" value="08:00 AM - 06:00 PM" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Contact Phone *</label>
                        <input type="text" name="contact_phone" required placeholder="08012345678" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--adm-text); margin-bottom: 4px;">Contact Officer (Optional)</label>
                        <input type="text" name="contact_person" placeholder="e.g. Aniefiok Bassey" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); font-size: 13px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('createPointModal').style.display='none'" class="btn-dash btn-dash-outline" style="font-size: 13px; padding: 8px 16px;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="font-size: 13px; padding: 8px 18px;">
                        Save Collection Point
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
