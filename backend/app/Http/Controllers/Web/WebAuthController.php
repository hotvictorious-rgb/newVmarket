<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class WebAuthController extends Controller
{
    // ==========================================
    // 1. CUSTOMER SHOPPER PORTAL (/login)
    // ==========================================
    public function showCustomerLogin()
    {
        return view('auth.customer-login');
    }

    public function loginCustomer(Request $request)
    {
        return $this->authenticateRole($request, ['customer'], redirect()->route('customer.dashboard'));
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
