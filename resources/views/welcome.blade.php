<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ url('/app') }}">
    <title>{{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f7f8fa; color: #1f2430; }
        a { color: #3b4fd8; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    {{-- The admin application lives at /app; the home page sends every visitor there. --}}
    <a href="{{ url('/app') }}">{{ config('app.name') }} →</a>
</body>
</html>
