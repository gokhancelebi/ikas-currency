<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationEnabled
{
    /**
     * Kayıt .env ile kapalıysa /register isteklerini 404 ile reddet.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.registration_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
