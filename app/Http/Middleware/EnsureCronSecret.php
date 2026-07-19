<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /cron-refresh gibi dış cron uçları — CRON_SECRET query token zorunlu.
 */
class EnsureCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('app.cron_secret', '');

        if ($secret === '' || ! hash_equals($secret, (string) $request->query('token', ''))) {
            abort(404);
        }

        return $next($request);
    }
}
