<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => strip_tags(trim($validated['name'])),
            'email' => strtolower(trim($validated['email'])),
            'phone' => preg_replace('/[^0-9+]/', '', $validated['phone']),
            'user_type' => 'customer', // STRICT ZERO-TRUST: Public API registration is strictly customer
            'status' => 'active',
            'is_demo' => false, // Real registration is NEVER a demo account
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('mobile_app_token', [$user->user_type])->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $loginIdentifier = $request->input('login') ?? $request->input('email');
        $password = $request->input('password');

        if (!$loginIdentifier || !$password) {
            throw ValidationException::withMessages([
                'login' => ['The login (or email) and password fields are required.'],
            ]);
        }

        $user = User::where('email', $loginIdentifier)
            ->orWhere('phone', $loginIdentifier)
            ->first();

        $passwordValid = false;
        if ($user) {
            if (\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                $passwordValid = true;
            } elseif ($user->is_demo && \App\Services\DemoAccountRegistry::isDemoEnabled()) {
                if ($password === \App\Services\DemoAccountRegistry::DEMO_PASSWORD || $password === 'Password123!') {
                    $passwordValid = true;
                }
            }
        }

        if (!$user || !$passwordValid) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Hard Invariant: Super Admin accounts cannot authenticate via mobile API tokens
        if ($user->user_type === 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => 'Super Administrator credentials are strictly restricted to the Web Command Gate.',
            ], 403);
        }

        // Hard Invariant: Demo accounts cannot authenticate in production
        if ($user->is_demo && app()->environment('production')) {
            return response()->json([
                'status' => false,
                'message' => 'Demo accounts are permanently disabled in production environments.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => "Account is currently {$user->status}.",
            ], 403);
        }

        $terminal = $request->input('terminal') ?? $request->header('X-Terminal-Type');

        // STRICT ROLE INVARIANT & PROOF: Rider Operating Terminal
        // 1. Only users with user_type === 'rider' can log into the rider app.
        //    Logistics companies, vendors, and customers are strictly prohibited.
        // 2. All riders MUST belong to a verified, active logistics company.
        if ($terminal === 'rider') {
            if ($user->user_type !== 'rider') {
                return response()->json([
                    'status' => false,
                    'message' => 'Access Denied: Only dispatch riders can authenticate into the Rider Terminal. Logistics company accounts, merchants, and customers must use their designated portals.',
                ], 403);
            }

            $rider = $user->riderProfile;
            if (!$rider || !$rider->logistics_company_id || !$rider->logisticsCompany) {
                return response()->json([
                    'status' => false,
                    'message' => 'Access Denied: All riders must belong to an active logistics company. Independent unassigned dispatch is strictly prohibited.',
                ], 403);
            }

            if (!$rider->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Access Denied: Rider dispatch account has been suspended or deactivated.',
                ], 403);
            }

            if ($rider->logisticsCompany->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Access Denied: Affiliated logistics company fleet is currently inactive or suspended.',
                ], 403);
            }
        }

        $token = $user->createToken('mobile_app_token', [$user->user_type])->plainTextToken;

        $userPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'user_type' => $user->user_type,
        ];

        if ($user->user_type === 'rider' && $user->riderProfile && $user->riderProfile->logisticsCompany) {
            $userPayload['fleet_company'] = $user->riderProfile->logisticsCompany->company_name;
            $userPayload['vehicle_type'] = $user->riderProfile->vehicle_type;
            $userPayload['license_plate'] = $user->riderProfile->license_plate;
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $userPayload,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'user_type' => $user->user_type,
            'avatar' => $user->avatar,
        ];

        if ($user->user_type === 'rider' && $user->riderProfile && $user->riderProfile->logisticsCompany) {
            $data['fleet_company'] = $user->riderProfile->logisticsCompany->company_name;
            $data['vehicle_type'] = $user->riderProfile->vehicle_type;
            $data['license_plate'] = $user->riderProfile->license_plate;
        }

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|unique:users,phone,' . $user->id,
            'avatar' => 'nullable|string',
            'password' => 'nullable|string|min:8',
        ]);

        if (isset($validated['name'])) {
            $user->name = strip_tags(trim($validated['name']));
        }
        if (isset($validated['phone'])) {
            $user->phone = preg_replace('/[^0-9+]/', '', $validated['phone']);
        }
        if (isset($validated['avatar'])) {
            $user->avatar = strip_tags(trim($validated['avatar']));
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}
