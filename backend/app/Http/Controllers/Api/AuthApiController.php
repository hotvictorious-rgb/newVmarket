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
            'user_type' => 'nullable|string|in:customer,vendor,rider',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'user_type' => $validated['user_type'] ?? 'customer',
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
        $validated = $request->validate([
            'login' => 'required|string', // Accepts email or phone
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
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

        $token = $user->createToken('mobile_app_token', [$user->user_type])->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
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
