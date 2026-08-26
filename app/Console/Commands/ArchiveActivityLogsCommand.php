<?php

/**
 * ==========================================================
 * MODUL       : ArchiveActivityLogsCommand
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-182 (permintaan Boss 2026-08-27) — cadangan BULANAN activity_logs
 *               ke file .log per organisasi, SUPAYA data lama tetap bisa dibaca
 *               manual di luar database. TIDAK MENGHAPUS satu baris pun dari
 *               activity_logs — F-23 (immutable selamanya) & F-51 (sumber 4/6
 *               metrik KPI) TETAP UTUH. Ini murni EXPORT/salinan, bukan pemindahan
 *               data (lihat RISIKO di bawah soal kenapa beban DB tidak berkurang).
 * DIPANGGIL   : routes/console.php (Schedule::command, monthlyOn(1) WIB),
 *               manual `php artisan activity-logs:archive` / `--month=Y-m`
 * MEMANGGIL   : Organization, ActivityLog
 * DATA MASUK  : activity_logs milik SATU BULAN (default: bulan lalu WIB, F-69) per organisasi
 * DATA KELUAR : storage/logs/activity-archive/{organization_id}/{Y-m}.log (NDJSON,
 *               1 baris = 1 event activity_logs, format sama Model::toArray())
 * RISIKO      : F-23 secara SADAR TIDAK dibongkar (keputusan Boss 2026-08-27) --
 *               artinya file ini HANYA salinan, baris ASLI tetap di database.
 *               Beban tabel activity_logs TIDAK berkurang oleh command ini --
 *               kalau nanti Boss butuh pengurangan beban NYATA (hapus baris lama
 *               dari DB), itu keputusan TERPISAH yang sengaja BELUM diambil karena
 *               melanggar F-23/F-51 (lihat audit 2026-08-27, JANGAN diam-diam
 *               ditambahkan tanpa persetujuan eksplisit Boss). Overwrite file bulan
 *               yang SAMA aman diulang (idempotent) -- bulan yang sudah lewat
 *               datanya tidak pernah berubah lagi (F-23), jadi isi file re-generate
 *               selalu identik.
 * ==========================================================
 */

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ArchiveActivityLogsCommand extends Command
{
    protected $signature = 'activity-logs:archive {--month= : Bulan target format Y-m, default bulan lalu (WIB)}';

    protected $description = 'Ekspor activity_logs satu bulan ke file .log per organisasi (backup, F-23 tidak disentuh)';

    public function handle(): int
    {
        $monthOption = $this->option('month');

        if ($monthOption) {
            try {
                $target = Carbon::createFromFormat('Y-m', $monthOption, 'Asia/Jakarta')->startOfMonth();
            } catch (\Throwable) {
                $this->error("Format --month tidak valid: \"{$monthOption}\" -- gunakan Y-m, contoh 2026-07.");

                return self::FAILURE;
            }
        } else {
            // F-69: WIB eksplisit. Default BULAN LALU -- bulan berjalan belum
            // "tertutup" (masih bisa nambah baris baru sepanjang bulan ini).
            $target = Carbon::now('Asia/Jakarta')->subMonthNoOverflow()->startOfMonth();
        }

        $monthKey = $target->format('Y-m');
        $start = $target->copy()->startOfMonth();
        $end = $target->copy()->endOfMonth();

        $totalRows = 0;
        $totalOrgs = 0;

        foreach (Organization::all() as $organization) {
            $rowsWritten = $this->archiveOrganizationMonth($organization->id, $monthKey, $start, $end);

            if ($rowsWritten > 0) {
                $totalOrgs++;
                $totalRows += $rowsWritten;
            }
        }

        $this->info("Arsip {$monthKey} selesai. Organisasi berisi data: {$totalOrgs}. Total baris: {$totalRows}.");

        return self::SUCCESS;
    }

    /**
     * KONTRAK: 1 file NDJSON per organisasi per bulan (storage/logs/activity-archive/
     * {org_id}/{Y-m}.log). chunkById (F-85) -- bulan padat tidak boleh memuat semua
     * baris ke memori sekaligus, sesuai alasan fitur ini dibuat (data sudah banyak).
     * File LAMA di path yang sama DITIMPA (idempotent -- lihat RISIKO header).
     *
     * @return int jumlah baris yang ditulis (0 = tidak ada data bulan itu, file tidak dibuat)
     */
    private function archiveOrganizationMonth(int $organizationId, string $monthKey, Carbon $start, Carbon $end): int
    {
        $hasRows = ActivityLog::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->exists();

        if (! $hasRows) {
            return 0;
        }

        $directory = storage_path("logs/activity-archive/{$organizationId}");
        File::ensureDirectoryExists($directory);

        $path = "{$directory}/{$monthKey}.log";
        $handle = fopen($path, 'w');
        $count = 0;

        ActivityLog::where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id')
            ->chunkById(500, function ($logs) use ($handle, &$count) {
                foreach ($logs as $log) {
                    // toArray() REUSE serialisasi model (SerializesDatesInAppTimezone,
                    // F-72) -- created_at di file SAMA format WIB dengan yang tampil
                    // di halaman Log Activity, bukan format ulang manual.
                    fwrite($handle, json_encode($log->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
                    $count++;
                }
            });

        fclose($handle);

        return $count;
    }
}
