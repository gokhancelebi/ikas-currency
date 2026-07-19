<?php

namespace App\Lib\IkasSync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AuthTokenService
{
    private const CACHE_KEY = 'ikas_access_token';

    public function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->refreshAccessToken();
    }

    public function refreshAccessToken(): string
    {
        $clientId = (string) config('ikas.client_id');
        $clientSecret = (string) config('ikas.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('IKAS_CLIENT_ID and IKAS_CLIENT_SECRET are required.');
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post((string) config('ikas.oauth_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('İkas OAuth token request failed: '.$response->body());
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            throw new RuntimeException('İkas OAuth response missing access_token.');
        }

        $expiresIn = (int) $response->json('expires_in', 14400);
        $ttl = max(60, $expiresIn - 60);
        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
