@if(\App\Rules\Turnstile::isEnabled())
    <div {{ $attributes->merge(['class' => 'mt-4']) }}>
        <div
            class="cf-turnstile"
            data-sitekey="{{ config('services.turnstile.site_key') }}"
        ></div>
        <x-input-error :messages="$errors->get('cf-turnstile-response')" class="mt-2" />
    </div>

    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce
@endif
