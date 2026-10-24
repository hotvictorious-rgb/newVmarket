@extends('layouts.app')

@section('title', 'Super Admin 2FA Verification — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid #EF4444; padding: 36px 32px; background: linear-gradient(180deg, rgba(30, 15, 25, 0.95), var(--card-dark));">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">Two-Factor Authentication</h2>
            <div style="display: inline-block; margin-top: 6px;">
                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #FCA5A5;">EXECUTIVE IDENTITY VERIFICATION</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                A single-use 6-digit passcode was sent to <strong>{{ $email }}</strong>. Enter it below to unlock the Command Tower.
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10B981; border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #6EE7B7;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.verify_2fa.post') }}">
            @csrf
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; text-align: center;">6-Digit Security Code</label>
                <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required autofocus
                    style="width: 100%; padding: 14px; letter-spacing: 0.5rem; text-align: center; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.25); background: rgba(0,0,0,0.4); color: #FFF; font-size: 1.8rem; font-weight: 800; font-family: monospace;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; background: linear-gradient(135deg, #B91C1C, #EF4444);">
                Verify &amp; Unlock Command Tower
            </button>
        </form>

        <form method="POST" action="{{ route('admin.resend_2fa') }}" style="margin-top: 14px; text-align: center;">
            @csrf
            <button type="submit" style="background: none; border: none; color: #9CA3AF; font-size: 0.85rem; cursor: pointer; text-decoration: underline;">
                Didn't receive code? Resend OTP
            </button>
        </form>

        <div style="margin-top: 20px; padding: 10px 14px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); font-size: 0.76rem; color: #FCA5A5; line-height: 1.4; text-align: center;">
            <strong>Anti-Hijack &amp; Inactivity Protection:</strong> This session will automatically expire after 15 minutes of inactivity and is strictly bound to your network IP.
        </div>
    </div>
</div>
@endsection
