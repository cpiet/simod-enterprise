<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Handle an incoming request:
     * 1. Enforce post-logout session lock if user explicitly logged out.
     * 2. Inject anti-cache HTTP headers to prevent browser Back-button history replay.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If user explicitly logged out and has not re-authenticated, redirect to login
        if (
            !$request->routeIs('login', 'login.post', 'logout') &&
            $request->hasSession() &&
            $request->session()->get('simod_logged_out') === true &&
            !Auth::check()
        ) {
            return redirect()->route('login');
        }

        $response = $next($request);

        if (isset($response->headers)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT');
        }

        return $response;
    }
}

