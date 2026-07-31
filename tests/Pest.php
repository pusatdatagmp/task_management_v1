<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// F-66 (HARDEN, Fase A): BusinessHoursCalculatorTest.php membangun WorkSchedule
// in-memory dengan effective_from terisi (dibutuhkan resolusi per-hari) -- cast
// 'date' Eloquent memanggil getConnection() SAAT SET ATTRIBUTE (fromDateTime() ->
// getDateFormat() -> getConnection()) walau modelnya TIDAK PERNAH disimpan atau
// di-query. Tanpa app di-boot, Model::$resolver statis kosong -> crash
// "Call to a member function connection() on null", bukan soal DB sungguhan.
// TestCase (TANPA RefreshDatabase) cukup: app boot supaya resolver ter-set, NOL
// migrate/seed/query nyata -- tests/Unit tetap murni logika kalkulator (F-57 H2 §C2).
pest()->extend(TestCase::class)
    ->in('Unit');

// BUSINESS RULE: F-83/C4 (Hari-7) — SearchTest.php dipindah ke tests/Search/
// (folder terpisah, LIHAT phpunit.xml testsuite "Search") supaya bisa didaftarkan
// dengan test case class BEDA: DatabaseMigrations, bukan RefreshDatabase.
//
// ALASAN: InnoDB FULLTEXT index di-update saat COMMIT. RefreshDatabase membungkus
// tiap test dalam transaction yang di-ROLLBACK di akhir — baris yang baru di-INSERT
// dalam transaction itu TERLIHAT oleh index B-tree biasa (baca dalam transaction
// yang sama), tapi TIDAK PERNAH terlihat oleh FULLTEXT index (butuh commit).
// Tanpa override ini, SETIAP test search MySQL gagal walau kodenya benar — bug
// lingkungan test, bukan bug aplikasi. DatabaseMigrations TIDAK membungkus
// transaction (migrate:fresh di awal, migrate:rollback di akhir tiap test), jadi
// data betul-betul ter-commit dan FULLTEXT index melihatnya.
//
// WORKAROUND folder terpisah (bukan cukup file spesifik di dalam tests/Feature):
// Pest MENOLAK 2 registrasi Tests\TestCase::class yang path-nya overlap untuk
// file yang sama ("TestCaseAlreadyInUse"), dan RefreshDatabase+DatabaseMigrations
// TIDAK BISA dipakai bersamaan di 1 class (keduanya redeclare method
// refreshTestDatabase() -> fatal trait method collision di PHP). Satu-satunya
// jalan aman: folder yang path-nya sama sekali tidak overlap dengan 'Feature'.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Search');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
