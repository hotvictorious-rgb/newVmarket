@extends('layouts.app')

@section('title', 'Pickup Reservations — Victorious MARKET')

@section('content')
<div class="container customer-profile-wrapper">
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--vm-text-muted); margin-bottom: 22px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-text-muted); text-decoration: none;">Storefront</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" style="color: var(--vm-text-muted); text-decoration: none;">Account</a>
        <span>/</span>
        <span style="color: var(--vm-primary); font-weight: 700;">Pickup Reservations</span>
    </div>

    <div class="profile-layout-grid">
        @include('customer.partials._sidebar')

        <main class="profile-canvas">
            <div class="page-header-row" style="margin-bottom: 20px;">
                <div>
                    <h1 class="page-header-title" style="font-size: 22px; font-weight: 800; color: var(--vm-dark); display: flex; align-items: center; gap: 10px;">
                        <span>🏬</span>
                        <span>In-Shop Pickup Reservations</span>
                    </h1>
                    <p style="font-size: 13.5px; color: var(--vm-text-muted); margin-top: 4px;">
                        Zero prepayment reservation passes. Inspect the product at the Plaza counter before paying.
                    </p>
                </div>
            </div>

            <div class="legacy-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 class="legacy-card-title" style="margin-bottom: 0;">Active Passes ({{ count($reservations) }})</h3>
                    <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
                        Walk-in Inspection
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--vm-border); text-align: left; font-size: 12px; color: var(--vm-text-muted); text-transform: uppercase;">
                                <th style="padding: 10px;">Pass Code</th>
                                <th style="padding: 10px;">Product Reserved</th>
                                <th style="padding: 10px;">Plaza Outlet</th>
                                <th style="padding: 10px;">Price</th>
                                <th style="padding: 10px;">Status</th>
                                <th style="padding: 10px;">Pass</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservations as $res)
                                <tr style="border-bottom: 1px solid var(--vm-border); font-size: 13.5px;">
                                    <td style="padding: 12px 10px;">
                                        <span class="badge" style="background: #FFFBEB; color: #B45309; font-size: 13px; font-weight: 800; letter-spacing: 0.5px; padding: 3px 8px; border-radius: 6px;">
                                            {{ $res->reservation_code }}
                                        </span>
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <strong style="color: var(--vm-dark);">{{ $res->product->name ?? 'Reserved Product' }}</strong>
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <div style="font-weight: 600;">{{ $res->shop->name ?? 'Victor Tech Plaza' }}</div>
                                        <div style="font-size: 11.5px; color: var(--vm-text-muted);">{{ $res->shop->address ?? 'Ibom Plaza, Uyo' }}</div>
                                    </td>
                                    <td style="padding: 12px 10px; font-weight: 800; color: #059669;">
                                        ₦{{ number_format($res->product->unit_price ?? 0, 2) }}
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        @if($res->status === 'inspected')
                                            <span class="badge" style="background: #ECFDF5; color: #047857; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                ✓ Inspected
                                            </span>
                                        @else
                                            <span class="badge" style="background: #EFF6FF; color: #1D4ED8; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                Pending Visit
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <a href="{{ route('reservation.pass', $res->reservation_code) }}" class="btn" style="padding: 4px 10px; font-size: 12px; font-weight: 700; background: var(--vm-gold); color: #0F172A; border-radius: 6px; text-decoration: none;">
                                            View Pass &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--vm-text-muted);">
                                        No active pickup reservations found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection