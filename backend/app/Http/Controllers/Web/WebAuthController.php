<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Lga;
use App\Models\User;
use App\Services\DemoAccountRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebAuthController extends Controller
{
    // ==========================================
    // 1. CUSTOMER SHOPPER PORTAL (/login, /register)
    // ==========================================
    public function showCustomerLogin()
    {
        if (Auth::check() && Auth::user()->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }
        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('customer');
        return view('auth.customer-login', compact('lgas', 'demoAccounts'));
    }

    protected function sanitizeReturnTo(?string $returnTo): ?string
    {
        if (empty($returnTo)) {
            return null;
        }

        $trimmed = trim($returnTo);

        // Must start with a single slash, not protocol-relative '//', and not contain schema '://'
        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//') && !str_contains($trimmed, '://') && !str_contains($trimmed, ':\\')) {
            $parsed = parse_url($trimmed);
            if (!isset($parsed['host']) && !isset($parsed['scheme'])) {
                return $trimmed;
            }
        }

        return null;
    }

    public function loginCustomer(Request $request)
    {
        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));
        $target = $returnTo ? redirect($returnTo) : redirect()->intended(route('customer.dashboard'));
        return $this->authenticateRole($request, ['customer'], $target);
    }

    public function showCustomerRegister()
    {
        if (Auth::check() && Auth::user()->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }
        $lgas = Lga::where('is_active', true)->orderBy('name', 'asc')->get();
        return view('auth.customer-register', compact('lgas'));
    }

    public function registerCustomer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:150|unique:users,email',
            'phone' => ['required', 'string', 'max:25', 'unique:users,phone'],
            'password' => 'required|string|min:8|confirmed',
            'lga_id' => 'nullable|integer|exists:lgas,id',
            'terms' => 'accepted',
        ], [
            'terms.accepted' => 'You must accept the Victorious MARKET terms of service and buyer protection policy.',
            'phone.unique' => 'This phone number is already registered. Please sign in.',
            'email.unique' => 'This email address is already registered. Please sign in.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters for security.',
        ]);

        // Clean phone number
        $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['phone']);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $cleanPhone,
            'password' => Hash::make($validated['password']),
            'user_type' => 'customer', // STRICT ZERO-TRUST BARRIER: Always customer
            'status' => 'active',
        ]);

        // Save selected LGA in session for proximity shopping
        if (!empty($validated['lga_id'])) {
            $lga = Lga::find($validated['lga_id']);
            if ($lga) {
                session([
                    'customer_city' => $lga->name,
                    'customer_state' => 'Akwa Ibom',
                    'customer_lga_id' => $lga->id,
                ]);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));
        if ($returnTo) {
            return redirect($returnTo)
                ->with('status', "Account successfully created! Welcome to Victorious MARKET, {$user->name}. Your 5% cashback rewards wallet is active.");
        }

        return redirect()->intended(route('customer.dashboard'))
            ->with('status', "Account successfully created! Welcome to Victorious MARKET, {$user->name}. Your 5% cashback rewards wallet is active.");
    }

    // ==========================================
    // GOOGLE OAUTH & IDENTITY SERVICES
    // ==========================================
    public function redirectToGoogle(Request $request)
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('customer.login')->withErrors([
                'login' => 'Google Sign-In is configured on the platform, but GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET have not been added to .env yet. You can sign in directly using email/phone and password, or add your Google API credentials.',
            ]);
        }

        $state = Str::random(40);
        $request->session()->put('oauth_google_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'online',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?{$query}");
    }

    public function handleGoogleCallback(Request $request)
    {
        $sessionState = $request->session()->pull('oauth_google_state');
        $callbackState = $request->input('state');

        if (empty($sessionState) || $sessionState !== $callbackState) {
            return redirect()->route('customer.login')->withErrors([
                'login' => 'Google authorization failed: Security state verification mismatch (potential CSRF). Please try again.',
            ]);
        }

        if ($request->has('error')) {
            return redirect()->route('customer.login')->withErrors([
                'login' => 'Google Sign-In was cancelled: ' . $request->input('error'),
            ]);
        }

        $code = $request->input('code');
        if (empty($code)) {
            return redirect()->route('customer.login')->withErrors([
                'login' => 'No authorization code returned from Google.',
            ]);
        }

        try {
            // Exchange code for token
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Google OAuth Token Exchange Failed', ['body' => $tokenResponse->body()]);
                return redirect()->route('customer.login')->withErrors([
                    'login' => 'Failed to exchange authorization token with Google.',
                ]);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            // Fetch user info
            $profileResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
            if ($profileResponse->failed()) {
                return redirect()->route('customer.login')->withErrors([
                    'login' => 'Failed to retrieve user profile from Google.',
                ]);
            }

            $profile = $profileResponse->json();
            $googleId = $profile['sub'] ?? null;
            $email = strtolower($profile['email'] ?? '');
            $name = $profile['name'] ?? 'Google Customer';
            $avatar = $profile['picture'] ?? null;

            if (empty($email)) {
                return redirect()->route('customer.login')->withErrors([
                    'login' => 'Google did not provide a verified email address.',
                ]);
            }

            // Find or create customer
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                if (empty($user->google_id)) {
                    $user->google_id = $googleId;
                }
                if (empty($user->avatar) && !empty($avatar)) {
                    $user->avatar = $avatar;
                }
                if (empty($user->email_verified_at)) {
                    $user->email_verified_at = now();
                }
                $user->save();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'password' => Hash::make(Str::random(32)),
                    'user_type' => 'customer', // STRICT ZERO-TRUST
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }

            if ($user->status !== 'active') {
                return redirect()->route('customer.login')->withErrors([
                    'login' => "Your account is currently {$user->status}.",
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->route('customer.dashboard')
                ->with('status', "Successfully signed in with Google as {$user->name}!");

        } catch (\Throwable $e) {
            Log::error('Google OAuth Exception', ['message' => $e->getMessage()]);
            return redirect()->route('customer.login')->withErrors([
                'login' => 'An error occurred during Google sign-in: ' . $e->getMessage(),
            ]);
        }
    }

    public function handleGoogleOneTap(Request $request)
    {
        $credential = $request->input('credential');
        if (empty($credential)) {
            return response()->json(['success' => false, 'message' => 'Missing Google One-Tap credential token.'], 400);
        }

        try {
            $response = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$credential}");
            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Invalid Google ID token.'], 401);
            }

            $payload = $response->json();
            $clientId = config('services.google.client_id');

            if (!empty($clientId) && ($payload['aud'] ?? '') !== $clientId) {
                return response()->json(['success' => false, 'message' => 'Token audience mismatch.'], 403);
            }

            $googleId = $payload['sub'] ?? null;
            $email = strtolower($payload['email'] ?? '');
            $name = $payload['name'] ?? 'Google Customer';
            $avatar = $payload['picture'] ?? null;

            if (empty($email)) {
                return response()->json(['success' => false, 'message' => 'No email in Google token.'], 400);
            }

            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                if (empty($user->google_id)) {
                    $user->google_id = $googleId;
                }
                if (empty($user->avatar) && !empty($avatar)) {
                    $user->avatar = $avatar;
                }
                $user->save();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'password' => Hash::make(Str::random(32)),
                    'user_type' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'redirect' => route('customer.dashboard'),
                'name' => $user->name,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. SUPER ADMIN COMMAND GATE (/admin/login)
    // ==========================================
    public function showAdminLogin()
    {
        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        // Super Admin credentials strictly come from ENV. No demo accounts.
        return view('auth.admin-login');
    }

    public function loginAdmin(Request $request)
    {
        $response = $this->authenticateRole($request, ['super_admin'], redirect()->route('admin.dashboard'));

        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            AdminAuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'super_admin_login_success',
                'target_type' => User::class,
                'target_id' => Auth::id(),
                'new_values' => [
                    'session_id' => session()->getId(),
                    'authenticated_at' => Carbon::now()->toIso8601String(),
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    // ==========================================
    // 3. ADMIN EMPLOYEES (/admin-staff/login)
    // ==========================================
    public function showAdminStaffLogin()
    {
        if (Auth::check() && Auth::user()->isAdminEmployee()) {
            return redirect()->route('admin.staff.dashboard');
        }
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('admin_employee');
        return view('auth.admin-staff-login', compact('demoAccounts'));
    }

    public function loginAdminStaff(Request $request)
    {
        $response = $this->authenticateRole($request, ['admin_employee'], redirect()->route('admin.staff.dashboard'));

        if (Auth::check() && Auth::user()->isAdminEmployee()) {
            AdminAuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'admin_staff_login_success',
                'target_type' => User::class,
                'target_id' => Auth::id(),
                'new_values' => [
                    'session_id' => session()->getId(),
                    'role' => Auth::user()->adminEmployeeProfile->role ?? 'support',
                    'authenticated_at' => Carbon::now()->toIso8601String(),
                ],
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    // ==========================================
    // 4. VENDOR MERCHANT DESK (/vendor/login)
    // ==========================================
    public function showVendorLogin()
    {
        if (Auth::check() && Auth::user()->isVendor()) {
            return redirect()->route('vendor.dashboard');
        }
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('vendor');
        return view('auth.vendor-login', compact('demoAccounts'));
    }

    public function loginVendor(Request $request)
    {
        return $this->authenticateRole($request, ['vendor'], redirect()->route('vendor.dashboard'));
    }

    // ==========================================
    // 5. IN-SHOP BRANCH COUNTER TERMINAL (/counter/login)
    // ==========================================
    public function showCounterLogin()
    {
        if (Auth::check() && Auth::user()->isVendorEmployee()) {
            return redirect()->route('counter.dashboard');
        }
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('vendor_employee');
        return view('auth.branch-counter-login', compact('demoAccounts'));
    }

    public function loginCounter(Request $request)
    {
        return $this->authenticateRole($request, ['vendor_employee'], redirect()->route('counter.dashboard'));
    }

    // ==========================================
    // 6. LOGISTICS COMPANY FLEET DESK (/logistics/login)
    // ==========================================
    public function showLogisticsLogin()
    {
        if (Auth::check() && Auth::user()->isLogisticsCompany()) {
            return redirect()->route('logistics.dashboard');
        }
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('logistics_company');
        return view('auth.logistics-login', compact('demoAccounts'));
    }

    public function loginLogistics(Request $request)
    {
        return $this->authenticateRole($request, ['logistics_company'], redirect()->route('logistics.dashboard'));
    }

    // ==========================================
    // 7. DELIVERY RIDER STATUS PORTAL (/rider/login)
    // ==========================================
    public function showRiderLogin()
    {
        if (Auth::check() && Auth::user()->isRider()) {
            return redirect()->route('rider.dashboard');
        }
        $demoAccounts = DemoAccountRegistry::getDemoAccounts('rider');
        return view('auth.rider-login', compact('demoAccounts'));
    }

    public function loginRider(Request $request)
    {
        return $this->authenticateRole($request, ['rider'], redirect()->route('rider.dashboard'));
    }

    // ==========================================
    // UNIFIED AUTHENTICATION ENGINE
    // ==========================================
    
    /**
     * One-click demo persona authentication.
     * HARD INVARIANTS:
     * 1. Permanently disabled in production.
     * 2. Cannot authenticate super_admin (Superadmin is strictly from ENV).
     * 3. Cannot authenticate real (is_demo == false) users.
     */
    public function quickDemoLogin(Request $request)
    {
        if (app()->environment('production') || !DemoAccountRegistry::isDemoEnabled()) {
            abort(403, 'Demo authentication is permanently forbidden in production environments.');
        }

        $email = (string) $request->input('email');
        $role = (string) $request->input('role');

        if ($role === 'super_admin') {
            abort(403, 'Super Admin credentials must strictly be provided via server environment (ENV).');
        }

        $demoAccount = DemoAccountRegistry::findDemoAccount($email);
        if (!$demoAccount) {
            abort(404, 'Specified demo persona was not found in registry.');
        }

        $user = User::where('email', $demoAccount['email'])->first();
        if (!$user) {
            $user = User::create([
                'name' => $demoAccount['name'],
                'email' => $demoAccount['email'],
                'phone' => '+2348000' . rand(100000, 999999),
                'password' => Hash::make(DemoAccountRegistry::DEMO_PASSWORD),
                'user_type' => $role,
                'status' => 'active',
                'is_demo' => true,
            ]);
        }

        if ($user->user_type === 'super_admin') {
            abort(403, 'Super Admin credentials originate strictly from environment variables (ENV) and cannot be authenticated via demo shortcuts.');
        }

        if (!$user->is_demo) {
            abort(403, 'Security Violation: Cannot authenticate non-demo accounts via quick demo login.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $redirectRoute = match ($user->user_type) {
            'admin_employee' => 'admin.staff.dashboard',
            'vendor' => 'vendor.dashboard',
            'vendor_employee' => 'counter.dashboard',
            'logistics_company' => 'logistics.dashboard',
            'rider' => 'rider.dashboard',
            default => 'customer.dashboard',
        };

        return redirect()->route($redirectRoute)
            ->with('status', "Signed in as demo persona: {$user->name} ({$demoAccount['badge']})");
    }

    protected function authenticateRole(Request $request, array $allowedRoles, $redirectTarget)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['login'])
            ->orWhere('phone', $credentials['login'])
            ->first();

        $passwordValid = false;
        if ($user) {
            if (\Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)) {
                $passwordValid = true;
            } elseif ($user->is_demo && \App\Services\DemoAccountRegistry::isDemoEnabled()) {
                if ($credentials['password'] === \App\Services\DemoAccountRegistry::DEMO_PASSWORD || $credentials['password'] === 'Password123!') {
                    $passwordValid = true;
                }
            }
        }

        if (!$user || !$passwordValid) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials provided.'],
            ]);
        }

        // Hard Security Invariant: Super Admin accounts can NEVER be demo accounts under any circumstance
        if ($user->user_type === 'super_admin' && $user->is_demo) {
            throw ValidationException::withMessages([
                'login' => ['Security Violation: Super Admin accounts can never be demo accounts.'],
            ]);
        }

        if ($user->is_demo && app()->environment('production')) {
            throw ValidationException::withMessages([
                'login' => ['Demo accounts are permanently disabled in production environments.'],
            ]);
        }

        if (!in_array($user->user_type, $allowedRoles)) {
            $roleNames = implode(', ', array_map('ucwords', str_replace('_', ' ', $allowedRoles)));
            throw ValidationException::withMessages([
                'login' => ["Unauthorized: This portal is strictly restricted to {$roleNames} accounts."],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => ["Your account is currently {$user->status}."],
            ]);
        }

        // Role-Specific Profile & Parent Organization Active Status Invariants
        if ($user->user_type === 'vendor' && $user->vendorProfile) {
            if ($user->vendorProfile->marketplace_status === 'suspended') {
                throw ValidationException::withMessages([
                    'login' => ['Your merchant account has been suspended by marketplace administration.'],
                ]);
            }
        }

        if ($user->user_type === 'vendor_employee' && $user->vendorEmployeeProfile) {
            if ($user->vendorEmployeeProfile->is_active === false) {
                throw ValidationException::withMessages([
                    'login' => ['Your counter branch employee account has been deactivated.'],
                ]);
            }
            if ($user->vendorEmployeeProfile->vendor && $user->vendorEmployeeProfile->vendor->marketplace_status === 'suspended') {
                throw ValidationException::withMessages([
                    'login' => ['Access disabled: Parent merchant organization is currently suspended.'],
                ]);
            }
        }

        if ($user->user_type === 'logistics_company' && $user->logisticsCompanyProfile) {
            if ($user->logisticsCompanyProfile->status !== 'active') {
                throw ValidationException::withMessages([
                    'login' => ["Your logistics fleet account is currently {$user->logisticsCompanyProfile->status}."],
                ]);
            }
        }

        if ($user->user_type === 'rider') {
            if (!$user->riderProfile || !$user->riderProfile->logistics_company_id || !$user->riderProfile->logisticsCompany) {
                throw ValidationException::withMessages([
                    'login' => ['Access disabled: Rider is not affiliated with any logistics company. All riders must belong to an active logistics company.'],
                ]);
            }
            if (!$user->riderProfile->is_active) {
                throw ValidationException::withMessages([
                    'login' => ['Your courier dispatch profile is currently deactivated.'],
                ]);
            }
            if ($user->riderProfile->logisticsCompany->status !== 'active') {
                throw ValidationException::withMessages([
                    'login' => ['Access disabled: Parent logistics fleet is currently inactive or suspended.'],
                ]);
            }
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $redirectTarget->with('status', "Welcome back, {$user->name}!");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront')->with('status', 'You have been successfully signed out.');
    }
}

