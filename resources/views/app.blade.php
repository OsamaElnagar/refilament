<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- The v1 shipping root view (docs/ARCHITECTURE.md → "Frontend
             delivery"): refilament pages render through this view so they
             boot the package's own prebuilt bundle, never the consumer's.
             Two modes, chosen per request by what exists on disk:
               - consumer: vendor:publish --tag=refilament-assets copied the
                 bundle to public/vendor/refilament → plain <link>/<script>.
               - workbench/dev: no published assets → the package's own
                 Vite build/manifest via @vite (npm run dev / npm run build). --}}
        @php
            $publishedAssets = file_exists(public_path('vendor/refilament/refilament.js'));

            // Cache-bust the published bundle: the asset URL is otherwise
            // stable, so a republished bundle would keep serving from the
            // browser cache. Appending the file's mtime forces a refetch
            // exactly when the file changes.
            $assetVersion = $publishedAssets
                ? (string) filemtime(public_path('vendor/refilament/refilament.js'))
                : null;
        @endphp

        @if ($publishedAssets)
            <link rel="stylesheet" href="{{ asset('vendor/refilament/refilament.css').($assetVersion !== null ? '?v='.$assetVersion : '') }}">
        @else
            @viteReactRefresh
            @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @endif

        <x-inertia::head>
            <title>{{ config('app.name', 'Refilament') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        @if ($publishedAssets)
            <script src="{{ asset('vendor/refilament/refilament.js').($assetVersion !== null ? '?v='.$assetVersion : '') }}" defer></script>
        @endif
    </body>
</html>
