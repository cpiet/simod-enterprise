<?php

namespace App\Providers;

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
        // Force HTTPS and correct asset root when accessed via ngrok or reverse proxy
        if (
            request()->header('X-Forwarded-Proto') === 'https' ||
            str_contains(request()->header('X-Forwarded-Host') ?? '', 'ngrok') ||
            str_contains(request()->getHost(), 'ngrok')
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
