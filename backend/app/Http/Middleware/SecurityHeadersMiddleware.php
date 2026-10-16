<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces enterprise HTTP security headers and adapts dynamically to local and live production environments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove revealing server technology signatures
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        // Core Zero-Trust Security Headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');

        $isProduction = app()->environment('production') || str_starts_with(config('app.url', ''), 'https://');

        // Dynamic origin whitelisting:
        // In local/testing environments: include dev hostnames and ports.
        // In production: dynamically whitelist 'self' and the configured production APP_URL.
        $devHosts = !$isProduction
            ? ' http://127.0.0.1:8080 http://localhost:8080 http://localhost:3000'
            : '';

        $evalDirective = !$isProduction ? " 'unsafe-eval'" : "";

        // Content Security Policy
        $csp = "default-src 'self' 'unsafe-inline'{$devHosts}; "
            . "style-src 'self' 'unsafe-inline'{$devHosts} https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
            . "font-src 'self' data:{$devHosts} https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
            . "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://checkout.paystack.com; "
            . "img-src 'self' data: blob:{$devHosts} https://img.youtube.com https://images.unsplash.com https:; "
            . "script-src 'self' 'unsafe-inline'{$evalDirective}{$devHosts} https://js.paystack.co https://cdnjs.cloudflare.com; "
            . "connect-src 'self'{$devHosts} https://api.paystack.co;";

        $response->headers->set('Content-Security-Policy', $csp);

        // On live production: enforce HTTP Strict Transport Security (HSTS)
        if ($isProduction && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}