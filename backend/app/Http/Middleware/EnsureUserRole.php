<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Unauthenticated check
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $loginRoute = $this->resolveLoginRoute($roles, $request);

            return redirect()->guest(route($loginRoute))
                ->with('warning', 'Please sign in with authorized credentials to access this portal.');
        }

        $user = Auth::user();

        // 2. Active status check
        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('storefront')
                ->with('error', "Your account is currently {$user->status}. Please contact administration.");
        }

        // 3. Role authorization check
        if (!empty($roles) && !in_array($user->user_type, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Forbidden: Insufficient role permissions.',
                    'current_role' => $user->user_type,
                    'required_roles' => $roles,
                ], 403);
            }

            return response()->view('errors.role-mismatch', [
                'user' => $user,
                'requiredRoles' => $roles,
            ], 403);
        }

        // 4. Cascading Role Profile & Parent Fleet/Merchant Active Checks
        if ($user->user_type === 'vendor_employee') {
            $employee = $user->vendorEmployeeProfile;
            if ($employee && $employee->is_active === false) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('counter.login')->with('error', 'Access terminated: Your employee profile has been deactivated.');
            }
            if ($employee && $employee->vendor && $employee->vendor->marketplace_status === 'suspended') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('counter.login')->with('error', 'Access terminated: Parent merchant organization is suspended.');
            }
        } elseif ($user->user_type === 'rider') {
            $rider = $user->riderProfile;
            if ($rider && !$rider->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('rider.login')->with('error', 'Access terminated: Your courier dispatch profile has been deactivated.');
            }
            if ($rider && $rider->logisticsCompany && $rider->logisticsCompany->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('rider.login')->with('error', 'Access terminated: Parent logistics fleet is suspended or inactive.');
            }
        } elseif ($user->user_type === 'logistics_company') {
            $company = $user->logisticsCompanyProfile;
            if ($company && $company->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('logistics.login')->with('error', "Access terminated: Your fleet profile is currently {$company->status}.");
            }
        }

        return $next($request);
    }

    /**
     * Resolve the dedicated login route based on required role or request path.
     */
    protected function resolveLoginRoute(array $roles, Request $request): string
    {
        if (in_array('super_admin', $roles, true)) {
            return 'admin.login';
        }
        if (in_array('admin_employee', $roles, true)) {
            return 'admin.staff.login';
        }
        if (in_array('vendor', $roles, true)) {
            return 'vendor.login';
        }
        if (in_array('vendor_employee', $roles, true)) {
            return 'counter.login';
        }
        if (in_array('logistics_company', $roles, true)) {
            return 'logistics.login';
        }
        if (in_array('rider', $roles, true)) {
            return 'rider.login';
        }

        return 'customer.login';
    }
}
