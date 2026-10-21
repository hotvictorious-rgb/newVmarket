@extends('layouts.dashboard')

@section('title', 'Merchant Accreditation & KYC Governance Desk')
@section('role_title', 'Super Admin')
@section('page_title', 'Merchant Accreditation Desk')
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
                <span style="font-size: 26px;">🏪</span>
                <span>Merchant Accreditation &amp; KYC Governance Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Examine legal CAC/NIN verification, physical store inspection, and disburse permissions to sell on Victorious MARKET.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="openAddSupplierModal()" class="btn-dash btn-dash-primary" style="font-size: 13px; padding: 7px 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span>➕</span>
                <span>Onboard New Supplier</span>
            </button>
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <span class="dash-badge dash-badge-purple" style="padding: 6px 12px; font-size: 12px;">
                🛡️ Sovereign Quality Gate
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

    <!-- 4 KYC Metric Cards -->
    <div class="stat-grid-4" style="margin-bottom: 24px;">
        <div class="stat-card stat-card-border-emerald">
            <div class="stat-card-info">
                <span class="stat-card-label">Accredited Active</span>
                <span class="stat-card-value">{{ $allVendors->where('kyc_status', 'verified')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-success);">Certified Quality Vendors</span>
            </div>
            <div class="stat-card-icon-box icon-box-emerald">✅</div>
        </div>

        <div class="stat-card stat-card-border-amber">
            <div class="stat-card-info">
                <span class="stat-card-label">Pending Verification</span>
                <span class="stat-card-value">{{ $allVendors->where('kyc_status', '!=', 'verified')->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-warning);">Awaiting Document Review</span>
            </div>
            <div class="stat-card-icon-box icon-box-amber">⏳</div>
        </div>

        <div class="stat-card stat-card-border-purple">
            <div class="stat-card-info">
                <span class="stat-card-label">Total Registered</span>
                <span class="stat-card-value">{{ $allVendors->count() }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-primary);">Ecosystem Merchants</span>
            </div>
            <div class="stat-card-icon-box icon-box-purple">🏬</div>
        </div>

        <div class="stat-card stat-card-border-blue">
            <div class="stat-card-info">
                <span class="stat-card-label">Active Shop Counters</span>
                <span class="stat-card-value">{{ $allVendors->sum(fn($v) => $v->shops->count()) }}</span>
                <span class="stat-card-subtext" style="color: var(--adm-info);">Uyo &amp; LGA Physical Plazas</span>
            </div>
            <div class="stat-card-icon-box icon-box-blue">📍</div>
        </div>
    </div>

    <!-- Merchant Accreditation Table Card -->
    <div class="dash-card" id="merchants-desk">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span>🏛️</span>
                <div>
                    <div>Accredited Merchant KYC &amp; Banking Desk</div>
                    <span class="dash-card-subtitle">Examine legal CAC/NIN credentials, verify physical plaza branches, and manage marketplace listing status.</span>
                </div>
            </div>
            <span class="dash-badge dash-badge-purple">Sovereign Quality Gate</span>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Merchant / Business</th>
                        <th>CAC Number</th>
                        <th>Director NIN</th>
                        <th>Settlement Bank</th>
                        <th>Branches</th>
                        <th>KYC Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allVendors ?? [] as $v)
                        <tr>
                            <td>
                                <strong style="font-size: 13.5px; color: var(--adm-text);">{{ $v->business_name }}</strong>
                                <div style="font-size: 11.5px; color: var(--adm-text-muted);">Owner: {{ $v->user->name ?? 'N/A' }} ({{ $v->user->phone ?? 'N/A' }})</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $v->user->email ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $v->cac_number ?? 'Pending CAC' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-purple">{{ $v->nin_number ?? 'Pending NIN' }}</span>
                            </td>
                            <td>
                                <div><strong>{{ $v->bank_name ?? 'Zenith Bank' }}</strong></div>
                                <div style="font-family: monospace; font-size: 12px; color: var(--adm-text-muted);">{{ $v->account_number ?? '1012345678' }}</div>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-gold" style="font-size: 11px;">
                                    🏬 {{ $v->shops->count() }} Shops
                                </span>
                            </td>
                            <td>
                                @if($v->kyc_status === 'verified')
                                    <span class="dash-badge dash-badge-success">● Accredited Active</span>
                                @else
                                    <span class="dash-badge dash-badge-danger">● {{ ucfirst($v->kyc_status ?? 'Pending') }}</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.vendors.accredit', $v->id) }}" style="display: flex; gap: 6px; margin: 0;">
                                    @csrf
                                    @if($v->kyc_status === 'verified')
                                        <button type="submit" name="action" value="reject" class="btn-dash btn-dash-danger" style="padding: 4px 10px; font-size: 11px;" title="Suspend Merchant">
                                            Suspend
                                        </button>
                                    @else
                                        <button type="submit" name="action" value="verify" class="btn-dash btn-dash-success" style="padding: 4px 10px; font-size: 11px;" title="Approve KYC">
                                            ✓ Approve KYC
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--adm-text-muted);">
                                No merchants registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sovereign Supplier Onboarding Modal -->
    <div id="addSupplierModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--adm-card, #1E293B); border: 1px solid var(--adm-border, rgba(255, 255, 255, 0.12)); border-radius: 14px; width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
            <!-- Modal Header -->
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--adm-border, rgba(255, 255, 255, 0.1)); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">🏪</span>
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #FFFFFF;">Onboard New Private Supplier</h3>
                        <span style="font-size: 11.5px; color: var(--adm-text-muted, #94A3B8);">Register a wholesale supplier and their physical shop counter in Uyo</span>
                    </div>
                </div>
                <button type="button" onclick="closeAddSupplierModal()" style="background: transparent; border: none; color: #94A3B8; font-size: 22px; cursor: pointer; padding: 4px;">&times;</button>
            </div>

            <!-- Modal Form -->
            <form method="POST" action="{{ route('admin.merchants.store') }}" style="padding: 22px 24px; margin: 0;">
                @csrf
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #CBD5E1; margin-bottom: 6px;">Supplier / Business Name *</label>
                        <input type="text" name="business_name" required placeholder="e.g. Emeka Electronics Plaza" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 10px 12px; color: #FFFFFF; font-size: 13px;" value="{{ old('business_name') }}">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #CBD5E1; margin-bottom: 6px;">Contact Person Name *</label>
                        <input type="text" name="contact_name" required placeholder="e.g. Chief Emeka Okonkwo" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 10px 12px; color: #FFFFFF; font-size: 13px;" value="{{ old('contact_name') }}">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #CBD5E1; margin-bottom: 6px;">Phone Number (Primary Login/Contact) *</label>
                        <input type="tel" name="phone" required placeholder="e.g. 08012345678" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 10px 12px; color: #FFFFFF; font-size: 13px;" value="{{ old('phone') }}">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #CBD5E1; margin-bottom: 6px;">Email Address (Optional)</label>
                        <input type="email" name="email" placeholder="e.g. supplier@gmail.com" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 10px 12px; color: #FFFFFF; font-size: 13px;" value="{{ old('email') }}">
                    </div>
                </div>

                <div style="background: rgba(255, 215, 0, 0.05); border: 1px dashed rgba(255, 215, 0, 0.3); border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                    <h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #FFD700; display: flex; align-items: center; gap: 6px;">
                        <span>📍</span> Primary Physical Shop Counter (Uyo / Akwa Ibom)
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Shop Counter Name *</label>
                            <input type="text" name="shop_name" required placeholder="e.g. Plaza Main Branch" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;" value="{{ old('shop_name', 'Main Plaza Branch') }}">
                        </div>
                        <div>
                            <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Local Government Area *</label>
                            <select name="lga_id" required style="width: 100%; background: #0F172A; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;">
                                @foreach($lgas ?? [] as $lga)
                                    <option value="{{ $lga->id }}" {{ (old('lga_id') == $lga->id || $lga->name === 'Uyo') ? 'selected' : '' }}>
                                        {{ $lga->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Physical Address in Uyo *</label>
                        <input type="text" name="shop_address" required placeholder="e.g. Shop 14, Plaza Line, Ikot Ekpene Road, Uyo" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;" value="{{ old('shop_address') }}">
                    </div>
                </div>

                <div style="background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #10B981; display: flex; align-items: center; gap: 6px;">
                        <span>🏦</span> Wholesale Settlement Bank Account
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g. Zenith Bank, GTBank, OPay" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;" value="{{ old('bank_name') }}">
                        </div>
                        <div>
                            <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Account Number (10 Digits)</label>
                            <input type="text" name="account_number" maxlength="10" placeholder="e.g. 1012345678" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;" value="{{ old('account_number') }}">
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #CBD5E1; margin-bottom: 4px;">Account Name</label>
                        <input type="text" name="account_name" placeholder="e.g. Emeka Electronics Ltd" style="width: 100%; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; color: #FFFFFF; font-size: 12.5px;" value="{{ old('account_name') }}">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeAddSupplierModal()" class="btn-dash btn-dash-outline" style="padding: 10px 18px; font-size: 13px; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 10px 22px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <span>✓</span>
                        <span>Complete Supplier Onboarding</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddSupplierModal() {
            const modal = document.getElementById('addSupplierModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }
        function closeAddSupplierModal() {
            const modal = document.getElementById('addSupplierModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        // Close on background click
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('addSupplierModal');
            if (e.target === modal) {
                closeAddSupplierModal();
            }
        });
        // Auto open if validation errors occurred on this form
        @if($errors->any())
            openAddSupplierModal();
        @endif
    </script>
@endsection