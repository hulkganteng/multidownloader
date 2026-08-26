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
        // Enforce HTTPS when served behind Cloudflare Tunnel or reverse proxy
        if (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
            || isset($_SERVER['HTTP_CF_VISITOR'])
            || (request()->header('X-Forwarded-Proto') === 'https')
            || str_starts_with((string) config('app.url'), 'https://')
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
