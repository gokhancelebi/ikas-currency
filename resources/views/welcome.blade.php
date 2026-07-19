<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
        <meta http-equiv="refresh" content="0;url={{ route('login') }}">
        <title>{{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    </head>
    <body>
        <p><a href="{{ route('login') }}">{{ config('app.name') }}</a></p>
    </body>
</html>
