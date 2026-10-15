@extends('layouts.app')

@section('title', 'Super Admin Command Gate — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid #EF4444; padding: 36px 32px; background: linear-gradient(180deg, rgba(30, 15, 25, 0.95), var(--card-dark));">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">Super Admin Command Gate</h2>
            <div style="display: inline-block; margin-top: 6px;">
                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #FCA5A5;">RESTRICTED EXECUTIVE ACCESS</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                Treasury Settlement, Directional Lane Matrix &amp; Platform Governance
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Executive Admin ID / Email</label>
                <input type="text" name="login" value="{{ old('login') }}" placeholder="Executive Admin ID / Email" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Master Security Key</label>
                <input type="password" name="password" value="" placeholder="••••••••••••" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; background: linear-gradient(135deg, #B91C1C, #EF4444);">
                Authenticate Command Gate
            </button>
        </form>
        <div style="margin-top: 16px; padding: 10px 14px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); font-size: 0.76rem; color: #FCA5A5; line-height: 1.4; text-align: center;">
            <strong>ENV Configuration Notice:</strong> Super Admin access is never in demo mode and must strictly match <code>SUPERADMIN_EMAIL</code> and <code>SUPERADMIN_PASSWORD</code> configured in <code>.env</code>.
        </div>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.78rem; text-align: center; color: var(--text-muted);">
            All administrative logins are cryptographically logged in <code>admin_audit_logs</code>.
        </div>
    </div>
</div>
@endsection
