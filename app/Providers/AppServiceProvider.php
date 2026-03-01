<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('taptoearn', function (Request $request): array {
            $userFingerprint = sha1((string) $request->input('init_data', 'anonymous'));
            $ip = $request->ip() ?? 'unknown_ip';

            return [
                Limit::perMinute(120)->by('taptoearn:user:'.$userFingerprint),
                Limit::perMinute(240)->by('taptoearn:ip:'.$ip),
            ];
        });
    }
}
