<?php

/**
 * ==========================================================
 * MODUL       : ArchiveActivityLogsCommandTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Pagar F-182 -- backup .log activity_logs bulanan. Fokus PALING
 *               PENTING: memastikan command ini TIDAK PERNAH menghapus baris dari
 *               DB (F-23 tetap utuh), murni menyalin ke file. Juga cakup batas
 *               bulan, isolasi organisasi (F-5), format --month, dan idempotency.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : ArchiveActivityLogsCommand, ActivityLog
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "jumlah baris DB tidak berkurang" adalah pagar SATU-SATUNYA
 *               untuk F-23 di fitur ini -- kalau lolos diam-diam, activity_logs
 *               bisa kehilangan data KPI (F-51) tanpa disadari.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

function makeArchiveLog(User $user, Carbon $createdAt, array $overrides = []): ActivityLog
{
    Carbon::setTestNow($createdAt);

    $log = ActivityLog::create(array_merge([
        'organization_id' => $user->organization_id,
        'user_id' => $user->id,
        'subject_type' => 'App\\Models\\Task',
        'subject_id' => 1,
        'event' => 'created',
        'properties' => ['old' => null, 'new' => ['title' => 'Contoh']],
    ], $overrides));

    Carbon::setTestNow();

    return $log;
}

function cleanupArchiveDir(int $organizationId): void
{
    File::deleteDirectory(storage_path("logs/activity-archive/{$organizationId}"));
}

test('F-182: archive bulan lalu menulis 1 file .log berisi persis baris bulan itu, bulan lain tidak ikut', function () {
    $admin = User::factory()->admin()->create();
    cleanupArchiveDir($admin->organization_id);

    $anchor = Carbon::create(2026, 8, 27, 10, 0, 0, 'Asia/Jakarta');
    $this->travelTo($anchor);

    // Bulan lalu (Juli) -- HARUS masuk arsip.
    $inJuly1 = makeArchiveLog($admin, Carbon::create(2026, 7, 5, 9, 0, 0, 'Asia/Jakarta'));
    $inJuly2 = makeArchiveLog($admin, Carbon::create(2026, 7, 31, 23, 59, 0, 'Asia/Jakarta'));

    // Bulan berjalan (Agustus) & bulan sebelum Juli (Juni) -- TIDAK BOLEH ikut.
    makeArchiveLog($admin, Carbon::create(2026, 8, 1, 0, 0, 0, 'Asia/Jakarta'));
    makeArchiveLog($admin, Carbon::create(2026, 6, 30, 23, 0, 0, 'Asia/Jakarta'));

    $this->artisan('activity-logs:archive')->assertSuccessful();

    $path = storage_path("logs/activity-archive/{$admin->organization_id}/2026-07.log");
    expect(File::exists($path))->toBeTrue();

    $lines = array_filter(explode(PHP_EOL, trim(File::get($path))));
    expect($lines)->toHaveCount(2);

    $ids = array_map(fn ($line) => json_decode($line, true)['id'], $lines);
    expect($ids)->toContain($inJuly1->id)
        ->and($ids)->toContain($inJuly2->id);

    cleanupArchiveDir($admin->organization_id);
});

test('F-182: SATU BARIS PUN TIDAK terhapus dari activity_logs setelah diarsipkan (F-23 tetap utuh)', function () {
    $admin = User::factory()->admin()->create();
    cleanupArchiveDir($admin->organization_id);

    makeArchiveLog($admin, Carbon::create(2026, 7, 10, 9, 0, 0, 'Asia/Jakarta'));
    makeArchiveLog($admin, Carbon::create(2026, 7, 15, 9, 0, 0, 'Asia/Jakarta'));

    $countBefore = ActivityLog::where('organization_id', $admin->organization_id)->count();

    $this->travelTo(Carbon::create(2026, 8, 27, 10, 0, 0, 'Asia/Jakarta'));
    $this->artisan('activity-logs:archive', ['--month' => '2026-07'])->assertSuccessful();

    $countAfter = ActivityLog::where('organization_id', $admin->organization_id)->count();

    expect($countAfter)->toBe($countBefore)
        ->and($countAfter)->toBe(2);

    cleanupArchiveDir($admin->organization_id);
});

test('F-182: dua organisasi tidak saling bocor -- masing-masing dapat file sendiri (F-5)', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    cleanupArchiveDir($adminA->organization_id);
    cleanupArchiveDir($adminB->organization_id);

    $logA = makeArchiveLog($adminA, Carbon::create(2026, 7, 10, 9, 0, 0, 'Asia/Jakarta'));
    $logB = makeArchiveLog($adminB, Carbon::create(2026, 7, 11, 9, 0, 0, 'Asia/Jakarta'));

    $this->artisan('activity-logs:archive', ['--month' => '2026-07'])->assertSuccessful();

    $pathA = storage_path("logs/activity-archive/{$adminA->organization_id}/2026-07.log");
    $pathB = storage_path("logs/activity-archive/{$adminB->organization_id}/2026-07.log");

    $contentA = File::get($pathA);
    $contentB = File::get($pathB);

    expect($contentA)->toContain('"id":'.$logA->id)
        ->and($contentA)->not->toContain('"id":'.$logB->id)
        ->and($contentB)->toContain('"id":'.$logB->id)
        ->and($contentB)->not->toContain('"id":'.$logA->id);

    cleanupArchiveDir($adminA->organization_id);
    cleanupArchiveDir($adminB->organization_id);
});

test('F-182: bulan tanpa data sama sekali -- tidak membuat file, command tetap sukses', function () {
    $admin = User::factory()->admin()->create();
    cleanupArchiveDir($admin->organization_id);

    $this->artisan('activity-logs:archive', ['--month' => '2020-01'])->assertSuccessful();

    expect(File::exists(storage_path("logs/activity-archive/{$admin->organization_id}/2020-01.log")))->toBeFalse();
});

test('F-182: dijalankan dua kali untuk bulan yang sama -- idempotent, isi file identik (bukan dobel)', function () {
    $admin = User::factory()->admin()->create();
    cleanupArchiveDir($admin->organization_id);

    makeArchiveLog($admin, Carbon::create(2026, 7, 10, 9, 0, 0, 'Asia/Jakarta'));

    $this->artisan('activity-logs:archive', ['--month' => '2026-07'])->assertSuccessful();
    $firstRun = File::get(storage_path("logs/activity-archive/{$admin->organization_id}/2026-07.log"));

    $this->artisan('activity-logs:archive', ['--month' => '2026-07'])->assertSuccessful();
    $secondRun = File::get(storage_path("logs/activity-archive/{$admin->organization_id}/2026-07.log"));

    expect($secondRun)->toBe($firstRun);

    cleanupArchiveDir($admin->organization_id);
});

test('F-182: format --month tidak valid ditolak dengan jelas, bukan crash', function () {
    $this->artisan('activity-logs:archive', ['--month' => 'bulan-lalu'])->assertFailed();
});
