<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan standar untuk semua respons web: anti-clickjacking, anti-MIME-sniffing, kebijakan referrer,
 * pembatasan fitur peramban, dan HSTS (hanya bila koneksi HTTPS). CSP sengaja hanya memuat arahan yang tidak
 * membatasi skrip/gaya (Vite & Inertia memakai inline), yaitu frame-ancestors, base-uri, form-action, object-src.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");

        if ($request->isSecure() && ! app()->environment(['local', 'testing'])) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
