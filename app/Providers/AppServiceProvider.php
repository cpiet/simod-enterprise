<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Multi-Domain & Multi-Hosting Portability:
        // Automatically detect active protocol (HTTP/HTTPS) and host from reverse proxy headers
        // (Nginx, aaPanel, Cloudflare, Load Balancer, Ngrok) without hardcoded domains.
        if (!$this->app->runningInConsole() && request()->getHost()) {
            $cfVisitor = request()->header('CF-Visitor', '');
            $isHttps = request()->isSecure()
                || strtolower((string) request()->header('X-Forwarded-Proto')) === 'https'
                || strtolower((string) request()->header('X-Forwarded-Ssl')) === 'on'
                || str_contains($cfVisitor, 'https')
                || str_contains((string) request()->header('X-Forwarded-Host'), 'ngrok')
                || str_contains(request()->getHost(), 'ngrok');

            if ($isHttps) {
                URL::forceScheme('https');
            }

            $scheme = $isHttps ? 'https' : request()->getScheme();
            $dynamicBaseUrl = $scheme . '://' . request()->getHttpHost();

            // Dynamically align public storage URL to the active host
            config(['filesystems.disks.public.url' => $dynamicBaseUrl . '/storage']);
        }
    }
}
