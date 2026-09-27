<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (isset($publicSeo))
            <title data-public-seo>{{ $publicSeo['title'] }}</title>
            <meta data-public-seo name="description" content="{{ $publicSeo['description'] }}">
            <link data-public-seo rel="canonical" href="{{ $publicSeo['url'] }}">
            <meta data-public-seo property="og:type" content="website">
            <meta data-public-seo property="og:title" content="{{ $publicSeo['title'] }}">
            <meta data-public-seo property="og:description" content="{{ $publicSeo['description'] }}">
            <meta data-public-seo property="og:url" content="{{ $publicSeo['url'] }}">
            @if ($publicSeo['image'])
                <meta data-public-seo property="og:image" content="{{ $publicSeo['image'] }}">
            @endif
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/icons/te-pinta-logo.svg" type="image/svg+xml" sizes="any">
        <link rel="apple-touch-icon" href="/icons/te-pinta-logo-192.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#1f1d2b">
        <meta name="application-name" content="Te Pinta 3.0">
        <meta name="apple-mobile-web-app-title" content="Te Pinta">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @unless (isset($publicSeo))
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endunless
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
