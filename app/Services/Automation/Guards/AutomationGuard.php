<?php

/**
 * ==========================================================
 * MODUL       : AutomationGuard
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Kontrak Guard komposabel (F-158) — tiap Guard menjawab SATU syarat
 *               "boleh generate?" tanpa tahu apa-apa soal Guard lain di rantai.
 *               Tambah syarat baru = tambah 1 class implement ini + sisip ke rantai
 *               Pipeline, NOL rewrite Guard lain.
 * DIPANGGIL   : Pipeline (dipanggil berurutan, Skip pertama menghentikan rantai)
 * MEMANGGIL   : (implementasi masing-masing) TaskTemplate, AutomationContext
 * DATA MASUK  : TaskTemplate yang dievaluasi + AutomationContext (data preload F-85)
 * DATA KELUAR : null (Pass, lanjut ke Guard berikutnya) ATAU Decision (Skip, rantai berhenti)
 * RISIKO      : Implementasi TIDAK BOLEH query DB per template di sini — semua data
 *               WAJIB sudah ada di AutomationContext (F-85), lihat RISIKO di sana.
 * ==========================================================
 */

namespace App\Services\Automation\Guards;

use App\Models\TaskTemplate;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Decision;

interface AutomationGuard
{
    public function check(TaskTemplate $template, AutomationContext $ctx): ?Decision;
}
