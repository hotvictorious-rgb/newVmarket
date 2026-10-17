<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeAndHardenInputMiddleware
{
    /**
     * Fields that must never be modified (passwords, tokens, and cryptographic signatures).
     */
    protected array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'signature',
        'x-paystack-signature',
        'payload_snapshot',
    ];

    /**
     * Multiline markdown fields that must preserve linebreaks, formatting, and tables
     * while still neutralizing malicious script tags, iframes, and dangerous protocols.
     */
    protected array $markdownFields = [
        'content',
        'draft_content',
    ];

    /**
     * Handle an incoming request.
     * Recursively sanitizes all input fields across all Web and API routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        if (!empty($input)) {
            $sanitized = $this->sanitizeArray($input);
            $request->replace($sanitized);
        }

        return $next($request);
    }

    /**
     * Recursively sanitize input array keys and values.
     */
    protected function sanitizeArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Strip null bytes or suspicious characters from keys
            $cleanKey = is_string($key) ? preg_replace('/[\x00-\x1F\x7F]/', '', $key) : $key;

            if (in_array(strtolower($cleanKey), $this->except, true)) {
                $result[$cleanKey] = $value;
                continue;
            }

            if (in_array(strtolower($cleanKey), $this->markdownFields, true)) {
                $result[$cleanKey] = is_string($value) ? $this->sanitizeMarkdown($value) : $value;
                continue;
            }

            if (is_array($value)) {
                $result[$cleanKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $result[$cleanKey] = $this->sanitizeString($value);
            } else {
                $result[$cleanKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Sanitize individual string values.
     */
    protected function sanitizeString(string $value): string
    {
        $wasNotEmpty = strlen(trim($value)) > 0;

        // 1. Strip null bytes
        $clean = str_replace(chr(0), '', $value);

        // 2. Strip invisible control and bidirectional override characters
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{202E}]/u', '', $clean) ?? $clean;

        // 3. Strip entire script, style, and iframe blocks completely (tags + inner malicious content)
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $clean) ?? $clean;

        // 4. Strip any remaining HTML tags
        $clean = strip_tags($clean);

        // 5. Neutralize dangerous pseudo-protocols
        $clean = preg_replace('/(javascript|vbscript|data\s*:\s*text\/html)\s*:/i', 'blocked:', $clean) ?? $clean;

        // 6. Normalize multiple consecutive spaces into a single space and trim
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        // If an input was non-empty malicious markup that got stripped to zero,
        // prevent it from converting to null so subsequent validators can reject it with 422.
        if ($wasNotEmpty && strlen($clean) === 0) {
            return '[blocked-markup]';
        }

        return $clean;
    }

    /**
     * Sanitize multiline markdown and document content.
     * Preserves newlines, lists, tables, and CommonMark formatting while neutralizing
     * embedded script tags, iframe tags, styles, and dangerous pseudo-protocols.
     */
    protected function sanitizeMarkdown(string $value): string
    {
        $wasNotEmpty = strlen(trim($value)) > 0;

        // 1. Strip null bytes
        $clean = str_replace(chr(0), '', $value);

        // 2. Strip invisible control characters (preserve newlines and tabs)
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{202E}]/u', '', $clean) ?? $clean;

        // 3. Strip entire script, style, and iframe blocks completely (tags + inner malicious content)
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $clean) ?? $clean;

        // 4. Neutralize dangerous pseudo-protocols
        $clean = preg_replace('/(javascript|vbscript|data\s*:\s*text\/html)\s*:/i', 'blocked:', $clean) ?? $clean;

        // 5. Ensure non-empty malicious markup that got stripped to zero is flagged
        if ($wasNotEmpty && strlen(trim($clean)) === 0) {
            return '[blocked-markup]';
        }

        return $clean;
    }
}
