@extends('layouts.app')

@section('title', 'Merchant Business Portal — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid #3B82F6; padding: 36px 32px;">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">Merchant Business Portal</h2>
            <div style="display: inline-block; margin-top: 6px;">
                <span class="badge badge-info">BUSINESS OWNER DESK</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                Product Catalog, 7-Day Listing Freshness, NUBAN Bank &amp; Settlement History
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('vendor.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Merchant Registered Email / Phone</label>
                <input type="text" name="login" value="{{ old('login') }}" placeholder="Account Email / ID" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Password</label>
                <input type="password" name="password" value="" placeholder="••••••••••••" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; background: linear-gradient(135deg, #1D4ED8, #3B82F6);">
                Enter Merchant Dashboard
            </button>
        </form>
        @include('auth.partials._demo-accounts', ['demoAccounts' => $demoAccounts ?? \App\Services\DemoAccountRegistry::getDemoAccounts('vendor'), 'roleKey' => 'vendor'])

        <div style="margin-top: 24px; padding: 14px; border-radius: var(--radius-md); background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.25); font-size: 0.8rem; color: #93C5FD;">
            📱 <strong>Mobile App Recommended:</strong> For real-time push alerts and live order notifications on the go, use the <strong>Victorious Vendor Flutter App</strong>.
        </div>
    </div>
</div>
@endsection
