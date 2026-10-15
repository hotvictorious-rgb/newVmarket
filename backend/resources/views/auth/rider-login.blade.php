@extends('layouts.app')

@section('title', 'Rider Terminal & Status Portal — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid var(--primary); padding: 36px 32px;">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">Delivery Rider Portal</h2>
            <div style="display: inline-block; margin-top: 6px;">
                <span class="badge badge-purple">COURIER ACCOUNT STATUS</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                Check Shift Balance, KYC Status, and Download Rider Terminal App
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('rider.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Rider Phone Number / Email</label>
                <input type="text" name="login" value="rider@victoriousmarket.com.ng" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">PIN / Password</label>
                <input type="password" name="password" value="Password123!" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem;">
                Sign In to Rider Account
            </button>
        </form>

        <div style="margin-top: 24px; padding: 16px; border-radius: var(--radius-md); background: rgba(108, 42, 138, 0.15); border: 1px solid rgba(108, 42, 138, 0.3); font-size: 0.82rem; color: #D8B4FE; text-align: center;">
            <div style="font-weight: 700; margin-bottom: 4px;">📲 Active Deliveries Require Mobile App</div>
            <p style="font-size: 0.78rem; opacity: 0.85; margin-bottom: 10px;">Turn-by-turn navigation and OTP verification require GPS on the motorcycle.</p>
            <a href="#" class="btn-accent" style="font-size: 0.78rem; padding: 8px 16px; display: inline-flex;">
                ⬇️ Download Rider Flutter APK
            </a>
        </div>
    </div>
</div>
@endsection
