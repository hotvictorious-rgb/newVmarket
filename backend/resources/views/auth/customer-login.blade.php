@extends('layouts.app')

@section('title', 'Sign In to Your Shopper Account — Victorious MARKET')

@section('content')
<div class="vm-auth-container" style="max-width: 520px; margin: 40px auto 60px; padding: 0 16px;">
    
    <!-- Outer Card with Glassmorphic Shadow -->
    <div class="vm-auth-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); overflow: hidden; position: relative;">
        
        <!-- Top Sovereign Brand Accent Bar -->
        <div style="height: 5px; background: linear-gradient(90deg, #5E17EB 0%, #FFD700 100%);"></div>

        <div style="padding: 36px 32px 32px;">
            
            <!-- Brand Pedestal & Header -->
            <div style="text-align: center; margin-bottom: 28px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 18px; background: #FAF5FF; border: 2px solid rgba(255, 215, 0, 0.45); box-shadow: 0 8px 24px rgba(94, 23, 235, 0.15); margin-bottom: 16px;">
                    <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 50px; height: 50px; border-radius: 14px; object-fit: cover;">
                </div>
                
                <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px; letter-spacing: -0.4px;">
                    Welcome Back Shopper
                </h1>
                
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; border: 1px solid rgba(5, 150, 105, 0.2); padding: 3px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #059669;">
                    <span>🎁</span>
                    <span>5% Cashback Rewards Guarantee Active</span>
                </div>
            </div>

            <!-- Navigation Tabs (Sign In / Register) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; background: #F1F5F9; border-radius: 12px; padding: 4px; margin-bottom: 26px;">
                <a href="{{ route('customer.login') }}" style="display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; padding: 10px; font-size: 13.5px; font-weight: 700; border-radius: 9px; background: #FFFFFF; color: #0F172A; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);">
                    <span>🔑</span>
                    <span>Sign In</span>
                </a>
                <a href="{{ route('customer.register') }}" style="display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; padding: 10px; font-size: 13.5px; font-weight: 600; border-radius: 9px; color: #64748B; transition: all 0.15s ease;">
                    <span>✨</span>
                    <span>Create Account</span>
                </a>
            </div>

            <!-- Alert Messages -->
            @if(session('status'))
                <div style="background: #ECFDF5; border: 1px solid #10B981; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #065F46; display: flex; align-items: center; gap: 8px;">
                    <span>✓</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div style="background: #FEF2F2; border: 1px solid #EF4444; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #991B1B;">
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin-bottom: 4px;">
                        <span>⚠️</span>
                        <span>Authentication Notice</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- 1. Continue with Google Button -->
            <a href="{{ route('auth.google.redirect') }}" class="vm-google-btn" style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 12px 18px; border: 1.5px solid #CBD5E1; border-radius: 12px; background: #FFFFFF; color: #1E293B; font-size: 14px; font-weight: 700; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <!-- Google Multicolored SVG Logo -->
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span>Continue with Google</span>
            </a>

            <!-- Divider -->
            <div style="display: flex; align-items: center; margin: 22px 0; color: #94A3B8; font-size: 12px; font-weight: 600;">
                <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
                <span style="padding: 0 12px; text-transform: uppercase; letter-spacing: 0.5px;">Or sign in with email / phone</span>
                <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
            </div>

            <!-- Standard Credentials Form -->
            <form method="POST" action="{{ route('customer.login.post') }}">
                @csrf
                
                <!-- Identifier (Email or Phone) -->
                <div style="margin-bottom: 18px;">
                    <label for="loginInput" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Email or Phone Number
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #94A3B8;">📱</span>
                        <input type="text" id="loginInput" name="login" value="{{ old('login', 'customer@victoriousmarket.com.ng') }}" required autofocus placeholder="e.g. 09118949035 or customer@email.com" style="width: 100%; padding: 12px 14px 12px 42px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 14px; transition: border-color 0.2s ease; outline: none; box-sizing: border-box;">
                    </div>
                </div>

                <!-- Password with Show/Hide Toggle -->
                <div style="margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="passwordInput" style="font-size: 13px; font-weight: 700; color: #334155;">
                            Password
                        </label>
                        <a href="https://wa.me/2349118949035?text=Hello%20Victorious%20MARKET,%20I%20need%20assistance%20resetting%20my%20shopper%20password" target="_blank" style="font-size: 12.5px; color: var(--vm-primary); font-weight: 600; text-decoration: none;">
                            Forgot Password?
                        </a>
                    </div>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #94A3B8;">🔒</span>
                        <input type="password" id="passwordInput" name="password" value="Password123!" required placeholder="Enter your secret password" style="width: 100%; padding: 12px 44px 12px 42px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 14px; transition: border-color 0.2s ease; outline: none; box-sizing: border-box;">
                        
                        <button type="button" id="togglePasswordBtn" aria-label="Toggle password visibility" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748B; font-size: 16px; padding: 4px;">
                            👁️
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Trust -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; cursor: pointer;">
                        <input type="checkbox" name="remember" value="1" checked style="accent-color: var(--vm-primary); width: 16px; height: 16px; border-radius: 4px;">
                        <span>Keep me signed in</span>
                    </label>
                    <span style="font-size: 12px; color: #059669; font-weight: 600;">🛡️ 256-Bit Encrypted</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #5E17EB 0%, #4D0FD4 100%); color: #FFFFFF; border: none; border-radius: 12px; padding: 13px 20px; font-size: 15px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); transition: transform 0.15s ease, box-shadow 0.15s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>Sign In to Account</span>
                    <span>→</span>
                </button>
            </form>

            <!-- Bottom Registration Prompt -->
            <div style="margin-top: 26px; padding-top: 20px; border-top: 1px solid #F1F5F9; font-size: 13px; text-align: center; color: #64748B;">
                New to Victorious MARKET? 
                <a href="{{ route('customer.register') }}" style="color: var(--vm-primary); font-weight: 700; text-decoration: none; margin-left: 4px;">
                    Create Free Account & Earn 5% Cashback →
                </a>
            </div>

        </div>

        <!-- Escrow & Security Trust Strip Footer -->
        <div style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 12px 24px; display: flex; justify-content: center; align-items: center; gap: 16px; font-size: 11.5px; color: #64748B;">
            <span>🛡️ Escrow Protected</span>
            <span>•</span>
            <span>📍 Physical In-Shop Pickup</span>
            <span>•</span>
            <span>⚡ Handshake OTP Delivery</span>
        </div>

    </div>
</div>

@push('scripts')
<script>
    // Password visibility toggle
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const pwdInput = document.getElementById('passwordInput');
    
    if (toggleBtn && pwdInput) {
        toggleBtn.addEventListener('click', function () {
            const isPwd = pwdInput.type === 'password';
            pwdInput.type = isPwd ? 'text' : 'password';
            this.textContent = isPwd ? '🙈' : '👁️';
        });
    }

    // High affordance input focus
    const inputs = document.querySelectorAll('#loginInput, #passwordInput');
    inputs.forEach(input => {
        input.addEventListener('focus', function () {
            this.style.borderColor = '#5E17EB';
            this.style.background = '#FFFFFF';
            this.style.boxShadow = '0 0 0 3px rgba(94, 23, 235, 0.12)';
        });
        input.addEventListener('blur', function () {
            this.style.borderColor = '#CBD5E1';
            this.style.background = '#F8FAFC';
            this.style.boxShadow = 'none';
        });
    });
</script>
@endpush
@endsection
