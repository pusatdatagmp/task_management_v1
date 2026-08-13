<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //Paksa semua URL/Route Laravel menggunakan protokol HTTPS
        URL::forceScheme('https');

        // BUSINESS RULE: F-69 — Carbon secara default SELALU mengonversi ke UTC
        // (suffix "Z") saat serialize ke JSON, APAPUN nilai config('app.timezone').
        // Tanpa override ini, tiap tanggal/datetime yang dikirim ke frontend via
        // Inertia props bergeser mundur 7 jam dari nilai aslinya — ditemukan saat
        // verifikasi manual: WorkSchedule.effective_from "2026-07-18 00:00" WIB
        // (tersimpan benar di DB) muncul sebagai "2026-07-17T17:00:00Z" di JSON,
        // yang kalau ditampilkan mentah di tabel jadi tanggal yang SALAH (mundur
        // 1 hari). Ini persis lubang yang F-69 coba hilangkan, cuma pindah lokasi
        // dari kalkulasi PHP ke serialisasi JSON.
        // NOTE: Carbon::serializeUsing() ditandai @deprecated oleh library (alasan:
        // "static setter bisa konflik antar library pihak ketiga") tapi TETAP jadi
        // cara resmi Laravel untuk override format JSON semua Carbon instance
        // secara global. Alternatif "getFactory()->serializeUsing()" TIDAK ADA
        // sebagai method statis (sudah dicoba, error runtime) — ini satu-satunya
        // cara yang benar-benar berfungsi untuk kebutuhan F-69.
        Carbon::serializeUsing(fn (Carbon $date) => $date->format('Y-m-d\TH:i:sP'));

        // BUSINESS RULE: F-85 (Hari-7) — satu-satunya isu performa nyata di skala
        // Boss (10 user, ~5rb task/tahun): N+1 query. Nol di 30 task seed, tapi di
        // 500 task satu halaman List View bisa jadi 500 query kalau ada relasi yang
        // lolos tanpa eager load. preventLazyLoading membuat lazy load MELEMPAR
        // exception di non-produksi (local/testing) — ketahuan saat DITULIS, bukan
        // saat tim mengeluh lambat nanti. Produksi tetap silent (tidak boleh crash
        // user asli gara-gara N+1 yang lolos, itu bug performa bukan bug fatal).
        Model::preventLazyLoading(! $this->app->isProduction());

        // BUSINESS RULE: F-90/RBAC §B4 — Gate::before menjadikan SEMUA
        // $user->can('xxx.yyy') native, langsung cek lewat User::hasPermission()
        // (yang baca role->permissions dari DB, F-88). SENGAJA tidak ada satu pun
        // Gate::define('task.manage', ...) per permission — nama permission murni
        // DATA dari tabel `permissions`/`role_permission`, bukan hardcode di kode.
        // Menambah permission baru = INSERT baris DB, BUKAN deploy kode baru.
        // Selalu return bool (bukan null) supaya Gate::before ini SATU-SATUNYA
        // sumber keputusan — tidak ada fallback ke Gate::define lain yang tidak
        // ada, yang akan membuat can() diam-diam selalu false untuk ability apa pun.
        Gate::before(fn (User $user, string $ability): bool => $user->hasPermission($ability));
    }
}
