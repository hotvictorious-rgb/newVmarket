<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\SystemSetting;
use App\Models\UserDeviceToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseCloudMessagingService
{
    /**
     * Send push notification to a specific device token using FCM HTTP v1 REST API
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = [], string $eventType = 'general'): array
    {
        // 1. Check Master FCM Setting
        $fcmEnabled = SystemSetting::get('notification_fcm_enabled', true);
        if (!$fcmEnabled) {
            NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'skipped', null, 'FCM disabled in System Settings');
            return ['success' => false, 'message' => 'FCM push notifications are globally disabled in Admin settings.'];
        }

        // 2. Resolve Credentials
        $projectId = $this->getProjectId();
        if (empty($projectId)) {
            // Simulated fallback for test/dev when project ID not yet configured
            Log::info("FCM [Simulated Dispatch]: To: {$deviceToken} | Title: {$title} | Body: {$body}", $data);
            NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'delivered', ['simulated' => true], null);
            return ['success' => true, 'simulated' => true, 'message' => 'FCM simulated (Project ID unconfigured).'];
        }

        $accessToken = $this->getOAuth2AccessToken();
        if (!$accessToken) {
            NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'failed', null, 'Failed to obtain Google OAuth2 access token');
            return ['success' => false, 'message' => 'Failed to authenticate with Google Firebase OAuth2.'];
        }

        // 3. Format String Data Payload (FCM requires string values in data block)
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string)$k] = is_array($v) ? json_encode($v) : (string)$v;
        }
        $stringData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'courier_dispatch_alerts',
                        'sound' => 'default',
                        'notification_priority' => 'PRIORITY_MAX',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json; UTF-8',
            ])->timeout(8)->post($url, $payload);

            if ($response->successful()) {
                $respData = $response->json();
                NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'delivered', $respData, null);
                return ['success' => true, 'data' => $respData];
            }

            $errorData = $response->json();
            $status = $response->status();

            // Self-Pruning: Deactivate stale / uninstalled device tokens
            $errorCode = $errorData['error']['details'][0]['errorCode'] ?? ($errorData['error']['status'] ?? '');
            if ($status === 404 || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                UserDeviceToken::where('device_token', $deviceToken)->update(['is_active' => false]);
                Log::warning("FCM: Deactivated stale device token: {$deviceToken} (Status: {$status}, Code: {$errorCode})");
            }

            NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'failed', $errorData, "HTTP {$status}: " . $response->body());
            return ['success' => false, 'message' => $errorData['error']['message'] ?? 'FCM dispatch failed.'];
        } catch (\Throwable $e) {
            NotificationDeliveryLog::log('fcm', $deviceToken, $eventType, $title, 'failed', null, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send push notification to all active devices belonging to a user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = [], string $eventType = 'general'): array
    {
        $tokens = UserDeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('device_token');

        if ($tokens->isEmpty()) {
            return ['success' => false, 'delivered_count' => 0, 'message' => 'No active device tokens found for user.'];
        }

        $delivered = 0;
        foreach ($tokens as $token) {
            $res = $this->sendToToken($token, $title, $body, $data, $eventType);
            if (!empty($res['success'])) {
                $delivered++;
            }
        }

        return ['success' => $delivered > 0, 'delivered_count' => $delivered, 'total_tokens' => $tokens->count()];
    }

    /**
     * Resolve Firebase Project ID
     */
    public function getProjectId(): ?string
    {
        $id = SystemSetting::get('firebase_project_id');
        if (!empty($id)) return trim($id);

        $path = $this->getCredentialsPath();
        if ($path && file_exists($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (!empty($json['project_id'])) {
                return $json['project_id'];
            }
        }

        return config('services.firebase.project_id');
    }

    /**
     * Resolve Service Account Credentials JSON path
     */
    public function getCredentialsPath(): ?string
    {
        $configured = SystemSetting::get('firebase_credentials_path');
        if (!empty($configured) && file_exists($configured)) {
            return $configured;
        }

        $defaultPath = storage_path('app/firebase/service-account.json');
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        return null;
    }

    /**
     * Obtain Google OAuth2 Access Token via Service Account JWT Assertion
     */
    public function getOAuth2AccessToken(): ?string
    {
        return Cache::remember('vmarket_fcm_oauth2_token', 3300, function () {
            $clientEmail = SystemSetting::get('firebase_client_email');
            $privateKey = SystemSetting::get('firebase_private_key');

            // Fallback to credentials JSON file
            if (empty($clientEmail) || empty($privateKey)) {
                $path = $this->getCredentialsPath();
                if ($path && file_exists($path)) {
                    $json = json_decode(file_get_contents($path), true);
                    $clientEmail = $json['client_email'] ?? null;
                    $privateKey = $json['private_key'] ?? null;
                }
            }

            if (empty($clientEmail) || empty($privateKey)) {
                return null;
            }

            $now = Carbon::now()->timestamp;
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = base64_encode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]));

            $header = str_replace(['+', '/', '='], ['-', '_', ''], $header);
            $claims = str_replace(['+', '/', '='], ['-', '_', ''], $claims);

            $signatureInput = "{$header}.{$claims}";
            $signature = '';

            // Handle escaped newlines in private key string
            $formattedKey = str_replace('\n', "\n", $privateKey);

            $keyResource = openssl_pkey_get_private($formattedKey);
            if (!$keyResource) {
                Log::error('FCM OAuth2: Invalid private key format provided in System Settings.');
                return null;
            }

            openssl_sign($signatureInput, $signature, $keyResource, OPENSSL_ALGO_SHA256);
            $jwt = $signatureInput . '.' . str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'] ?? null;
            }

            Log::error('FCM OAuth2 token exchange failed: ' . $response->body());
            return null;
        });
    }
}
