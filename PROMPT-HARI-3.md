# PROMPT HARI-3 — TZ FIX + CRUD ADMIN

> **Isi:** Fase A perbaikan timezone (~30 mnt) · Work Schedule CRUD · Project CRUD · Task Status CRUD
> **Masih belum:** Task CRUD (Hari-4) · List View (Hari-5) · Search & notifikasi (Hari-6)

---

## §0. YANG BOSS LAKUKAN DULU

**Salin ulang 4 file yang Jarvis perbarui** ke folder proyek (menimpa yang lama):
```
CLAUDE.md                    <- TZ diubah ke Asia/Jakarta
docs/01-PRD.md               <- idem
docs/02-DATA-MODEL.md        <- idem
docs/04-FINDING-REGISTRY.md  <- +F-69, F-70, F-71 + catatan audit Hari-2
```

> **Kenapa Jarvis yang mengubah dokumen, bukan Claude Code?**
> Dokumen adalah **ground truth**; kode mengikuti dokumen. Kalau Claude Code boleh mengedit spec-nya sendiri, dia bisa "menyesuaikan" spec agar cocok dengan kodenya — bukan sebaliknya. Jarvis pegang dokumen, Claude Code pegang kode.

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Dokumen SUDAH DIPERBARUI Boss. Baca ulang utuh, jangan andalkan ingatan sesi lalu:
1. CLAUDE.md              <- TZ berubah jadi Asia/Jakarta (F-69)
2. docs/01-PRD.md
3. docs/02-DATA-MODEL.md
4. docs/03-BUSINESS-FLOW.md   <- §6 matriks permission, §8 alur, §2 lifecycle
5. docs/04-FINDING-REGISTRY.md  <- F-69, F-70, F-71 baru

LAPORKAN:
- Konfirmasi kamu membaca F-69 (TZ), F-70 (effective_from), F-71 (project_user observer)
- Checklist eksekusi Fase A-D, dependency-aware
- Hal yang belum jelas / bertentangan

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

Hari-2 selesai & diaudit. Semua lulus. Kamu sendiri yang menemukan lubang TZ di
laporan RISIKO #1 — itu temuan bagus dan Boss sudah memutuskannya.

KEPUTUSAN BOSS (F-69): APP_TIMEZONE = Asia/Jakarta. Semua timestamp WIB di DB.
TIDAK ADA konversi UTC di mana pun. Perbandingan naive di BusinessHoursCalculator
menjadi BENAR, bukan kebetulan.

Alasan dipilih atas opsi "UTC + kolom timezone": migrasi TZ bersifat deterministik
(WIB = UTC+7 selalu, Indonesia tanpa DST), sedangkan kompleksitas UTC dibayar
setiap hari oleh tim fresh entry yang akan melanjutkan kode ini.

## FASE A — PERBAIKAN TIMEZONE (F-69)

A1. Ubah APP_TIMEZONE menjadi Asia/Jakarta (.env dan/atau config/app.php).
    Pastikan tidak ada tempat lain yang meng-hardcode 'UTC'. Grep dulu.

A2. php artisan migrate:fresh --seed

A3. php artisan test
    31 test HARUS tetap lulus TANPA diubah. 13 test BusinessHoursCalculator
    ditulis naive — dengan Asia/Jakarta, naive = benar.
    Kalau ada yang gagal: LAPOR, jangan tambal test-nya.

A4. Verifikasi actual_minutes masih masuk akal:
      Task::whereNotNull('actual_minutes')->get(['id','actual_minutes','estimated_minutes']);
    Tidak boleh ada yang > (daily_capacity_minutes x jumlah hari kerja segmen).

A5. Bukti TZ aktif:
      php artisan tinker --execute="echo config('app.timezone'); echo PHP_EOL; echo now();"
    Harus 'Asia/Jakarta' dan jam yang cocok dengan jam dinding Boss (WIB).

A6. Grep pastikan tidak ada sisa: setTimezone('UTC'), ->utc(), Carbon::now('UTC')

## FASE B — WORK SCHEDULE CRUD (Pengaturan Jam Kerja)

Halaman: Pengaturan > Jam Kerja. ADMIN ONLY.

B1. Index — tampilkan RIWAYAT versi (F-40), urut effective_from desc.
    Tandai mana yang sedang aktif.

B2. Form tambah versi baru:
    - days_of_week  : checkbox Sen-Min (ISO 1=Sen .. 7=Min)
    - start_time    : time
    - end_time      : time
    - daily_capacity_minutes : number, menit (default 480)
    - effective_from : date

B3. 🔴 F-40 — SIMPAN = INSERT BARIS BARU. BUKAN UPDATE.
    JANGAN PERNAH meng-update baris work_schedules yang sudah ada.
    Ubah pengaturan = versi baru dengan effective_from baru.
    UNIQUE(organization_id, effective_from) sudah ada di skema — hormati.

B4. 🔴 F-70 — effective_from DILARANG backdate.
    Validasi: effective_from >= hari ini. Tolak tanggal masa lalu.
    ALASAN: mengubah jendela kerja masa lalu = menulis ulang realisasi task yang
    belum di-approve. Task yang sudah approved aman (F-39 sudah membekukannya),
    tapi yang sedang berjalan akan berubah angkanya diam-diam.
    Tulis alasan ini sebagai komentar, sebut F-70.

B5. Validasi lain:
    - end_time > start_time
    - days_of_week minimal 1 hari
    - daily_capacity_minutes antara 1 dan (end_time - start_time) dalam menit
      (F-42: kapasitas BOLEH lebih kecil dari panjang jendela — itu jam istirahat.
       Tapi tidak boleh LEBIH BESAR dari jendela.)

B6. TIDAK ada edit. TIDAK ada delete. Versi lama = arsip permanen.

## FASE C — PROJECT CRUD

ADMIN ONLY (F-29). Member hanya melihat project yang di-assign ke dia.

C1. Index: daftar project. Admin lihat semua, member lihat miliknya.
C2. Create/Edit: name, description, owner_id (dropdown user), members (multi-select)
C3. 🔴 Saat CREATE -> auto-generate 4 status default sesuai 02-DATA-MODEL §3.7:
      TODO        #94a3b8 pos=0  work=0 review=0 completed=0
      IN PROGRESS #3b82f6 pos=1  work=1 review=0 completed=0
      REVIEW      #f59e0b pos=2  work=0 review=1 completed=0
      DONE        #22c55e pos=3  work=0 review=0 completed=1
    Bungkus dalam DB transaction. Project tanpa status = project rusak.

C4. Archive (is_archived=true), BUKAN delete. Soft delete hanya untuk kasus ekstrem.

C5. 🔴 F-71 — sync member WAJIB tercatat.
    project_user belum punya observer. Assign/unassign member saat ini TIDAK ter-log.
    Perbaiki: buat ProjectUserObserver (ikuti pola TaskUserObserver yang sudah ada),
    ATAU catat event 'members_synced' dengan properties {"old":[...],"new":[...]}.
    Pilih salah satu, JELASKAN alasannya di laporan.

C6. 🔴 PERINGATAN dari audit Hari-1 — temuanmu sendiri:
    "Kalau assign lewat query manual ke tabel pivot (bukan relasi Eloquent),
     event assigned TIDAK tercatat = lubang F-51."
    Gunakan $project->members()->sync(...), JANGAN DB::table('project_user')->insert().

## FASE D — TASK STATUS CRUD

Per project. ADMIN ONLY.

D1. Index: daftar status project, urut position.
D2. Create/Edit: name, color (hex), is_work_state, is_review, is_completed
D3. Reorder: pakai tombol naik/turun ATAU input angka position.
    🔴 JANGAN drag-drop — itu v1.0 bareng Board View. Jangan curi scope.

D4. 🔴 CONSTRAINT WAJIB (validasi di service layer, bukan cuma frontend):
    - TEPAT 1 status is_completed=true per project. Tidak boleh 0, tidak boleh 2.
    - MAKSIMAL 1 status is_review=true per project.
    - Minimal 1 status is_work_state=true (kalau tidak, counter tidak akan pernah jalan
      dan realisasi selamanya 0).
    - position unik per project.

D5. 🔴 HAPUS STATUS: TOLAK kalau masih ada task memakainya.
    Pesan: "Masih ada N task di status ini. Pindahkan dulu."
    JANGAN cascade delete. JANGAN pindahkan otomatis.

D6. Rename bebas — F-44 sudah menjamin logika sistem ikut FLAG, bukan nama.
    Ini fitur, bukan bug: admin boleh menamai statusnya sesuai bahasa timnya.

## UI

- Inertia v2 + React 19 + shadcn/ui + Tailwind
- Bahasa Indonesia
- Layout admin sederhana + navigasi
- Tampilkan pesan error validasi dengan jelas
- JANGAN buat komponen custom kalau shadcn/ui sudah punya

## DILARANG KERAS DI HARI-3

JANGAN buat Task CRUD -> Hari-4
JANGAN buat List View / Board View -> Hari-5 / v1.0
JANGAN buat search, notifikasi UI, dashboard
JANGAN buat drag-drop apa pun
JANGAN buat recurring engine
JANGAN buat scheduler/cron (F-38)
JANGAN kembalikan auto-fill due_date (F-68)
JANGAN update baris work_schedules yang sudah ada (F-40)
JANGAN hitung ulang actual_minutes yang sudah frozen (F-39)
JANGAN hardcode nama status (F-44)
JANGAN buat tabel scoring/KPI/payroll
JANGAN install dependency tanpa approval Boss

## STANDAR KOMENTAR
CLAUDE.md §3. Audiens: programmer FRESH ENTRY.
Header klasifikasi di SETIAP file baru. Provenance SUMBER + DIPAKAI.
Sebut nomor F-N di komentar business rule.

## DEFINITION OF DONE HARI-3

Tempel output ASLI:

[ ] config('app.timezone') -> 'Asia/Jakarta'
[ ] php artisan test -> SEMUA lulus (31 lama + test baru), TANPA mengubah test lama
[ ] grep 'UTC' di app/ config/ -> tidak ada yang fungsional (komentar boleh)
[ ] Task::whereNotNull('actual_minutes') -> 3, angkanya masih masuk akal
[ ] Buat project baru dari browser -> TaskStatus::where('project_id',$baru)->count() = 4
[ ] Status DONE project baru -> is_completed = true
[ ] Ubah jam kerja -> WorkSchedule::count() BERTAMBAH (bukti INSERT, bukan UPDATE)
[ ] effective_from kemarin -> DITOLAK validasi (F-70)
[ ] Hapus status yang masih dipakai task -> DITOLAK dengan pesan jelas
[ ] Set 2 status is_completed -> DITOLAK
[ ] Sync member project -> ActivityLog bertambah (F-71)
[ ] Login sebagai member -> TIDAK bisa akses halaman Pengaturan/Project create
[ ] ./vendor/bin/pint -> 0 issue
[ ] npm run build -> sukses
[ ] Tidak ada file scheduler/cron

Test feature WAJIB:
[ ] tests/Feature/ProjectTest.php   — create menghasilkan 4 status
[ ] tests/Feature/WorkScheduleTest.php — insert baru, backdate ditolak
[ ] tests/Feature/TaskStatusTest.php — constraint is_completed, hapus ditolak
[ ] Permission test: member ditolak di semua route admin

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual, tempel apa adanya>
DEVIASI  : <apa yang beda dari instruksi ini, dan kenapa>
RISIKO   : <apa yang bisa pecah di Hari-4>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa Fase A cuma 30 menit padahal lubangnya besar?**
Karena data masih seeder. `migrate:fresh --seed` membuat ulang semuanya dengan TZ benar. **Kalau lubang ini baru ketahuan setelah tim memakai aplikasi, ongkosnya bukan 30 menit** — itu ratusan `actual_minutes` yang sudah dibekukan salah dan, per F-39, tidak boleh dihitung ulang.

**Ini kedua kalinya pola yang sama diselamatkan tepat waktu** — F-57 di Hari-2, F-69 di Hari-3. Keduanya kalkulator yang membekukan angka salah. Keduanya ketahuan saat data masih dummy.

**Kenapa 31 test lama TIDAK BOLEH diubah?**
Kalau `Asia/Jakarta` benar, test naive akan lulus **apa adanya**. Kalau ada yang gagal lalu ditambal, itu menyembunyikan masalah nyata. **Test yang menyesuaikan diri dengan kode bukan test — itu stempel.**

**Kenapa `effective_from` tidak boleh backdate (F-70)?**
Karena jendela kerja masa lalu **sudah dipakai menghitung realisasi**. Mengubahnya = menulis ulang angka task yang sedang berjalan, diam-diam. Task yang sudah approved aman (F-39 membekukannya), tapi yang belum akan bergeser tanpa ada yang sadar.

**Kenapa reorder status pakai tombol, bukan drag-drop?**
Drag-drop butuh `dnd-kit` + optimistic UI (F-33) — itu paket Board View di v1.0. Mengambilnya sekarang berarti mengerjakan v1.0 di Hari-3 dan menggeser semua yang lain. **Tombol naik/turun berfungsi 100% sama untuk admin yang mengatur 4 status sekali setahun.**

**Timeline tetap:**
| Hari | Isi | Status |
|---|---|---|
| 1 | Fondasi · skema · observer | ✅ |
| 2 | MySQL 8 · F-57 | ✅ |
| **3** | **TZ · Work Schedule · Project · Status CRUD** | ← sekarang |
| 4 | Task CRUD · validasi F-45 | |
| 5 | List View · filter · My Tasks | |
| 6 | Search · notifikasi · buffer | |
