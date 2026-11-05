<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureIngestToken;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*') === '*' ? '*' : array_map('trim', explode(',', (string) env('TRUSTED_PROXIES'))),
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->alias([
            'ingest.token' => EnsureIngestToken::class,
            'idle'         => EnforceSessionTimeout::class,
            'module'       => EnsureModulePermission::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            PreventBackHistory::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Halaman galat resmi (Inertia) bila debug mati (produksi); saat debug tetap halaman diagnosa Laravel.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();
            if (config('app.debug') || ! in_array($status, [403, 404, 419, 429, 500, 503], true) || $request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            return \Inertia\Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
        });
    })->create();
