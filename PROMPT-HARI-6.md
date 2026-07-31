# PROMPT HARI-6 — SEARCH + NOTIFIKASI (PENUTUP v0.5)

> **Hari terakhir v0.5.** Bersih-bersih · Search (F-7, F-79) · Notifikasi 8 trigger (F-35, F-80) · verifikasi v0.5 utuh
> Setelah hari ini: aplikasi siap dipakai tim, dan **data KPI mulai terkumpul sejak hari pertama pemakaian**.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 2 file:
```
CLAUDE.md                    <- F-78 (menambal ≠ memperbarui test), F-81 (klarifikasi F-38)
docs/04-FINDING-REGISTRY.md  <- DIPERBAIKI TOTAL: F-73..F-81 sempat tidak masuk tabel
```

> 🔴 **Registry sebelumnya rusak.** Tujuh entry gagal tercatat dan nomor F-73/F-75 sempat tertukar. Sudah diperbaiki mengikuti versi yang tertanam di kode. **Pakai file yang baru, buang yang lama.**

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md                    <- F-78 dan F-81 BARU
2. docs/01-PRD.md               <- §5.1 daftar V05-1..13 (dipakai Fase E)
3. docs/03-BUSINESS-FLOW.md     <- §9 notifikasi, §10 search
4. docs/04-FINDING-REGISTRY.md  <- DIPERBAIKI. F-79, F-80, F-81 BARU

LAPORKAN:
- Konfirmasi kamu membaca F-79 (description_plain), F-80 (idempotency), F-81 (klarifikasi F-38)
- Sebutkan nama paket rich text editor yang kamu pasang di Hari-4 + versinya
  (Boss sudah konfirmasi editor terpasang; Jarvis belum pernah tahu paketnya)
- Checklist Fase A-E, dependency-aware

BERHENTI. Tunggu Boss bilang "LANJUT".

## 🔴 KLARIFIKASI PENTING — F-81

Setiap prompt sejak Hari-1 menulis "JANGAN buat scheduler/cron (F-38)".
ITU KONTEKSNYA COUNTER: counter = calculated, bukan stateful, jangan scheduler per menit.

HARI INI SCHEDULER DIIZINKAN — khusus untuk notifikasi due-date (Fase C2).
Cron harian untuk notifikasi adalah hal yang berbeda dari polling counter per menit.
F-38 TETAP BERLAKU untuk counter: JANGAN pernah simpan state counter berjalan.

## KONTEKS

Hari-5 lulus, 80 test. Kamu menemukan sendiri bahwa instruksi Jarvis bertabrakan
(hapus flagConstraintViolation vs test lama tidak boleh diubah) dan menyelesaikannya
dengan benar. Itu melahirkan F-78 yang sekarang ada di CLAUDE.md §5:

  Menambal  = test gagal karena KODE salah -> ubah test agar hijau -> DILARANG
  Memperbarui = test gagal karena PERILAKU sengaja diubah instruksi -> test
                disesuaikan, cakupan setara, WAJIB dilaporkan -> BENAR

Kamu tidak perlu bertanya lagi untuk kasus seperti itu. Cukup lapor.

## FASE A — BERSIH-BERSIH UTANG (~45 menit)

A1. HAPUS file auth mati. Route-nya dicabut sejak Hari-1, filenya masih menggantung:
    resources/js/pages/auth/register.tsx
    resources/js/pages/auth/forgot-password.tsx
    resources/js/pages/auth/reset-password.tsx
    resources/js/pages/auth/verify-email.tsx
    resources/js/pages/auth/confirm-password.tsx
    (Grep dulu. Hapus HANYA yang route-nya benar-benar tidak ada.
     Ada yang masih terpakai -> LAPOR, jangan hapus.)

    ALASAN: file mati membingungkan fresh entry - mereka mengira fitur itu ada,
    lalu menghabiskan waktu mencari kenapa tidak jalan. Dan F-73 membuktikan file
    mati bisa menjatuhkan halaman hidup.

A2. Perbaiki 4 error `npx tsc --noEmit` (pre-existing dari starter kit):
    - login.tsx / register.tsx / reset-password.tsx -> FormDataType
    - welcome.tsx -> mixBlendMode
    Sebagian mungkin hilang sendiri setelah A1.
    Target: npx tsc --noEmit -> 0 error.

    ALASAN: npm run build pakai esbuild yang TIDAK type-check penuh. Build hijau
    tapi tsc merah = fresh entry melihat IDE merah dan tidak tahu harus percaya
    yang mana. Type error yang dibiarkan mengajarkan tim mengabaikan type error.

A3. Tambah test coverage yang kamu sendiri tandai kurang di laporan Hari-5:
    wouldLeaveNoWorkState() dipanggil lewat updateFlags() (bukan hanya destroy()).
    Skenario: admin uncheck checkbox is_work_state TERAKHIR -> DITOLAK.

## FASE B — SEARCH (F-7) + description_plain (F-79)

B1. 🔴 MASALAH: description sekarang berisi HTML (rich text, F-30).
    FULLTEXT di (title, description) mengindeks HTML MENTAH:
      "<p>Kerjakan <strong>laporan</strong></p>"
      -> terindeks: kerjakan, strong, laporan
    Akibat: cari "strong" -> keluar semua task bercetak tebal.
    Link -> "href", "https", "com" ikut terindeks.
    Snippet hasil pencarian membocorkan tag ke layar.

B2. Migration: tambah kolom tasks.description_plain (longtext NULL).

B3. TaskObserver: isi description_plain saat saving.
    Transformasi: strip_tags -> html_entity_decode -> normalisasi spasi -> trim.
    JANGAN lupa &nbsp; dan entity lain - itu jadi sampah kalau tidak di-decode.

B4. Migration: DROP fulltext_index lama, CREATE di (title, description_plain).
    Guard: exclude sqlite, BUKAN include mysql (F-67).

B5. Data masih seeder -> migrate:fresh --seed cukup untuk backfill.
    JANGAN tulis script backfill. Belum perlu.

B6. Endpoint search:
    - MATCH(title, description_plain) AGAINST (? IN BOOLEAN MODE)
    - 🔴 F-34: WAJIB difilter permission. Member TIDAK BOLEH menemukan task dari
      project yang bukan miliknya. Ini bug keamanan paling umum di fitur search.
      Test-nya WAJIB ada.
    - Maks 20 hasil
    - Snippet diambil dari description_plain (sudah bersih), BUKAN description

B7. UI: search box di header. Debounce 300ms. Hasil: judul + nama project +
    snippet + status. Klik -> ke task.

B8. Empty state jelas: "Tidak ada task yang cocok dengan '<kata>'".

B9. MySQL FULLTEXT punya minimum token size (default 3). Kata < 3 huruf tidak
    ketemu. JANGAN akali dengan LIKE %...% sebagai fallback - itu full table scan.
    Cukup beri tahu user: "Kata pencarian minimal 3 huruf."

## FASE C — NOTIFIKASI 8 TRIGGER (F-35)

C1. ENAM trigger lewat OBSERVER (event-driven, tanpa scheduler):

    1. Task di-assign ke saya          -> assignee baru
    2. Task di-unassign                -> assignee lama
    3. Status task saya berubah        -> assignee LAIN (bukan pelaku)
    6. Task masuk status is_review     -> ADMIN
    7. Task di-approve                 -> assignee
    8. Task ditolak + alasan           -> assignee

    🔴 F-36: pelaku aksi TIDAK dapat notifikasi atas aksinya sendiri.
    Kalau tidak, inbox banjir sampah dan orang berhenti membaca notifikasi -
    yang membunuh fitur ini seluruhnya.

    🔴 F-44: JANGAN hardcode nama status. Pakai flag is_review / is_completed.

C2. DUA trigger lewat SCHEDULER (diizinkan hari ini - F-81):

    4. Due date BESOK        -> assignee
    5. Task LEWAT deadline   -> assignee + admin

    Command:
      php artisan tasks:notify-due-soon
      php artisan tasks:notify-overdue

    Daftarkan di scheduler, jadwal HARIAN (bukan per jam).

    🔴 F-80 — GUARD IDEMPOTENCY WAJIB:
    Cron bisa jalan 2x (retry, atau seseorang menjalankan manual).
    Tanpa guard, notif "due besok" terkirim berulang -> inbox banjir -> fitur mati.
    Guard: sebelum kirim, cek apakah sudah ada notifikasi tipe yang sama untuk
    task yang sama pada hari yang sama. Kalau ada -> SKIP.
    Ini pola yang sama dengan last_generated_date di F-61.

    Definisi:
    - due_soon : DATE(due_date) = besok  AND status BUKAN is_completed
    - overdue  : due_date < now()        AND status BUKAN is_completed

    Di Windows tidak ada cron. Uji manual: php artisan schedule:run
    Cron dipasang saat deploy ke server (bukan pekerjaan hari ini).

C3. TIDAK ADA trigger 9 & 10 (extension diajukan/diputuskan) - butuh extension
    flow yang dijadwalkan v0.8. Jangan bangun.

C4. UI notifikasi:
    - Bell di header + badge jumlah belum dibaca
    - Dropdown: 10 terbaru, judul + waktu relatif ("2 jam lalu")
    - Klik notif -> tandai dibaca + buka task terkait
    - Tombol "Tandai semua dibaca"
    - Empty state: "Belum ada notifikasi"

C5. Pakai tabel notifications bawaan Laravel (sudah ada sejak Hari-1).
    JANGAN bikin tabel sendiri.

## FASE D — TEST

D1. tests/Feature/SearchTest.php
    - cari kata di title -> ketemu
    - cari kata di description (rich text) -> ketemu lewat description_plain
    - 🔴 cari kata "strong" -> TIDAK mengembalikan task yang cuma bercetak tebal
      (ini test yang membuktikan F-79 selesai)
    - 🔴 member TIDAK menemukan task dari project yang bukan miliknya (F-34)
    - kata < 3 huruf -> pesan jelas, bukan error

D2. tests/Feature/NotificationTest.php
    - assign task -> assignee dapat notif
    - pelaku TIDAK dapat notif atas aksinya sendiri (F-36)
    - task masuk review -> admin dapat notif
    - approve -> assignee dapat notif
    - reject -> assignee dapat notif + alasan ikut terkirim

D3. tests/Feature/NotificationSchedulerTest.php
    - 🔴 jalankan command 2x -> notifikasi TIDAK ganda (F-80)
    - due besok -> assignee dapat notif
    - task sudah selesai + due lewat -> TIDAK dapat notif overdue
    - pakai travelTo() untuk anchor waktu (pelajaran Hari-2: tanggal relatif = flaky)

D4. 80 test lama tetap lulus.
    F-78 berlaku: kalau ada yang gagal karena perilaku sengaja diubah hari ini,
    perbarui + LAPOR. Kalau gagal karena kode salah, PERBAIKI KODENYA.

## FASE E — VERIFIKASI v0.5 UTUH

Cek satu per satu terhadap docs/01-PRD.md §5.1. Untuk tiap item tulis
LENGKAP / SEBAGIAN / TIDAK ADA + bukti singkat:

  V05-1  Skema database lengkap (17 migration)
  V05-2  Auth + role, tanpa self-signup
  V05-3  Pengaturan jam kerja (versioned)
  V05-4  Project CRUD + assign member
  V05-5  Task CRUD (points, estimasi, rich text)
  V05-6  Custom status + 3 flag
  V05-7  Transisi berurutan + validasi
  V05-8  List View + filter + sort
  V05-9  Search FULLTEXT
  V05-10 My Tasks
  V05-11 Notifikasi in-app
  V05-12 Activity Log via Observer
  V05-13 Time segments tercatat

Lalu jawab jujur: ADA TIDAK yang diklaim selesai tapi sebenarnya belum pernah
diverifikasi di browser? (F-73 terjadi persis karena ini.)

## DILARANG KERAS DI HARI-6

JANGAN buat Board View / Kanban / drag-drop -> v1.0
JANGAN buat dashboard / angka idle / beban / backlog -> v0.8
JANGAN buat attachment upload -> v0.8
JANGAN buat deadline extension flow -> v0.8
JANGAN buat trigger notifikasi 9 & 10 (extension) -> v0.8
JANGAN buat recurring engine / task_templates CRUD -> v0.8
JANGAN buat holiday calendar -> v0.8
JANGAN buat business-hours per-hari resolution (F-66) -> v0.8
JANGAN buat scheduler untuk COUNTER (F-38 tetap berlaku)
JANGAN buat tabel scoring/KPI/payroll -> v1.5 / v2.0
JANGAN pakai LIKE %...% sebagai fallback search (B9)
JANGAN hardcode nama status (F-44)
JANGAN kembalikan auto-fill due_date (F-68)
JANGAN install dependency tanpa approval Boss
JANGAN edit dokumen di docs/ - lapor kalau ada yang perlu diubah

## DEFINITION OF DONE HARI-6

🔴 F-75: item [BROWSER] WAJIB dibuktikan di browser nyata.

[ ] [BROWSER] search "laporan" -> ketemu, snippet BERSIH tanpa tag HTML
[ ] [BROWSER] search "strong" -> TIDAK mengembalikan task bercetak tebal
[ ] [BROWSER] member search -> tidak menemukan task project lain (F-34)
[ ] [BROWSER] assign task -> bell muncul badge, dropdown menampilkan notif
[ ] [BROWSER] klik notif -> tandai dibaca + buka task
[ ] [BROWSER] "Tandai semua dibaca" -> badge hilang
[ ] php artisan tasks:notify-due-soon (jalankan 2x) -> notif TIDAK ganda (F-80)
[ ] npx tsc --noEmit -> 0 error
[ ] SHOW INDEX FROM tasks -> fulltext di (title, description_plain), yang lama HILANG
[ ] Task::whereNotNull('description_plain')->count() > 0
[ ] grep '<p>' pada description_plain -> No matches (HTML benar-benar dibersihkan)
[ ] php artisan test -> SEMUA lulus (80 lama + baru)
[ ] ./vendor/bin/pint -> 0 issue
[ ] npm run build + npm run lint -> bersih
[ ] Tidak ada scheduler untuk counter (F-38)
[ ] Verifikasi Fase E: 13 item V05 dilaporkan satu per satu

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual>
           <bukti browser untuk item [BROWSER]>
DEVIASI  : <apa yang beda dari instruksi ini, dan kenapa>
           <kalau nol, tulis "NOL" eksplisit - jangan kosongkan>
V0.5     : <13 item V05, masing-masing LENGKAP/SEBAGIAN/TIDAK ADA>
RISIKO   : <apa yang harus dibereskan sebelum tim memakai aplikasi ini>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa `description_plain` (F-79) tidak bisa ditunda?**
Karena `migrate:fresh --seed` sekarang **gratis** — datanya cuma dummy. Setelah tim mengisi 500 task berisi HTML, menambah kolom ini berarti **backfill data produksi**, dan search yang keburu dipakai orang akan terlanjur mengembalikan hasil aneh. **Pola yang sama dengan F-57 dan F-69: dibereskan mumpung data masih seeder.**

**Kenapa test "cari `strong`" ditandai merah?**
Itu **satu-satunya test yang membuktikan F-79 benar-benar selesai**. Kalau `description_plain` tidak diisi dengan benar, search "laporan" tetap lulus — tapi "strong" akan membocorkan semua task bercetak tebal. **Test yang hanya menguji jalur bahagia tidak membuktikan apa pun.**

**Kenapa F-80 (idempotency) ditandai merah?**
Notifikasi ganda bukan bug kosmetik. Tim yang inbox-nya banjir **berhenti membaca notifikasi sama sekali** — dan begitu itu terjadi, trigger "task lewat deadline" jadi tidak berguna, padahal itu sinyal KPI paling dasar Boss.

**Fase E adalah pertanyaan terpenting hari ini.**
*"Ada tidak yang diklaim selesai tapi belum pernah diverifikasi di browser?"* — **F-73 terjadi persis karena pertanyaan ini tidak pernah diajukan.** `/login` mati tiga hari sambil semua test hijau.

---

**Setelah Hari-6, v0.5 tutup. Yang Boss punya:**
- Aplikasi task management yang bisa dipakai 10 orang
- **Activity log & time segments merekam sejak hari pertama pemakaian** — bahan kalibrasi scoring v1.5
- Skema lengkap: kolom KPI, attachment, extension, recurring **sudah tertanam** menunggu diaktifkan

**Yang belum, dan itu disengaja:** dashboard idle/beban (v0.8) · recurring engine (v0.8) · attachment (v0.8) · extension flow (v0.8) · Board View (v1.0) · scoring (v1.5).

**Sebelum tim benar-benar memakainya, tiga hal wajib diputuskan:** F-11 nama produk · F-12 hosting · F-13 backup. Dan **F-65 — jadwal upgrade Laravel 12 → 13**, karena bug-fix L12 berakhir ±Agustus 2026.
