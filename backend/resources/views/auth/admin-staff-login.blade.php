@extends('layouts.app')

@section('title', 'Admin Staff Portal — Victorious MARKET')

@section('content')
<div style="max-width: 460px; margin: 40px auto;">
    <div class="v-card" style="border-top: 4px solid #F59E0B; padding: 36px 32px;">
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 58px; height: 58px; border-radius: 14px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.28);">
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #FFF;">Admin Staff Workstation</h2>
            <div style="display: flex; gap: 6px; justify-content: center; margin-top: 8px;">
                <span class="badge badge-warning">Finance Desk</span>
                <span class="badge badge-info">Dispatch</span>
                <span class="badge badge-purple">Support</span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                Operational Queues, Settlement Audits &amp; Return Disputes
            </p>
        </div>

        @if ($errors->any())
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem; color: #FCA5A5;">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.staff.login.post') }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Staff Email Address</label>
                <input type="text" name="login" value="{{ old('login') }}" placeholder="Account Email / ID" required autofocus style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                    Demo accounts: <code>finance@...</code>, <code>dispatch@...</code>, <code>support@...</code>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px;">Staff Access Token / Password</label>
                <input type="password" name="password" value="" placeholder="••••••••••••" required style="width: 100%; padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.3); color: #FFF; font-size: 0.95rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem; background: linear-gradient(135deg, #D97706, #F59E0B);">
                Enter Staff Workstation
            </button>
        </form>
        @include('auth.partials._demo-accounts', ['demoAccounts' => $demoAccounts ?? \App\Services\DemoAccountRegistry::getDemoAccounts('admin_employee'), 'roleKey' => 'admin_employee'])
    </div>
</div>
@endsection
