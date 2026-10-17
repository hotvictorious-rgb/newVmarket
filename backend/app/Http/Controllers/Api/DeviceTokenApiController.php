<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Models\UserDeviceToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenApiController extends Controller
{
    /**
     * Register or update an active FCM device token for the authenticated user
     */
    public function registerToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => 'required|string|max:512',
            'device_type' => 'required|string|in:android,ios,web',
            'app_type' => 'required|string|in:rider,vendor,customer,admin',
            'device_name' => 'nullable|string|max:128',
        ]);

        $user = $request->user();

        $tokenRecord = UserDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_token' => $validated['device_token'],
            ],
            [
                'device_type' => $validated['device_type'],
                'app_type' => $validated['app_type'],
                'device_name' => strip_tags(trim($validated['device_name'] ?? '')),
                'is_active' => true,
                'last_seen_at' => Carbon::now(),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'FCM device token registered successfully for push dispatch.',
            'data' => [
                'id' => $tokenRecord->id,
                'device_type' => $tokenRecord->device_type,
                'app_type' => $tokenRecord->app_type,
                'is_active' => $tokenRecord->is_active,
            ],
        ], 200);
    }

    /**
     * Revoke or deactivate a device token on logout
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => 'required|string',
        ]);

        $user = $request->user();

        UserDeviceToken::where('user_id', $user->id)
            ->where('device_token', $validated['device_token'])
            ->update([
                'is_active' => false,
                'last_seen_at' => Carbon::now(),
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Device token successfully deactivated. User will no longer receive pushes on this terminal.',
        ], 200);
    }

    /**
     * List in-app notifications for the authenticated user
     */
    public function listInApp(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = SystemNotification::where('user_id', $user->id)
            ->recent(30)
            ->get();

        $unreadCount = SystemNotification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'status' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ], 200);
    }

    /**
     * Mark notification(s) as read
     */
    public function markAsRead(Request $request, ?string $id = null): JsonResponse
    {
        $user = $request->user();

        if ($id) {
            SystemNotification::where('user_id', $user->id)
                ->where('id', $id)
                ->update(['read_at' => Carbon::now()]);
        } else {
            SystemNotification::where('user_id', $user->id)
                ->unread()
                ->update(['read_at' => Carbon::now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notifications marked as read.',
        ], 200);
    }
}
