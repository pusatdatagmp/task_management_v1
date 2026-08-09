<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// CATATAN: `Throwable` SENGAJA tidak di-`use` -- built-in interface global
// (root namespace), file ini pun TANPA `namespace` sendiri, jadi `use
// Throwable;` cuma memicu PHP warning "non-compound name has no effect"
// (ketahuan dari 463 test yang tiba-tiba WARN, F-4).

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
        // Permintaan Boss (2026-08-07): halaman error 403/404/500 kustom lewat
        // Inertia (resources/js/pages/errors/error.tsx), gantikan halaman
        // default Laravel/Symfony yang polos.
        //
        // GUARD 1: $request->expectsJson() DIKECUALIKAN -- endpoint JSON/API
        // (mis. dashboard.command-center yang dites via getJson() di banyak
        // Feature test) WAJIB tetap dapat body JSON asli, bukan HTML/Inertia.
        // Inertia sendiri set Accept: text/html (bukan application/json) jadi
        // navigasi SPA normal TIDAK kena guard ini.
        //
        // GUARD 2: 500 HANYA di-custom kalau app.debug OFF -- di lokal
        // (APP_DEBUG=true) developer TETAP lihat Whoops/Ignition asli (stack
        // trace) buat debug, bukan halaman generik yang menyembunyikan error.
        // 403/404 SELALU custom (bukan bug kode, aman ditampilkan kapan pun).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $hidingRealServerError = $status === 500 && config('app.debug');

            if (! in_array($status, [403, 404, 500], true) || $hidingRealServerError) {
                return $response;
            }

            return Inertia::render('errors/error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
