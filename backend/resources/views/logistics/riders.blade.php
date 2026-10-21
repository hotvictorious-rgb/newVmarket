@extends('layouts.dashboard')

@section('title', 'Rider Roster & Shifts — Logistics Fleet')
@section('role_title', 'Logistics Fleet Operations Center')
@section('page_title', 'Rider Roster & Shift Operations')
@section('role_badge', 'LOGISTICS HQ')
@section('role_badge_class', 'role-badge-logistics')

@section('sidebar_menu')
    @include('logistics.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('logistics.dashboard') }}">Logistics Fleet</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Rider Roster &amp; Shifts</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏍️</span>
                <span>Rider Roster &amp; Dispatch Fleet</span>
            </h1>
            <p class="page-header-subtitle">
                Manage your dispatch couriers, assigned vehicles, LGA delivery corridors, and duty readiness for Victorious MARKET fulfillment.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="openAddRiderModal()" class="btn-dash btn-dash-primary" style="font-size: 13px; padding: 7px 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span>➕</span>
                <span>Add Rider to Fleet</span>
            </button>
            <span class="dash-badge dash-badge-success" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeRiders ?? []) }} Active / {{ count($allRiders ?? $activeRiders) }} Total
            </span>
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

    @if($errors->any())
        <div style="margin-bottom: 20px; padding: 12px 18px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #B91C1C; font-weight: 600; font-size: 13px;">
            <strong>⚠️ Please check the form errors:</strong>
            <ul style="margin: 6px 0 0 20px; padding: 0;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Registered Fleet Couriers ({{ count($allRiders ?? $activeRiders) }})</div>
                <span class="dash-card-subtitle">Couriers authorized for directional LGA transit, counter pickups, and OTP doorstep handshakes</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Courier Name &amp; Contact</th>
                        <th>Vehicle &amp; License Plate</th>
                        <th>Assigned LGA Corridor</th>
                        <th>Duty Telemetry</th>
                        <th>Fleet Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allRiders ?? $activeRiders as $rider)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $rider->user->name ?? 'Fleet Rider' }}</div>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">📞 {{ $rider->user->phone ?? 'N/A' }}</span>
                                @if(!empty($rider->user->email))
                                    <div style="font-size: 11px; color: var(--adm-text-muted);">✉️ {{ $rider->user->email }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ ucfirst($rider->vehicle_type ?? 'Motorcycle') }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-primary); font-family: monospace; font-weight: 700;">{{ $rider->license_plate ?? 'PENDING' }}</span>
                                @if(!empty($rider->nin_number))
                                    <div style="font-size: 10.5px; color: var(--adm-text-muted);">NIN: {{ $rider->nin_number }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $rider->assignedLga->name ?? 'Uyo' }} LGA Corridor</span>
                            </td>
                            <td>
                                @if(!$rider->is_active)
                                    <span class="dash-badge dash-badge-danger">✕ DEACTIVATED</span>
                                @elseif($rider->is_online)
                                    <span class="dash-badge dash-badge-success">● ON DUTY</span>
                                @else
                                    <span class="dash-badge dash-badge-gold">○ STANDBY / OFF</span>
                                @endif
                            </td>
                            <td>
                                @if($rider->is_active)
                                    <span class="dash-badge dash-badge-success">Active Courier</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">Suspended</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('logistics.riders.toggle_active', $rider->id) }}" style="margin: 0;" onsubmit="return confirm('Toggle active status for courier {{ addslashes($rider->user->name ?? 'this rider') }}?');">
                                    @csrf
                                    @if($rider->is_active)
                                        <button type="submit" class="btn-dash btn-dash-danger" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700;">
                                            Deactivate
                                        </button>
                                    @else
                                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700;">
                                            Activate
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">🏍️</div>
                                <div style="font-weight: 600; font-size: 14px;">No couriers registered on your fleet roster yet.</div>
                                <div style="font-size: 12.5px; margin-top: 4px;">Click the "➕ Add Rider to Fleet" button above to add your first courier.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ADD RIDER MODAL -->
    <div id="addRiderModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: 12px; width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--adm-border); padding-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 22px;">🏍️</span>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: var(--adm-text);">Add Courier to Fleet</h3>
                </div>
                <button type="button" onclick="closeAddRiderModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('logistics.riders.store') }}" method="POST">
                @csrf

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Courier Full Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Samuel Okon" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Phone Number (Login) *</label>
                        <input type="text" name="phone" required placeholder="e.g. 08023456789" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Email Address (Optional)</label>
                        <input type="email" name="email" placeholder="e.g. samuel@gmail.com" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Temporary Password (Optional)</label>
                        <input type="text" name="password" placeholder="Leave blank to auto-generate" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Assigned LGA Corridor *</label>
                        <select name="assigned_lga_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->id }}" {{ strtolower($lga->name) === 'uyo' ? 'selected' : '' }}>
                                    {{ $lga->name }} LGA
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Vehicle Type *</label>
                        <select name="vehicle_type" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                            <option value="motorcycle" selected>🏍️ Motorcycle</option>
                            <option value="bicycle">🚲 Bicycle</option>
                            <option value="van">🚐 Delivery Van</option>
                            <option value="transit_waybill">📦 Transit Waybill</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">License Plate / Vehicle Reg *</label>
                        <input type="text" name="license_plate" required placeholder="e.g. UYO-456-AK" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px; text-transform: uppercase;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">NIN / National ID (Optional)</label>
                        <input type="text" name="nin_number" placeholder="11-digit NIN" maxlength="20" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeAddRiderModal()" class="btn-dash btn-dash-outline" style="padding: 8px 16px; font-weight: 700;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 8px 20px; font-weight: 700;">Add Courier</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddRiderModal() {
            document.getElementById('addRiderModal').style.display = 'flex';
        }
        function closeAddRiderModal() {
            document.getElementById('addRiderModal').style.display = 'none';
        }
    </script>
@endsection