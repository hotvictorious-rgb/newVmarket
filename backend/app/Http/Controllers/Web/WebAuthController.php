<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\User;
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
        return view('auth.customer-login', compact('lgas'));
    }

    public function loginCustomer(Request $request)
    {
        return $this->authenticateRole($request, ['customer'], redirect()->route('customer.dashboard'));
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

        return redirect()->route('customer.dashboard')
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
        return view('auth.admin-login');
    }

    public function loginAdmin(Request $request)
    {
        return $this->authenticateRole($request, ['super_admin'], redirect()->route('admin.dashboard'));
    }

    // ==========================================
    // 3. ADMIN EMPLOYEES (/admin-staff/login)
    // ==========================================
    public function showAdminStaffLogin()
    {
        return view('auth.admin-staff-login');
    }

    public function loginAdminStaff(Request $request)
    {
        return $this->authenticateRole($request, ['admin_employee'], redirect()->route('admin.staff.dashboard'));
    }

    // ==========================================
    // 4. VENDOR MERCHANT DESK (/vendor/login)
    // ==========================================
    public function showVendorLogin()
    {
        return view('auth.vendor-login');
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
        return view('auth.branch-counter-login');
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
        return view('auth.logistics-login');
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
        return view('auth.rider-login');
    }

    public function loginRider(Request $request)
    {
        return $this->authenticateRole($request, ['rider'], redirect()->route('rider.dashboard'));
    }

    // ==========================================
    // UNIFIED AUTHENTICATION ENGINE
    // ==========================================
    protected function authenticateRole(Request $request, array $allowedRoles, $redirectTarget)
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['login'])
            ->orWhere('phone', $credentials['login'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials provided.'],
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
