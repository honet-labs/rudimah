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
        // Enforce skema HTTPS bila aplikasi diakses melalui reverse proxy / tunnel SSL atau APP_URL berawalan https://
        if (
            str_starts_with(config('app.url', ''), 'https://') ||
            request()->header('x-forwarded-proto') === 'https' ||
            request()->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
            request()->server('HTTPS') === 'on'
        ) {
            URL::forceScheme('https');
        }
    }
}
