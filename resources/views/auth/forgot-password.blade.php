<x-guest-layout :title="__('account.forgot_password')">
    <div class="mb-4 text-sm text-gray-600">
        {{ __('account.forgot_password_intro') }}
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('account.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-turnstile />

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('account.send_reset_link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
