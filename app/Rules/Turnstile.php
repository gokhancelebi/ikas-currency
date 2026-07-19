<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    /**
     * Turnstile is active only when enabled in .env and both keys are set.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled')
            && config('services.turnstile.site_key')
            && config('services.turnstile.secret_key');
    }

    /**
     * Validation rules for the Turnstile response field on auth forms.
     */
    public static function requestRules(): array
    {
        if (! self::isEnabled()) {
            return [];
        }

        return [
            'cf-turnstile-response' => ['required', new self],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isEnabled()) {
            return;
        }

        if (empty($value)) {
            $fail(__('validation.turnstile'));

            return;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->successful() || ! $response->json('success')) {
            $fail(__('validation.turnstile'));
        }
    }
}
