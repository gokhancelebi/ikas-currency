<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bilinen arama motoru / crawler User-Agent'larını engeller;
 * tüm yanıtlara X-Robots-Tag ekler (SITE_NOINDEX).
 */
class PreventSearchEngineIndexing
{
    /** @var list<string> */
    private const BOT_PATTERNS = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'sogou',
        'exabot',
        'facebot',
        'facebookexternalhit',
        'ia_archiver',
        'applebot',
        'twitterbot',
        'linkedinbot',
        'petalbot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'dotbot',
        'rogerbot',
        'screaming frog',
        'bytespider',
        'gptbot',
        'claudebot',
        'amazonbot',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.block_search_engine_bots', true)) {
            $userAgent = strtolower($request->userAgent() ?? '');

            foreach (self::BOT_PATTERNS as $pattern) {
                if (str_contains($userAgent, $pattern)) {
                    return response('Forbidden', Response::HTTP_FORBIDDEN)
                        ->header('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
                }
            }
        }

        $response = $next($request);

        if (config('security.noindex', true)) {
            $response->headers->set(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive, nosnippet',
                false
            );
        }

        return $response;
    }
}
