@extends('layouts.app')

@section('title', '5% Sovereign Cashback Rewards Wallet — Victorious MARKET')

@section('content')
<div class="container customer-profile-wrapper">
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--vm-text-muted); margin-bottom: 22px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-text-muted); text-decoration: none;">Storefront</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" style="color: var(--vm-text-muted); text-decoration: none;">Account</a>
        <span>/</span>
        <span style="color: var(--vm-primary); font-weight: 700;">Victorious Cashback</span>
    </div>

    <div class="profile-layout-grid">
        @include('customer.partials._sidebar')

        <main class="profile-canvas">
            <div class="page-header-row" style="margin-bottom: 20px;">
                <div>
                    <h1 class="page-header-title" style="font-size: 22px; font-weight: 800; color: var(--vm-dark); display: flex; align-items: center; gap: 10px;">
                        <span>🪙</span>
                        <span>5% Sovereign Cashback Rewards Wallet</span>
                    </h1>
                    <p style="font-size: 13.5px; color: var(--vm-text-muted); margin-top: 4px;">
                        5% Victorious Cashback Guarantee on every fulfilled order. Instantly credited to your sovereign rewards balance.
                    </p>
                </div>
            </div>

            <!-- Cashback Balance Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 22px;">
                <div style="background: linear-gradient(135deg, #FAF5FF 0%, #F3E8FF 100%); border: 1.5px solid rgba(94, 23, 235, 0.2); border-radius: 12px; padding: 20px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--vm-primary); text-transform: uppercase;">Available Cashback Balance</div>
                    <div style="font-size: 26px; font-weight: 800; color: var(--vm-primary); margin-top: 6px;">
                        ₦{{ number_format($cashbackAvailable ?? 0, 2) }}
                    </div>
                    <div style="font-size: 12px; color: var(--vm-text-muted); margin-top: 4px;">Ready for instant checkout deduction</div>
                </div>

                <div style="background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border: 1.5px solid rgba(245, 158, 11, 0.2); border-radius: 12px; padding: 20px;">
                    <div style="font-size: 12px; font-weight: 700; color: #B45309; text-transform: uppercase;">Pending Delivery Verification</div>
                    <div style="font-size: 26px; font-weight: 800; color: #B45309; margin-top: 6px;">
                        ₦{{ number_format($cashbackPending ?? 0, 2) }}
                    </div>
                    <div style="font-size: 12px; color: var(--vm-text-muted); margin-top: 4px;">Matures upon customer delivery OTP handshake</div>
                </div>
            </div>

            <div class="legacy-card">
                <h3 class="legacy-card-title">Cashback Rewards History</h3>
                <div class="table-responsive">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--vm-border); text-align: left; font-size: 12px; color: var(--vm-text-muted); text-transform: uppercase;">
                                <th style="padding: 10px;">Date</th>
                                <th style="padding: 10px;">Description</th>
                                <th style="padding: 10px;">Amount</th>
                                <th style="padding: 10px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cashbackLedger as $entry)
                                <tr style="border-bottom: 1px solid var(--vm-border); font-size: 13.5px;">
                                    <td style="padding: 12px 10px; color: var(--vm-text-muted);">
                                        {{ $entry->created_at->format('d M, Y H:i') }}
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        <strong style="color: var(--vm-dark);">{{ $entry->description ?? '5% Order Cashback' }}</strong>
                                    </td>
                                    <td style="padding: 12px 10px; font-weight: 800; color: #059669;">
                                        +₦{{ number_format($entry->amount, 2) }}
                                    </td>
                                    <td style="padding: 12px 10px;">
                                        @if($entry->status === 'matured')
                                            <span class="badge" style="background: #ECFDF5; color: #047857; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                ✓ Matured
                                            </span>
                                        @else
                                            <span class="badge" style="background: #FEF3C7; color: #B45309; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--vm-text-muted);">
                                        No cashback transactions recorded yet. Complete an order to earn your first 5% reward!
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