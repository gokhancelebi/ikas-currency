<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/products';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        $enabled = (bool) config('security.rate_limit_enabled', true);

        RateLimiter::for('api', function (Request $request) use ($enabled) {
            $perMinute = $enabled ? 60 : 10_000;

            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('web', function (Request $request) use ($enabled) {
            $perMinute = $enabled
                ? (int) config('security.web_per_minute', 120)
                : 10_000;

            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) use ($enabled) {
            $perMinute = $enabled
                ? (int) config('security.auth_per_minute', 20)
                : 10_000;

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        RateLimiter::for('cron', function (Request $request) use ($enabled) {
            $perMinute = $enabled
                ? (int) config('security.cron_refresh_per_minute', 10)
                : 10_000;

            return Limit::perMinute($perMinute)->by($request->ip());
        });
    }
}
