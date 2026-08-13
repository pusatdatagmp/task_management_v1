<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Deevatech') }}</title>

        {{-- Icon sistem (favicon) -- ganti bawaan Laravel yang kosong (public/favicon.ico
             0 byte, tidak pernah diisi). Aset dari public/logo_deevatech.png, di-crop
             cuma bagian ikon elang (wordmark "GARUDA Merah Putih" dibuang, tidak
             terbaca dikecilkan jadi ukuran tab browser). --}}
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">
        <link rel="apple-touch-icon" href="/favicon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
