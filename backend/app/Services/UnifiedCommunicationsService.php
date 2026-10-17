<?php

namespace App\Services;

use App\Models\SystemSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UnifiedCommunicationsService
{
    /**
     * Standardize any Nigerian phone number to canonical international format (234...)
     */
    public static function formatNigerianPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            return '234' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '234')) {
            return $cleaned;
        } elseif (strlen($cleaned) === 10) {
            return '234' . $cleaned;
        }
        return $cleaned;
    }

    /**
     * Sequential SMS & WhatsApp Dispatch with Strict Single-Execution Failover Invariant:
     * - Never triggers multiple providers simultaneously (prevents duplicate costs and customer confusion).
     * - Checks .env configuration first, falls back to Admin Panel settings.
     * - Supported Nigerian Gateways: WhatsApp (Meta/Termii), Termii (DND), SmartSMS, EbulkSMS, KudiSMS, Sendchamp.
     */
    public function sendSmsWithFailover(string $phone, string $message, string $preferredChannel = 'sms'): array
    {
        $normalizedPhone = self::formatNigerianPhone($phone);
        $candidates = $this->resolveSmsCandidates($preferredChannel);

        $failoverHistory = [];

        foreach ($candidates as $gateway) {
            $gwName = $gateway['name'];
            $config = $gateway['config'];

            try {
                $result = $this->dispatchToGateway($gwName, $normalizedPhone, $message, $config);

                if ($result['success']) {
                    Log::info("SMS successfully dispatched via [{$gwName}]", [
                        'to' => $normalizedPhone,
                        'provider' => $gwName,
                        'provider_ref' => $result['reference'] ?? null,
                        'failover_count' => count($failoverHistory),
                    ]);

                    return [
                        'success' => true,
                        'provider' => $gwName,
                        'reference' => $result['reference'] ?? null,
                        'failover_history' => $failoverHistory,
                    ];
                }

                $failoverHistory[] = [
                    'provider' => $gwName,
                    'error' => $result['error'] ?? 'Gateway returned non-success response',
                ];
            } catch (\Throwable $e) {
                $failoverHistory[] = [
                    'provider' => $gwName,
                    'error' => $e->getMessage(),
                ];
                Log::warning("SMS Gateway [{$gwName}] failed: " . $e->getMessage() . ". Proceeding to sequential failover.");
            }
        }

        // If all live gateways failed or none configured, log gracefully (or simulation in test/local)
        Log::error("All SMS gateways exhausted during sequential failover", [
            'to' => $normalizedPhone,
            'failover_history' => $failoverHistory,
        ]);

        return [
            'success' => false,
            'message' => 'All configured SMS providers were attempted sequentially but failed.',
            'failover_history' => $failoverHistory,
        ];
    }

    /**
     * Resolve ordered candidate gateways from .env and Admin SystemSetting.
     */
    protected function resolveSmsCandidates(string $preferredChannel): array
    {
        $candidates = [];

        // 1. WhatsApp Priority if preferred
        if ($preferredChannel === 'whatsapp') {
            $waConfig = SystemSetting::get('whatsapp_config', []);
            if (!empty($waConfig['api_key']) || !empty(env('WHATSAPP_ACCESS_TOKEN'))) {
                $candidates[] = [
                    'name' => 'whatsapp_meta',
                    'config' => array_merge([
                        'token' => env('WHATSAPP_ACCESS_TOKEN', $waConfig['token'] ?? ''),
                        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', $waConfig['phone_number_id'] ?? ''),
                    ], $waConfig),
                ];
            }
        }

        // 2. Primary .env Gateway (Termii, Sendchamp, etc.)
        $envTermiiKey = \App\Models\SystemSetting::get('termii_api_key') ?: env('TERMII_API_KEY');
        if (!empty($envTermiiKey)) {
            $candidates[] = [
                'name' => 'termii',
                'config' => [
                    'api_key' => $envTermiiKey,
                    'sender' => env('TERMII_SENDER_ID', 'VMARKET'),
                    'channel' => env('TERMII_CHANNEL', 'dnd'),
                ],
            ];
        }

        // 3. Active Gateway from Admin SystemSetting
        $adminActive = SystemSetting::get('sms_active_gateway');
        $adminGateways = SystemSetting::get('sms_gateways', []);

        if (!empty($adminActive) && isset($adminGateways[$adminActive])) {
            $candidates[] = [
                'name' => $adminActive,
                'config' => $adminGateways[$adminActive],
            ];
        }

        // 4. Secondary Nigerian Fallbacks from Admin Settings (in priority sequence)
        $fallbackSequence = ['termii', 'smart_sms', 'ebulksms', 'kudisms', 'sendchamp'];
        foreach ($fallbackSequence as $gw) {
            if (isset($adminGateways[$gw]) && !empty($adminGateways[$gw]['is_active'])) {
                $candidates[] = [
                    'name' => $gw,
                    'config' => $adminGateways[$gw],
                ];
            }
        }

        // Deduplicate by name preserving order
        $unique = [];
        $seen = [];
        foreach ($candidates as $item) {
            if (!in_array($item['name'], $seen, true)) {
                $seen[] = $item['name'];
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /**
     * Dispatch to individual Nigerian gateway API
     */
    protected function dispatchToGateway(string $gateway, string $phone, string $message, array $config): array
    {
        return match ($gateway) {
            'termii' => $this->callTermii($phone, $message, $config),
            'smart_sms' => $this->callSmartSms($phone, $message, $config),
            'ebulksms' => $this->callEbulkSms($phone, $message, $config),
            'kudisms' => $this->callKudiSms($phone, $message, $config),
            'sendchamp' => $this->callSendchamp($phone, $message, $config),
            'whatsapp_meta' => $this->callWhatsAppMeta($phone, $message, $config),
            default => ['success' => false, 'error' => "Unsupported gateway: {$gateway}"],
        };
    }

    /**
     * Termii API Integration
     */
    protected function callTermii(string $phone, string $message, array $config): array
    {
        $apiKey = $config['api_key'] ?? (\App\Models\SystemSetting::get('termii_api_key') ?: env('TERMII_API_KEY'));
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Termii API Key missing'];
        }

        $sender = $config['sender'] ?? (\App\Models\SystemSetting::get('termii_sender_id') ?: env('TERMII_SENDER_ID', 'VMARKET'));
        $channel = $config['channel'] ?? 'dnd';

        $response = Http::timeout(8)->post('https://api.ng.termii.com/api/sms/send', [
            'to' => $phone,
            'from' => $sender,
            'sms' => $message,
            'type' => 'plain',
            'channel' => $channel,
            'api_key' => $apiKey,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'reference' => $response->json('message_id') ?? 'termii_' . uniqid(),
            ];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * SmartSMS Solutions Integration
     */
    protected function callSmartSms(string $phone, string $message, array $config): array
    {
        $token = $config['token'] ?? $config['api_key'] ?? env('SMARTSMS_TOKEN');
        if (empty($token)) {
            return ['success' => false, 'error' => 'SmartSMS Token missing'];
        }

        $sender = $config['sender_id'] ?? env('SMARTSMS_SENDER_ID', 'VMARKET');

        $response = Http::timeout(8)->post('https://app.smartsmssolutions.com/io/api/client/v1/sms/', [
            'token' => $token,
            'sender' => $sender,
            'to' => $phone,
            'message' => $message,
            'type' => 0,
            'routing' => 4, // Corporate DND Route
        ]);

        if ($response->successful()) {
            return ['success' => true, 'reference' => $response->json('message_id') ?? 'smart_' . uniqid()];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * EbulkSMS Integration
     */
    protected function callEbulkSms(string $phone, string $message, array $config): array
    {
        $username = $config['username'] ?? env('EBULKSMS_USERNAME');
        $apiKey = $config['api_key'] ?? env('EBULKSMS_API_KEY');

        if (empty($username) || empty($apiKey)) {
            return ['success' => false, 'error' => 'EbulkSMS credentials missing'];
        }

        $sender = $config['sender'] ?? env('EBULKSMS_SENDER_NAME', 'VMARKET');

        $response = Http::timeout(8)->post('https://api.ebulksms.com/sendsms.json', [
            'SMS' => [
                'auth' => ['username' => $username, 'apikey' => $apiKey],
                'message' => ['sender' => $sender, 'messagetext' => $message, 'flash' => '0'],
                'recipients' => ['gsm' => [['msidn' => $phone]]],
            ],
        ]);

        if ($response->successful() && str_contains($response->body(), 'SUCCESS')) {
            return ['success' => true, 'reference' => 'ebulk_' . uniqid()];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * KudiSMS Integration
     */
    protected function callKudiSms(string $phone, string $message, array $config): array
    {
        $token = $config['token'] ?? env('KUDISMS_TOKEN');
        if (empty($token)) {
            return ['success' => false, 'error' => 'KudiSMS Token missing'];
        }

        $sender = $config['sender'] ?? env('KUDISMS_SENDER', 'VMARKET');

        $response = Http::timeout(8)->post('https://kudisms.net/api/sms', [
            'token' => $token,
            'sender' => $sender,
            'recipient' => $phone,
            'message' => $message,
        ]);

        if ($response->successful()) {
            return ['success' => true, 'reference' => 'kudisms_' . uniqid()];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * Sendchamp Integration
     */
    protected function callSendchamp(string $phone, string $message, array $config): array
    {
        $pubKey = $config['public_key'] ?? env('SENDCHAMP_PUBLIC_KEY');
        if (empty($pubKey)) {
            return ['success' => false, 'error' => 'Sendchamp Key missing'];
        }

        $sender = $config['sender_name'] ?? env('SENDCHAMP_SENDER', 'Sendchamp');

        $response = Http::timeout(8)->withHeaders([
            'Authorization' => 'Bearer ' . $pubKey,
            'Accept' => 'application/json',
        ])->post('https://api.sendchamp.com/v1/sms/send', [
            'to' => [$phone],
            'message' => $message,
            'sender_name' => $sender,
            'route' => 'dnd',
        ]);

        if ($response->successful()) {
            return ['success' => true, 'reference' => 'sendchamp_' . uniqid()];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * WhatsApp Meta Cloud API Integration
     */
    protected function callWhatsAppMeta(string $phone, string $message, array $config): array
    {
        $token = $config['token'] ?? env('WHATSAPP_ACCESS_TOKEN');
        $phoneId = $config['phone_number_id'] ?? env('WHATSAPP_PHONE_NUMBER_ID');

        if (empty($token) || empty($phoneId)) {
            return ['success' => false, 'error' => 'WhatsApp Meta credentials missing'];
        }

        $response = Http::timeout(8)->withToken($token)->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        if ($response->successful()) {
            return ['success' => true, 'reference' => 'wa_' . uniqid()];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * Sequential Email Dispatch with Strict Single-Execution Failover Invariant:
     * - Attempt 1: PHP sendmail (configured in .env / local server MTA)
     * - Attempt 2: Admin Panel SMTP (configured in SystemSetting)
     * - Never fires both simultaneously.
     */
    public function sendEmailWithFailover(string $toEmail, string $subject, string $htmlContent): array
    {
        $failoverHistory = [];

        // Attempt 1: Default .env Mailer (e.g. sendmail)
        try {
            Mail::html($htmlContent, function ($msg) use ($toEmail, $subject) {
                $msg->to($toEmail)
                    ->subject($subject);
            });

            Log::info("Email dispatched successfully via primary mailer (.env)");
            return [
                'success' => true,
                'driver' => config('mail.default', 'sendmail'),
                'failover_history' => [],
            ];
        } catch (\Throwable $e) {
            $failoverHistory[] = [
                'driver' => config('mail.default', 'sendmail'),
                'error' => $e->getMessage(),
            ];
            Log::warning("Primary mailer failed: " . $e->getMessage() . ". Initiating SMTP failover.");
        }

        // Attempt 2: Admin Panel SMTP Failover (configured in SystemSetting)
        $smtpConfig = SystemSetting::get('mail_smtp_config');

        if (!empty($smtpConfig) && !empty($smtpConfig['host'])) {
            try {
                config([
                    'mail.mailers.admin_fallback_smtp' => [
                        'transport' => 'smtp',
                        'host' => $smtpConfig['host'],
                        'port' => (int)($smtpConfig['port'] ?? 587),
                        'encryption' => $smtpConfig['encryption'] ?? 'tls',
                        'username' => $smtpConfig['username'] ?? '',
                        'password' => $smtpConfig['password'] ?? '',
                        'timeout' => 10,
                    ],
                ]);

                Mail::mailer('admin_fallback_smtp')->html($htmlContent, function ($msg) use ($toEmail, $subject, $smtpConfig) {
                    $fromAddress = $smtpConfig['from_address'] ?? 'noreply@victoriousmarket.com.ng';
                    $fromName = $smtpConfig['from_name'] ?? 'Victorious Market';

                    $msg->to($toEmail)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
                });

                Log::info("Email dispatched successfully via fallback Admin SMTP");
                return [
                    'success' => true,
                    'driver' => 'admin_fallback_smtp',
                    'failover_history' => $failoverHistory,
                ];
            } catch (\Throwable $smtpEx) {
                $failoverHistory[] = [
                    'driver' => 'admin_fallback_smtp',
                    'error' => $smtpEx->getMessage(),
                ];
                Log::error("Admin fallback SMTP also failed: " . $smtpEx->getMessage());
            }
        }

        return [
            'success' => false,
            'message' => 'All configured mail transports failed sequentially.',
            'failover_history' => $failoverHistory,
        ];
    }
}