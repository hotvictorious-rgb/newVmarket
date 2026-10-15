@extends('layouts.dashboard')

@section('title', 'Physical Inspection Desk — Counter Staff')
@section('role_title', 'Counter Staff')
@section('page_title', 'Physical Inspection Sign-Off Desk')
@section('role_badge', 'COUNTER STAFF')
@section('role_badge_class', 'role-badge-counter')

@section('sidebar_menu')
    @include('counter.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('counter.dashboard') }}">Counter Terminal</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Physical Inspection Desk</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🔎</span>
                <span>Physical Inspection Sign-Off Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Mandatory physical examination with the shopper at the counter before escrow release. Examine the reserved item and verify customer satisfaction.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($pendingInspections) }} Pending Walk-in Inspections
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="dash-card" style="border-top: 3.5px solid var(--adm-gold);">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Pending In-Shop Customer Reservations</div>
                <span class="dash-card-subtitle">Verify reservation code with shopper to authorize physical product inspection</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Pass Code</th>
                        <th>Product &amp; Unit Value</th>
                        <th>Customer Details</th>
                        <th>Branch Plaza</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingInspections as $res)
                        <tr>
                            <td>
                                <span class="dash-badge dash-badge-gold" style="font-size: 13px; font-weight: 800; letter-spacing: 0.5px;">
                                    {{ $res->reservation_code }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text);">{{ $res->product->name ?? 'Reserved Item' }}</div>
                                <span style="font-size: 12px; font-weight: 800; color: #059669;">₦{{ number_format($res->product->unit_price ?? 0, 2) }}</span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--adm-text);">{{ $res->customer->name ?? 'Shopper' }}</div>
                                <span style="font-size: 11.5px; color: var(--adm-text-muted);">{{ $res->customer->phone ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $res->shop->name ?? 'Plaza Counter' }}</span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('vendor.approve.inspection') }}" style="display: flex; gap: 6px; align-items: center;">
                                    @csrf
                                    <input type="hidden" name="reservation_code" value="{{ $res->reservation_code }}">
                                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                        ✅ Sign-Off Passed
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No pending customer reservations awaiting counter inspection.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection