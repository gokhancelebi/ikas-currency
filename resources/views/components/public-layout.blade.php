@props(['title' => null, 'card' => false])

@php
    $pageTitle = $title
        ? $title.' — '.config('app.name')
        : config('app.name');
    $homeUrl = auth()->check() ? route('products.index') : route('login');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-seo-noindex />
        <title>{{ $pageTitle }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <x-environment-warning />

        <div class="min-h-screen flex flex-col bg-gradient-to-b from-slate-50 via-white to-slate-100">
            <header class="w-full border-b border-gray-200/80 bg-white/90 backdrop-blur-sm">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
                    <a href="{{ $homeUrl }}" class="flex items-center gap-3 min-w-0">
                        <x-application-logo class="h-9 w-9 shrink-0" />
                        <span class="font-semibold text-gray-800 text-sm sm:text-base leading-tight truncate">
                            {{ config('app.name') }}
                        </span>
                    </a>

                    <div class="flex items-center gap-1 text-xs font-medium text-gray-500 shrink-0">
                        <a href="{{ route('locale.switch', 'tr') }}"
                           class="px-2 py-1 rounded {{ app()->getLocale() === 'tr' ? 'bg-indigo-100 text-indigo-800' : 'hover:bg-gray-100' }}">TR</a>
                        <span class="text-gray-300">|</span>
                        <a href="{{ route('locale.switch', 'en') }}"
                           class="px-2 py-1 rounded {{ app()->getLocale() === 'en' ? 'bg-indigo-100 text-indigo-800' : 'hover:bg-gray-100' }}">EN</a>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex flex-col items-center justify-center px-4 py-10 sm:py-14 w-full">
                @if ($card)
                    <div class="w-full sm:max-w-md bg-white shadow-md border border-gray-100 overflow-hidden sm:rounded-xl px-6 py-6">
                        {{ $slot }}
                    </div>
                @else
                    {{ $slot }}
                @endif
            </main>

            <footer class="py-5 text-center text-xs text-gray-400">
                {{ config('app.name') }}
            </footer>
        </div>

        @stack('scripts')
    </body>
</html>
