<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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

        <style>
            :root { @foreach ($brandPalette->cssVariables() as $token => $value){{ $token }}:{{ $value }};@endforeach }
            html { color-scheme: light; background-color: #f7f8fa; }
        </style>

        <link rel="icon" href="/icons/te-pinta-logo.svg" type="image/svg+xml" sizes="any">
        <link rel="apple-touch-icon" href="/icons/te-pinta-logo-192.png">
        {{-- The admin PWA manifest is denied by the public gateway, so storefront pages do not link it. --}}
        @unless (str_starts_with($page['component'], 'public/'))
            <link rel="manifest" href="/manifest.webmanifest">
        @endunless
        <meta name="theme-color" content="{{ $brandPalette->accent }}">
        <meta name="application-name" content="{{ $restaurantName }}">
        <meta name="apple-mobile-web-app-title" content="{{ $restaurantName }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @unless (isset($publicSeo))
                <title>{{ $restaurantName }}</title>
            @endunless
        </x-inertia::head>
    </head>
    <body class="font-orders antialiased">
        <x-inertia::app />
    </body>
</html>
