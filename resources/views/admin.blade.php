@php
    $locale = app()->getLocale();
    $boot = [
        'locale' => $locale,
        'locales' => config('app.locales'),
        'appName' => config('app.name'),
        // Changes whenever a language file changes, so the browser never keeps old texts.
        'translationsVersion' => substr(md5(collect(\Illuminate\Support\Facades\File::allFiles(lang_path()))->map(fn ($file) => $file->getMTime())->implode(',')), 0, 10),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:opsz,wght@14..32,400..700&family=Noto+Sans+SC:wght@400;500;600;700&display=swap">
    <script>
        window.__ADMIN__ = @json($boot);
        // The saved theme is applied before the page draws, so a dark page never flashes white.
        try {
            var theme = localStorage.getItem('admin-theme');
            if (theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    @viteReactRefresh
    @vite(['resources/css/admin.css', 'resources/js/admin/main.tsx'])
</head>
<body class="bg-canvas text-ink antialiased">
    <div id="root"></div>
</body>
</html>
