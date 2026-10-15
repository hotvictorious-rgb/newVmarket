@if (!empty($demoAccounts) && count($demoAccounts) > 0)
    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px dashed rgba(255,255,255,0.15);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 16px;">🧪</span>
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--gold); text-transform: uppercase; letter-spacing: 0.05em;">
                    Demo Personas (3 Available)
                </span>
            </div>
            <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 9999px; background: rgba(94, 23, 235, 0.2); color: #C4B5FD; font-weight: 600; border: 1px solid rgba(94, 23, 235, 0.4);">
                DEMO MODE
            </span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            @foreach ($demoAccounts as $demo)
                <div style="background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-md); padding: 12px 14px; transition: border-color 0.2s, background 0.2s;" onmouseover="this.style.borderColor='var(--gold)'; this.style.background='rgba(0,0,0,0.5)';" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.background='rgba(0,0,0,0.35)';">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                                <strong style="font-size: 0.9rem; color: #FFF;">{{ $demo['name'] }}</strong>
                                <span style="font-size: 0.7rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; background: rgba(245, 158, 11, 0.15); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    {{ $demo['badge'] }}
                                </span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px; word-break: break-all;">
                                <code>{{ $demo['email'] }}</code>
                            </div>
                            <div style="font-size: 0.76rem; color: #94A3B8; line-height: 1.4;">
                                {{ $demo['description'] }}
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 6px; flex-shrink: 0;">
                            <form method="POST" action="{{ route('demo.quick_login') }}">
                                @csrf
                                <input type="hidden" name="email" value="{{ $demo['email'] }}">
                                <input type="hidden" name="role" value="{{ $roleKey ?? $demo['role_label'] ?? 'customer' }}">
                                <button type="submit" style="padding: 6px 12px; font-size: 0.78rem; font-weight: 700; background: linear-gradient(135deg, var(--gold), #D97706); color: #000; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; white-space: nowrap; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25);" title="Instant Demo Sign In">
                                    <span>⚡ Sign In</span>
                                </button>
                            </form>
                            <button type="button" onclick="autofillDemo('{{ $demo['email'] }}', '{{ $demo['password'] }}')" style="padding: 4px 8px; font-size: 0.7rem; background: rgba(255,255,255,0.06); color: #CBD5E1; border: 1px solid rgba(255,255,255,0.12); border-radius: 4px; cursor: pointer;" title="Fill credentials into form inputs">
                                Auto-fill
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 12px; font-size: 0.72rem; color: #64748B; line-height: 1.4; text-align: center;">
            🔒 <em>Strict Security Policy: Demo credentials are strictly segregated and permanently locked out in production.</em>
        </div>
    </div>

    <script>
        function autofillDemo(email, password) {
            const loginInput = document.querySelector('input[name="login"]') || document.querySelector('input[name="email"]');
            const passInput = document.querySelector('input[name="password"]');
            if (loginInput) loginInput.value = email;
            if (passInput) passInput.value = password;
        }
    </script>
@endif
