<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        foreach (['customer-auth' => 10, 'customer-code' => 12] as $name => $maximum) {
            RateLimiter::for($name, function (Request $request) use ($maximum, $name) {
                return [
                    Limit::perMinute($maximum)->by($name.'|ip|'.$request->ip()),
                    Limit::perMinute(6)->by($name.'|email|'.hash('sha256', mb_strtolower(trim((string) ($request->user()?->email ?? $request->input('email')))))),
                    Limit::perMinute(6)->by($name.'|account|'.($request->user()?->id ?? $request->ip())),
                ];
            });
        }
        Vite::prefetch(concurrency: 3);
    }
}
