@php
    $homeUrl = auth()->check() ? route('products.index') : route('login');
    $homeLabel = auth()->check() ? __('errors.back_home') : __('errors.back_login');
@endphp

<div class="w-full max-w-lg text-center">
    <p class="text-7xl sm:text-8xl font-bold tracking-tight text-indigo-600/90 tabular-nums">
        {{ $code }}
    </p>

    <h1 class="mt-4 text-2xl sm:text-3xl font-semibold text-gray-900">
        {{ $heading }}
    </h1>

    <p class="mt-3 text-sm sm:text-base text-gray-600 leading-relaxed">
        {{ $message }}
    </p>

    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
        @if (! empty($showRefresh))
            <button type="button"
                    onclick="window.location.reload()"
                    class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('errors.refresh') }}
            </button>
        @endif

        <a href="{{ $homeUrl }}"
           class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            {{ $homeLabel }}
        </a>
    </div>
</div>
