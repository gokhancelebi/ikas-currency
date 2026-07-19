<?php

namespace App\Lib\IkasSync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GraphqlClient
{
    public function __construct(
        private AuthTokenService $auth
    ) {
    }

    public function graphqlUrl(): string
    {
        return (string) config('ikas.graphql_url');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(array $payload): array
    {
        $throttle = (int) config('ikas.throttle_seconds', 1);
        if ($throttle > 0) {
            sleep($throttle);
        }

        $response = Http::withToken($this->auth->getAccessToken())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withOptions(['verify' => false])
            ->timeout(60)
            ->post($this->graphqlUrl(), $payload);

        if ($response->status() === 401) {
            $this->auth->forgetToken();
            $response = Http::withToken($this->auth->getAccessToken())
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withOptions(['verify' => false])
                ->timeout(60)
                ->post($this->graphqlUrl(), $payload);
        }

        $body = $response->json();
        if (! is_array($body)) {
            self::logProblem('İkas GraphQL invalid JSON response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['errors' => [['message' => 'Invalid JSON response']]];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function uploadImage(array $data): bool
    {
        $throttle = (int) config('ikas.throttle_seconds', 1);
        if ($throttle > 0) {
            sleep($throttle);
        }

        $response = Http::withToken($this->auth->getAccessToken())
            ->withOptions(['verify' => false])
            ->timeout(60)
            ->post((string) config('ikas.image_upload_url'), $data);

        return $response->successful();
    }

    public static function logProblem(string $message, array $context = []): void
    {
        Log::channel('single')->warning('[ikas-sync] '.$message, $context);
    }
}
