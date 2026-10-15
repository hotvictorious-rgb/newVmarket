@extends('layouts.app')

@section('title', 'Role Access Restricted — Victorious MARKET')

@section('content')
<div style="max-width: 580px; margin: 60px auto; padding: 0 16px;">
    <div class="v-card" style="border-top: 4px solid var(--gold); padding: 40px 32px; background: linear-gradient(180deg, rgba(30, 20, 45, 0.98), var(--card-dark)); text-align: center;">
        <div style="display: flex; justify-content: center; margin-bottom: 20px;">
            <div style="width: 68px; height: 68px; border-radius: 20px; background: rgba(239, 68, 68, 0.15); border: 2px solid rgba(239, 68, 68, 0.35); display: flex; align-items: center; justify-content: center; font-size: 32px;">
                🛡️
            </div>
        </div>

        <h2 style="font-size: 1.5rem; font-weight: 800; color: #FFF; margin-bottom: 8px;">Role Access Barrier</h2>
        <div style="display: inline-block; margin-bottom: 16px;">
            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #FCA5A5; font-size: 0.8rem; font-weight: 700; padding: 4px 12px; border-radius: 9999px;">
                HTTP 403 • ZERO-TRUST ISOLATION
            </span>
        </div>

        <p style="font-size: 0.92rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
            You are currently signed in as <strong style="color: #FFF;">{{ $user->name }}</strong> 
            (<code style="color: var(--gold); background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">{{ $user->user_type }}</code>).
            This specific section is strictly restricted to 
            <strong style="color: #6EE7B7;">{{ implode(', ', array_map(fn($r) => ucwords(str_replace('_', ' ', $r)), $requiredRoles ?? [])) }}</strong> accounts.
        </p>

        <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); border-radius: var(--radius-md); padding: 18px 20px; margin-bottom: 28px; text-align: left;">
            <div style="font-size: 0.78rem; font-weight: 700; color: var(--gold); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                Security Enforcement Policy
            </div>
            <div style="font-size: 0.82rem; color: #CBD5E1; line-height: 1.5;">
                Under Victorious MARKET Rule 9 (Zero-Trust IDOR Authorization Scoping), sessions cannot cross-pollinate private merchant, fleet, or customer ledgers.
            </div>
        </div>

        @php
            $targetUrl = match($user->user_type) {
                'super_admin' => route('admin.dashboard'),
                'admin_employee' => route('admin.staff.dashboard'),
                'vendor' => route('vendor.dashboard'),
                'vendor_employee' => route('counter.dashboard'),
                'logistics_company' => route('logistics.dashboard'),
                'rider' => route('rider.dashboard'),
                default => route('customer.dashboard'),
            };
            $roleLabel = ucwords(str_replace('_', ' ', $user->user_type));
        @endphp

        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
            <a href="{{ $targetUrl }}" class="btn-primary" style="padding: 12px 24px; font-weight: 700; font-size: 0.9rem; background: linear-gradient(135deg, var(--gold), #D97706); color: #000; text-decoration: none; border-radius: var(--radius-md);">
                Return to My {{ $roleLabel }} Portal &rarr;
            </a>

            <form method="POST" action="{{ route('logout') }}" style="display: inline-block;">
                @csrf
                <button type="submit" class="btn-secondary" style="padding: 12px 20px; font-size: 0.9rem; font-weight: 600; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); color: #FFF; border-radius: var(--radius-md); cursor: pointer;">
                    Sign Out &amp; Switch Account
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
