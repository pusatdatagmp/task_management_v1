<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // F-90/RBAC §D4: alias 'admin' (EnsureUserIsAdmin) DIHAPUS — semua route
        // admin-only sudah pindah ke middleware bawaan Laravel `can:xxx`
        // (routes/admin.php), digerbangi Gate::before -> User::hasPermission()
        // (AppServiceProvider). Tidak ada lagi konsep "admin blanket", murni
        // permission per aksi.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
