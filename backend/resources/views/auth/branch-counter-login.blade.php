@extends('layouts.app')

@section('title', 'Branch Counter Terminal — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid var(--success); padding: 36px 32px; background: linear-gradient(180deg, rgba(16, 40, 30, 0.95), var(--card-dark));">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">In-Shop Counter Terminal</h2>
            <div style="display: inline-block; margin-top: 6px;">
                <span class="badge badge-success">BRANCH-ISOLATED COUNTER DESK</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                Physical Product Inspection Sign-Off &bull; Custody Handover OTP Verification
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('counter.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Branch Staff Work ID / Email</label>
                <input type="text" name="login" value="staff@victoriousmarket.com.ng" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Staff Passcode / Password</label>
                <input type="password" name="password" value="Password123!" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; background: linear-gradient(135deg, #059669, #10B981);">
                Open Counter Terminal
            </button>
        </form>

        <div style="margin-top: 24px; padding: 14px; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); font-size: 0.8rem; color: #6EE7B7;">
            🛡️ <strong>Zero-POS Safety:</strong> Branch staff accounts are strictly locked to their assigned physical counter (<code>shop_id</code>) and have zero access to merchant banking or outside inventory.
        </div>
    </div>
</div>
@endsection
