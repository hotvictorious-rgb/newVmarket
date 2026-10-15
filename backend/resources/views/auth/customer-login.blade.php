@extends('layouts.app')

@section('title', 'Customer Login — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid var(--accent); padding: 36px 32px;">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800;">Customer Sign In</h2>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">
                Access your Victorious 5% Cashback Rewards &amp; Order Tracking
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('customer.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Email or Phone Number</label>
                <input type="text" name="login" value="customer@victoriousmarket.com.ng" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <label style="font-size: 0.85rem; color: var(--text-muted);">Password</label>
                    <a href="#" style="font-size: 0.8rem; color: var(--accent); text-decoration: none;">Forgot?</a>
                </div>
                <input type="password" name="password" value="Password123!" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-accent" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem;">
                Sign In to Account
            </button>
        </form>

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.82rem; text-align: center; color: var(--text-muted);">
            Don't have an account? <a href="#" style="color: var(--accent); font-weight: 600; text-decoration: none;">Create Shopper Account</a>
        </div>
    </div>
</div>
@endsection
