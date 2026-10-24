<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionSecurityMiddleware
{
    /**
     * Handle session security: inactivity timeout, IP/UA anti-hijack binding, and 2FA gate.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user || !$user->isSuperAdmin()) {
            return $next($request);
        }

        $session = $request->session();
        $now = now();

        // 1. Anti-Hijack IP & User-Agent Verification
        $currentIp = (string) ($request->ip() ?? '127.0.0.1');
        $currentUaHash = hash('sha256', (string) $request->userAgent());

        $sessionIp = $session->get('admin_session_ip');
        $sessionUa = $session->get('admin_session_ua');

        if ($sessionIp && $sessionIp !== $currentIp) {
            AdminAuditLog::create([
                'user_id' => $user->id,
                'action' => 'super_admin_session_hijack_ip_mismatch',
                'target_type' => get_class($user),
                'target_id' => $user->id,
                'old_values' => ['ip' => $sessionIp],
                'new_values' => ['ip' => $currentIp],
                'ip_address' => $currentIp,
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['login' => 'Security Warning: Session invalidated due to network address change.']);
        }

        if ($sessionUa && $sessionUa !== $currentUaHash) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['login' => 'Security Warning: Session invalidated due to client browser mutation.']);
        }

        // Initialize session IP/UA on first check
        if (!$sessionIp) {
            $session->put('admin_session_ip', $currentIp);
        }
        if (!$sessionUa) {
            $session->put('admin_session_ua', $currentUaHash);
        }

        // 2. Inactivity Timeout Verification
        $timeoutMinutes = (int) config('auth.admin_session_lifetime_minutes', 15);
        $lastActivityTimestamp = $session->get('admin_last_activity_at');

        if ($lastActivityTimestamp && ($now->timestamp - $lastActivityTimestamp) > ($timeoutMinutes * 60)) {
            AdminAuditLog::create([
                'user_id' => $user->id,
                'action' => 'super_admin_session_inactivity_timeout',
                'target_type' => get_class($user),
                'target_id' => $user->id,
                'old_values' => ['last_active' => date('Y-m-d H:i:s', $lastActivityTimestamp)],
                'new_values' => ['timed_out_at' => $now->toDateTimeString()],
                'ip_address' => $currentIp,
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['login' => "Your administrative session was terminated after {$timeoutMinutes} minutes of inactivity."]);
        }

        $session->put('admin_last_activity_at', $now->timestamp);

        // 3. Super Admin 2FA Verification Gate
        $is2FaEnabled = (bool) config('auth.admin_2fa_enabled', false);
        if ($is2FaEnabled) {
            $is2FaVerified = (bool) $session->get('admin_2fa_verified', false);
            // Allow access to 2FA verification routes and logout
            if (!$is2FaVerified && !$request->routeIs('admin.verify_2fa', 'admin.verify_2fa.post', 'admin.resend_2fa', 'admin.logout', 'admin.login')) {
                return redirect()->route('admin.verify_2fa');
            }
        }

        return $next($request);
    }
}
