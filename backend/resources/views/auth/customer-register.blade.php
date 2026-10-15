@extends('layouts.app')

@section('title', 'Create Free Shopper Account — Victorious MARKET')

@section('content')
<div class="vm-auth-container" style="max-width: 540px; margin: 40px auto 60px; padding: 0 16px;">
    
    <!-- Outer Card with Glassmorphic Shadow -->
    <div class="vm-auth-card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08); overflow: hidden; position: relative;">
        
        <!-- Top Sovereign Brand Accent Bar -->
        <div style="height: 5px; background: linear-gradient(90deg, #FFD700 0%, #5E17EB 100%);"></div>

        <div style="padding: 36px 32px 32px;">
            
            <!-- Brand Pedestal & Header -->
            <div style="text-align: center; margin-bottom: 26px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 18px; background: #FAF5FF; border: 2px solid rgba(255, 215, 0, 0.45); box-shadow: 0 8px 24px rgba(94, 23, 235, 0.15); margin-bottom: 16px;">
                    <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Victorious MARKET" style="width: 50px; height: 50px; border-radius: 14px; object-fit: cover;">
                </div>
                
                <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px; letter-spacing: -0.4px;">
                    Create Your Shopper Account
                </h1>
                
                <p style="font-size: 13.5px; color: #64748B; margin: 0 auto; max-width: 420px; line-height: 1.4;">
                    Join thousands of shoppers in Akwa Ibom enjoying verified local products, doorstep delivery, and guaranteed 5% cashback rewards.
                </p>
            </div>

            <!-- Navigation Tabs (Sign In / Register) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; background: #F1F5F9; border-radius: 12px; padding: 4px; margin-bottom: 26px;">
                <a href="{{ route('customer.login') }}" style="display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; padding: 10px; font-size: 13.5px; font-weight: 600; border-radius: 9px; color: #64748B; transition: all 0.15s ease;">
                    <span>🔑</span>
                    <span>Sign In</span>
                </a>
                <a href="{{ route('customer.register') }}" style="display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; padding: 10px; font-size: 13.5px; font-weight: 700; border-radius: 9px; background: #FFFFFF; color: #0F172A; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);">
                    <span>✨</span>
                    <span>Create Account</span>
                </a>
            </div>

            <!-- Validation Errors Alert -->
            @if ($errors->any())
                <div style="background: #FEF2F2; border: 1px solid #EF4444; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #991B1B;">
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; margin-bottom: 4px;">
                        <span>⚠️</span>
                        <span>Please correct the errors below:</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- 1. Quick Sign Up with Google Button -->
            <a href="{{ route('auth.google.redirect') }}" class="vm-google-btn" style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 12px 18px; border: 1.5px solid #CBD5E1; border-radius: 12px; background: #FFFFFF; color: #1E293B; font-size: 14px; font-weight: 700; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <!-- Google Multicolored SVG Logo -->
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span>Sign up with Google (Fast 1-Click)</span>
            </a>

            <!-- Divider -->
            <div style="display: flex; align-items: center; margin: 22px 0; color: #94A3B8; font-size: 12px; font-weight: 600;">
                <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
                <span style="padding: 0 12px; text-transform: uppercase; letter-spacing: 0.5px;">Or register with your details</span>
                <div style="flex-grow: 1; height: 1px; background: #E2E8F0;"></div>
            </div>

            <!-- Registration Form -->
            <form method="POST" action="{{ route('customer.register.post') }}">
                @csrf
                
                <!-- Full Name -->
                <div style="margin-bottom: 16px;">
                    <label for="nameInput" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Full Name (First &amp; Last Name) <span style="color: #EF4444;">*</span>
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #94A3B8;">👤</span>
                        <input type="text" id="nameInput" name="name" value="{{ old('name') }}" required autofocus placeholder="e.g. Victor Bassey" style="width: 100%; padding: 11px 14px 11px 42px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 14px; outline: none; box-sizing: border-box; transition: all 0.2s ease;">
                    </div>
                </div>

                <!-- Phone & Email (Side by Side) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    
                    <!-- Phone Number with Nigerian Badge -->
                    <div>
                        <label for="phoneInput" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Phone Number <span style="color: #EF4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; font-weight: 700; color: #475569;">🇳🇬</span>
                            <input type="tel" id="phoneInput" name="phone" value="{{ old('phone') }}" required placeholder="080... or 090..." style="width: 100%; padding: 11px 12px 11px 38px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label for="emailInput" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Email Address <span style="color: #EF4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #94A3B8;">✉️</span>
                            <input type="email" id="emailInput" name="email" value="{{ old('email') }}" required placeholder="you@example.com" style="width: 100%; padding: 11px 12px 11px 38px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                        </div>
                    </div>

                </div>

                <!-- Primary Delivery LGA Selector -->
                <div style="margin-bottom: 16px;">
                    <label for="lgaSelect" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Primary Local Government Area (LGA) <span style="font-weight: 400; color: #64748B;">— For fastest delivery speed</span>
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; color: #94A3B8;">📍</span>
                        <select id="lgaSelect" name="lga_id" style="width: 100%; padding: 11px 14px 11px 42px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box; cursor: pointer;">
                            <option value="">Select your local government (e.g. Uyo, Eket, Ikot Ekpene)...</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->id }}" {{ old('lga_id', session('customer_lga_id', 69)) == $lga->id ? 'selected' : '' }}>
                                    {{ $lga->name }} — {{ $lga->state->name ?? 'Akwa Ibom' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Password & Confirm Password (Side by Side) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
                    
                    <!-- Password -->
                    <div>
                        <label for="regPassword" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Password <span style="color: #EF4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #94A3B8;">🔒</span>
                            <input type="password" id="regPassword" name="password" required placeholder="Min. 8 characters" style="width: 100%; padding: 11px 36px 11px 36px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                            <button type="button" class="vm-pwd-toggle" data-target="regPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748B; font-size: 14px; padding: 2px;">
                                👁️
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="regPasswordConfirm" style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Confirm Password <span style="color: #EF4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #94A3B8;">🔒</span>
                            <input type="password" id="regPasswordConfirm" name="password_confirmation" required placeholder="Repeat password" style="width: 100%; padding: 11px 36px 11px 36px; border-radius: 10px; border: 1.5px solid #CBD5E1; background: #F8FAFC; color: #0F172A; font-size: 13.5px; outline: none; box-sizing: border-box;">
                            <button type="button" class="vm-pwd-toggle" data-target="regPasswordConfirm" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748B; font-size: 14px; padding: 2px;">
                                👁️
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Terms & Buyer Protection Checkbox -->
                <div style="margin-bottom: 22px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; font-size: 12.5px; color: #475569; cursor: pointer; line-height: 1.45;">
                        <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : 'checked' }} required style="accent-color: var(--vm-primary); width: 16px; height: 16px; border-radius: 4px; margin-top: 2px; flex-shrink: 0;">
                        <span>
                            I agree to Victorious MARKET's <strong>Buyer Protection &amp; Escrow Policy</strong>, physical in-shop handover inspection terms, and privacy protocols.
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" style="width: 100%; background: linear-gradient(135deg, #5E17EB 0%, #4D0FD4 100%); color: #FFFFFF; border: none; border-radius: 12px; padding: 13px 20px; font-size: 15px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.35); transition: transform 0.15s ease, box-shadow 0.15s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>Create Shopper Account &amp; Unlock 5% Rewards</span>
                    <span>→</span>
                </button>
            </form>

            <!-- Bottom Login Link -->
            <div style="margin-top: 26px; padding-top: 20px; border-top: 1px solid #F1F5F9; font-size: 13px; text-align: center; color: #64748B;">
                Already have an account? 
                <a href="{{ route('customer.login') }}" style="color: var(--vm-primary); font-weight: 700; text-decoration: none; margin-left: 4px;">
                    Sign In to Account →
                </a>
            </div>

        </div>

        <!-- Escrow & Security Trust Strip Footer -->
        <div style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 12px 24px; display: flex; justify-content: center; align-items: center; gap: 16px; font-size: 11.5px; color: #64748B;">
            <span>🛡️ Escrow Protected</span>
            <span>•</span>
            <span>🎁 5% Cashback Rewards</span>
            <span>•</span>
            <span>⚡ Doorstep &amp; Plaza Pickup</span>
        </div>

    </div>
</div>

@push('scripts')
<script>
    // Password visibility toggle for multiple fields
    document.querySelectorAll('.vm-pwd-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            if (input) {
                const isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                this.textContent = isPwd ? '🙈' : '👁️';
            }
        });
    });

    // High affordance input styling
    const formInputs = document.querySelectorAll('#nameInput, #phoneInput, #emailInput, #lgaSelect, #regPassword, #regPasswordConfirm');
    formInputs.forEach(input => {
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
