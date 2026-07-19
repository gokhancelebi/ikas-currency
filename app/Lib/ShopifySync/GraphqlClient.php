<?php

namespace App\Lib\ShopifySync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GraphqlClient
{
    public function storeDomain(): string
    {
        return (string) config('shopify.store_domain');
    }

    public function apiVersion(): string
    {
        return (string) config('shopify.api_version');
    }

    public function graphqlUrl(): string
    {
        return $this->storeDomain().'admin/api/'.$this->apiVersion().'/graphql.json';
    }

    /**
     * @param  string|array<string, mixed>  $payload
     */
    public function request(string|array $payload): string
    {
        $throttle = (int) config('shopify.throttle_seconds', 1);
        if ($throttle > 0) {
            sleep($throttle);
        }

        $body = is_array($payload) ? json_encode($payload) : $payload;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => (string) config('shopify.access_token'),
        ])
            ->withOptions(['verify' => false])
            ->withBody($body, 'application/json')
            ->post($this->graphqlUrl());

        return $response->body();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rest(string $path, array $data = [], string $method = 'GET'): string|false
    {
        $url = str_starts_with($path, 'http')
            ? $path
            : $this->storeDomain().ltrim($path, '/');

        $request = Http::withHeaders([
            'X-Shopify-Access-Token' => (string) config('shopify.access_token'),
        ])->withOptions(['verify' => false]);

        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => $request->get($url, $data),
        };

        if (! $response->successful()) {
            Log::channel('single')->warning('Shopify REST request failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return $response->body();
    }

    public static function logProblem(string $message, array $context = []): void
    {
        Log::channel('single')->warning('[shopify-sync] '.$message, $context);
    }
}
