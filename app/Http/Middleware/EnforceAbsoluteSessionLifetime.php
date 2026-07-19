<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Girişten SESSION_ABSOLUTE_LIFETIME dakika sonra oturumu kapatır (0 = kapalı).
 * Hareketsizlik için Laravel SESSION_LIFETIME kullanılır.
 */
class EnforceAbsoluteSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        $limitMinutes = (int) config('security.session_absolute_lifetime', 0);

        if ($limitMinutes <= 0 || ! Auth::check()) {
            return $next($request);
        }

        $startedAt = $request->session()->get('logged_in_at');

        if (! $startedAt) {
            $request->session()->put('logged_in_at', now()->timestamp);

            return $next($request);
        }

        if (now()->timestamp - (int) $startedAt >= $limitMinutes * 60) {
            $this->logoutUser($request);

            return redirect()->route('login')
                ->with('status', __('account.session_expired'));
        }

        return $next($request);
    }

    private function logoutUser(Request $request): void
    {
        $user = Auth::user();

        Auth::guard('web')->logout();

        if ($user) {
            $user->forceFill(['remember_token' => null])->save();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
