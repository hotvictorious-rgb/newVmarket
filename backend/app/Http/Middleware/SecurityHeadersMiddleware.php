<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces enterprise HTTP security headers and prevents clickjacking, MIME sniffing, and XSS.
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

        // Content Security Policy
        $csp = "default-src 'self'; "
            . "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://checkout.paystack.com; "
            . "img-src 'self' data: https://img.youtube.com https://images.unsplash.com https:; "
            . "script-src 'self' 'unsafe-inline' https://js.paystack.co; "
            . "connect-src 'self' https://api.paystack.co;";
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
