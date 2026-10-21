@extends('layouts.dashboard')

@section('title', 'Logistics Companies & Fleet Governance Desk')
@section('role_title', 'Super Admin')
@section('page_title', 'Logistics Companies Desk')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Breadcrumb Trail -->
    <div class="breadcrumb-trail">
        <a href="{{ route('admin.dashboard') }}">Command Tower</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Logistics Companies Desk</span>
    </div>

    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🚚</span>
                <span>Logistics Companies &amp; Fleet Governance Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Accredit, onboard, and govern third-party delivery companies. Only Super Admin can onboard logistics companies; accredited companies manage their own riders.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="openAddCompanyModal()" class="btn-dash btn-dash-primary" style="font-size: 13px; padding: 7px 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span>➕</span>
                <span>Onboard Logistics Company</span>
            </button>
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Fleet Gatekeeper
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10B981; color: #10B981; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #EF4444; color: #EF4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
            <strong>⚠️ Please correct the following errors:</strong>
            <ul style="margin: 6px 0 0 20px; padding: 0;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 4 Metric Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accredited Fleets</span>
                <span class="stat-card-value">{{ $companies->where('status', 'active')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Active Logistics Companies</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">🚚</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Registered</span>
                <span class="stat-card-value">{{ $companies->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">All-Time Partners</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏢</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Registered Couriers</span>
                <span class="stat-card-value">{{ $totalRiders }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Fleet Dispatch Riders</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">🏍️</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Couriers</span>
                <span class="stat-card-value">{{ $activeRiders }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Cleared for Doorstep Runs</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⚡</div>
        </div>
    </div>

    <!-- Logistics Companies Table Card -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🚚</span>
                <span>Accredited Logistics Partners ({{ $companies->count() }})</span>
            </div>
            <span class="dash-card-subtitle">
                Third-party delivery operators authorized for Uyo intra-LGA and Akwa Ibom statewide fulfillment.
            </span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Company Details</th>
                        <th>Contact Representative</th>
                        <th>HQ Corridor &amp; Address</th>
                        <th>Fleet Size &amp; Riders</th>
                        <th>Bank Settlement NUBAN</th>
                        <th>Accreditation Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">
                                    {{ $company->company_name }}
                                </div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted); font-family: monospace;">
                                    Reg: {{ $company->registration_number ?? 'RC-PENDING' }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">
                                    {{ $company->user->name ?? 'Contact Person' }}
                                </div>
                                <div style="font-size: 12px; color: var(--adm-text-muted);">
                                    📞 {{ $company->contact_phone ?? ($company->user->phone ?? 'N/A') }}
                                </div>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">
                                    ✉️ {{ $company->contact_email ?? ($company->user->email ?? 'N/A') }}
                                </div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info" style="font-size: 11.5px;">
                                    {{ $company->hqLga->name ?? 'Uyo' }} LGA
                                </span>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted); margin-top: 4px; max-width: 220px; line-height: 1.3;">
                                    {{ $company->hq_address ?? 'Akwa Ibom HQ' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 13px; color: var(--adm-text);">
                                    {{ $company->riders->count() }} Couriers
                                </div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">
                                    Cap: {{ $company->fleet_size }} vehicles
                                </span>
                            </td>
                            <td>
                                @if(!empty($company->bank_name) && !empty($company->account_number))
                                    <div style="font-weight: 600; font-size: 12.5px; color: var(--adm-text);">
                                        {{ $company->bank_name }}
                                    </div>
                                    <div style="font-family: monospace; font-size: 12px; color: var(--adm-primary); font-weight: 700;">
                                        {{ $company->account_number }}
                                    </div>
                                    <div style="font-size: 10.5px; color: var(--adm-text-muted);">
                                        {{ $company->account_name }}
                                    </div>
                                @else
                                    <span style="font-size: 11.5px; color: var(--adm-text-muted); font-style: italic;">
                                        No bank linked
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($company->status === 'active')
                                    <span class="dash-badge dash-badge-success">● ACCREDITED</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">✕ SUSPENDED</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.logistics_companies.toggle_status', $company->id) }}" style="margin: 0;" onsubmit="return confirm('Toggle accreditation status for {{ addslashes($company->company_name) }}?');">
                                    @csrf
                                    @if($company->status === 'active')
                                        <button type="submit" class="btn-dash btn-dash-danger" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700;">
                                            Suspend
                                        </button>
                                    @else
                                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700;">
                                            Reactivate
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px; color: var(--adm-text-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">🚚</div>
                                <div style="font-weight: 600; font-size: 14px;">No logistics companies onboarded yet.</div>
                                <div style="font-size: 12.5px; margin-top: 4px;">Click the "➕ Onboard Logistics Company" button above to accredit your first fleet partner.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ONBOARD LOGISTICS COMPANY MODAL -->
    <div id="addCompanyModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-surface); border: 1px solid var(--adm-border); border-radius: 12px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--adm-border); padding-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 22px;">🚚</span>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: var(--adm-text);">Onboard New Logistics Company</h3>
                </div>
                <button type="button" onclick="closeAddCompanyModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--adm-text-muted);">&times;</button>
            </div>

            <form action="{{ route('admin.logistics_companies.store') }}" method="POST">
                @csrf

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Company Name *</label>
                        <input type="text" name="company_name" required placeholder="e.g. Apex Express Couriers" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Contact Person Name *</label>
                        <input type="text" name="contact_name" required placeholder="e.g. Edidiong Bassey" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Contact Phone (Login) *</label>
                        <input type="text" name="phone" required placeholder="e.g. 08012345678" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Contact Email (Optional)</label>
                        <input type="email" name="email" placeholder="e.g. fleet@apexexpress.ng" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Initial Password (Optional)</label>
                        <input type="text" name="password" placeholder="Leave blank to auto-generate" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">CAC / Reg Number (Optional)</label>
                        <input type="text" name="registration_number" placeholder="e.g. RC 1234567" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">HQ LGA Hub Corridor *</label>
                        <select name="lga_id" required style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->id }}" {{ strtolower($lga->name) === 'uyo' ? 'selected' : '' }}>
                                    {{ $lga->name }} LGA
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">Authorized Fleet Capacity</label>
                        <input type="number" name="fleet_size" value="10" min="1" max="500" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;">
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--adm-text);">HQ Physical Office Address *</label>
                    <textarea name="hq_address" required rows="2" placeholder="e.g. Suite 4, Oron Road, opposite Plaza, Uyo, Akwa Ibom State" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); color: var(--adm-text); font-size: 13px;"></textarea>
                </div>

                <div style="background: rgba(94, 23, 235, 0.05); border: 1px solid rgba(94, 23, 235, 0.2); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--adm-primary); margin-bottom: 8px;">🏦 Settlement Bank Details (For Delivery Disbursements)</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <input type="text" name="bank_name" placeholder="Bank Name (e.g. Zenith Bank)" style="width: 100%; padding: 7px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-surface); color: var(--adm-text); font-size: 12px;">
                        </div>
                        <div>
                            <input type="text" name="account_number" placeholder="10-Digit NUBAN Account Number" maxlength="10" style="width: 100%; padding: 7px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-surface); color: var(--adm-text); font-size: 12px;">
                        </div>
                    </div>
                    <div style="margin-top: 8px;">
                        <input type="text" name="account_name" placeholder="Bank Account Name (as on bank statement)" style="width: 100%; padding: 7px 10px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-surface); color: var(--adm-text); font-size: 12px;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeAddCompanyModal()" class="btn-dash btn-dash-outline" style="padding: 8px 16px; font-weight: 700;">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 8px 20px; font-weight: 700;">Onboard &amp; Accredit Fleet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddCompanyModal() {
            document.getElementById('addCompanyModal').style.display = 'flex';
        }
        function closeAddCompanyModal() {
            document.getElementById('addCompanyModal').style.display = 'none';
        }
    </script>
@endsection
