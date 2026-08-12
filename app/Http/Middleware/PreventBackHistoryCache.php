<?php

/**
 * ==========================================================
 * MODUL       : PreventBackHistoryCache
 * KLASIFIKASI : CONFIG
 * TUJUAN      : Cegah browser (bfcache/back-forward cache) menyimpan snapshot
 *               halaman ter-autentikasi -- tanpa ini, tombol Back browser
 *               setelah logout menampilkan halaman lama (mis. Dashboard) dari
 *               memori TANPA request ulang ke server, seolah masih login.
 *               Baru hilang setelah user refresh manual (yang memicu request
 *               baru -> kena middleware `auth` -> redirect login).
 * DIPANGGIL   : bootstrap/app.php (grup middleware 'web', SEMUA route HTML)
 * MEMANGGIL   : -
 * DATA MASUK  : Response dari route handler berikutnya di pipeline
 * DATA KELUAR : Response yang sama + header Cache-Control/Pragma no-store
 * RISIKO      : Kalau header ini hilang/tidak terpasang, bug back-button
 *               setelah logout muncul lagi (data sensitif tim/KPI sempat
 *               terlihat sebelum redirect).
 * ==========================================================
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistoryCache
{
    /**
     * SUMBER: Cache-Control: no-store adalah sinyal yang dipakai Chrome/Firefox
     * untuk MENGECUALIKAN halaman dari bfcache (beda dari HTTP cache biasa) --
     * begitu header ini ada, tombol Back/Forward browser SELALU minta ulang ke
     * server alih-alih menampilkan snapshot lama dari memori. Dipasang GLOBAL
     * ke semua halaman (bukan cuma route ber-auth) supaya konsisten & tidak
     * perlu didaftarkan ulang tiap kali ada route baru.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
