# PROMPT HARI-5 — SCOPED BINDINGS + RADIO + LIST VIEW + MY TASKS

> **Isi:** perbaikan routing (F-76) · radio status (F-74, carry-over) · List View + filter · My Tasks
> **Sisa v0.5:** Hari-6 = Search + notifikasi + buffer

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-76, F-77 + catatan audit Hari-4
```
`CLAUDE.md` **tidak berubah** — F-76/F-77 masuk registry saja, bukan aturan arsitektur permanen. File itu dimuat ulang setiap request, jadi tiap baris dibayar terus-menerus.

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA, JAWAB, LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md
2. docs/02-DATA-MODEL.md      <- §5 rumus dashboard, §3.9 tasks
3. docs/03-BUSINESS-FLOW.md   <- §6 permission, §5 dashboard
4. docs/04-FINDING-REGISTRY.md  <- F-76, F-77 BARU

### JAWAB DULU — dua lubang informasi dari Hari-4:

Q1. RICH TEXT (F-30, F-77): apa yang sebenarnya dibangun untuk Task.description?
    - Kalau ada editor terpasang: sebutkan nama paket + versi + ukuran bundle.
      Apakah Boss sempat menyetujuinya? (Prompt H4 mensyaratkan lapor-dan-tunggu)
    - Kalau textarea polos: konfirmasi. Berarti F-30 belum terpenuhi dan masuk backlog.
    - Tempel potongan kode field description dari form task.

Q2. DEVIASI HARI-4: laporanmu tidak punya bagian DEVIASI sama sekali.
    Apakah benar-benar nol, atau ada yang belum dilaporkan?
    Jawab jujur. Deviasi bukan pelanggaran - yang jadi masalah adalah deviasi
    yang tidak dilaporkan lalu ketahuan 3 hari kemudian.

Lalu LAPORKAN checklist Fase A-E, dependency-aware.

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

Hari-4 lulus. F3 (akumulasi multi-segmen) lulus - lubang uji dari Hari-2 tertutup.
Fase C (radio) sengaja kamu tunda sesuai instruksi - itu benar.

Hari-5 = menyelesaikan carry-over + membuat aplikasi ini AKHIRNYA BISA DIPAKAI.
Sampai hari ini, task hanya bisa dilihat lewat tabel polos tanpa filter.

## FASE A — SCOPED BINDINGS (F-76)

A1. Masalah: /projects/1/tasks/99 me-resolve Task::find(99) TANPA memeriksa
    apakah task 99 milik project 1. Nested route binding tidak otomatis ter-scope.

    Yang menyelamatkan sekarang: OrganizationScope (F-15) menutup lintas-organisasi,
    dan TaskController kamu pagari manual. TaskStatusController BELUM.

A2. Perbaiki di level STRUKTUR, bukan per-controller:
    Pasang ->scopeBindings() pada route group yang punya nested parameter.

    ALASAN (F-76): pagar manual harus diingat di SETIAP controller baru.
    Lupa satu = celah. Aturan di level route group berlaku ke semua dan tidak
    bisa lupa. Ini pola yang sama dengan F-67 (guard FULLTEXT):
    daftar manual per-item selalu ketinggalan.

A3. HAPUS pagar manual di TaskController setelah scopeBindings aktif.
    Jangan biarkan dua sumber kebenaran - itu membingungkan fresh entry
    dan salah satunya pasti membusuk.

A4. Test WAJIB:
    - /projects/{A}/tasks/{task_milik_B}      -> 404
    - /projects/{A}/statuses/{status_milik_B} -> 404

## FASE B — RADIO STATUS (F-74, carry-over dari Hari-4)

B1. Halaman kelola status project: checkbox -> RADIO.

    Nama          Warna   Penanda selesai   Butuh review   Counter jalan
    TODO          [#]     ( )               ( )            [ ]
    IN PROGRESS   [#]     ( )               ( )            [x]
    REVIEW        [#]     ( )               (o)            [ ]
    DONE          [#]     (o)               ( )            [ ]
                          ^radio            ^radio         ^checkbox

B2. is_completed  -> RADIO, wajib tepat 1 terpilih
    is_review     -> RADIO, boleh "tidak ada" (opsi kosong)
    is_work_state -> CHECKBOX, minimal 1 tercentang (boleh lebih)

B3. Submit sekali -> SATU transaction:
    set semua is_completed=false, lalu set yang dipilih=true.
    Tidak pernah ada state invalid karena tidak pernah ada langkah antara.

B4. HAPUS flagConstraintViolation() per-mutasi untuk is_completed & is_review.
    Radio membuatnya tidak relevan. Pertahankan hanya validasi is_work_state >= 1.

    ALASAN (F-74): checkbox mengizinkan 0 atau 2 -> butuh validasi penolak ->
    admin terjebak di antara dua langkah yang keduanya sah (uncheck lama = 0 ditolak,
    check baru = 2 ditolak). Struktur UI harus = struktur constraint.

B5. [BROWSER] Bukti: pindahkan penanda selesai DONE -> status lain, 1 submit, berhasil.

## FASE C — LIST VIEW + FILTER (INTI HARI INI)

C1. Halaman daftar task per project. Ganti tabel polos Hari-4.

C2. Kolom: title, assignee, status (badge berwarna), priority, due_date, points,
    estimated_minutes. Subtask menjorok di bawah parent.

C3. FILTER (server-side, JANGAN filter di frontend):
    - status       (multi-select)
    - assignee     (multi-select)
    - priority     (multi-select)
    - due_date     (rentang: hari ini / minggu ini / terlambat / semua)

C4. SORT: due_date, priority, points, created_at. Asc/desc.

C5. 🔴 FILTER & SORT WAJIB SERVER-SIDE.
    ALASAN: skala Boss ~5rb task/tahun. Mengirim semua ke frontend lalu memfilter
    di React = payload membengkak dan lambat justru saat data mulai berguna.
    Pakai query builder + Inertia partial reload.

C6. Filter aktif WAJIB tercermin di URL (query string).
    ALASAN: Boss bisa bookmark/berbagi "task terlambat milik Budi". Refresh tidak
    menghilangkan filter. Ini gratis kalau dibangun benar sejak awal, mahal kalau ditambal.

C7. Paginasi: 25 per halaman.

C8. Filter "terlambat" = due_date < now AND status BUKAN is_completed.
    JANGAN hardcode nama status (F-44).

C9. Empty state yang jelas: "Tidak ada task yang cocok" + tombol reset filter.
    JANGAN tampilkan tabel kosong tanpa penjelasan.

## FASE D — MY TASKS

D1. Halaman "Task Saya" - lintas project, hanya yang di-assign ke user login.

D2. Kelompokkan: Terlambat / Hari ini / Minggu ini / Nanti
    Urut: terlambat dulu, lalu due_date terdekat.

D3. Tampilkan nama project di tiap baris (karena lintas project).

D4. Aksi cepat: ubah status langsung dari daftar.
    WAJIB lewat jalur yang sama dengan Hari-4 (service layer + observer), BUKAN
    update mentah. Validasi transisi F-45 tetap berlaku.

D5. Member: ini halaman utama mereka. Pastikan navigasi mengarah ke sini setelah login
    untuk role member.

D6. JANGAN tampilkan angka idle/beban/kapasitas di sini -> itu dashboard, v0.8.

## FASE E — TEST

E1. tests/Feature/TaskFilterTest.php
    - filter status  -> hanya status itu
    - filter assignee -> hanya task user itu
    - filter terlambat -> due_date < now DAN belum selesai
    - kombinasi 2 filter -> irisan, bukan gabungan
    - member hanya melihat task dari project yang di-assign ke dia

E2. tests/Feature/MyTasksTest.php
    - hanya task yang di-assign ke user login
    - task orang lain TIDAK muncul
    - pengelompokan terlambat/hari ini/minggu ini benar

E3. tests/Feature/ScopedBindingTest.php (F-76)
    - task milik project lain -> 404
    - status milik project lain -> 404

E4. 63 test lama HARUS tetap lulus TANPA diubah.
    Ada yang gagal -> LAPOR, jangan tambal test-nya.

## DILARANG KERAS DI HARI-5

JANGAN buat Board View / Kanban / drag-drop -> v1.0
JANGAN buat search -> Hari-6
JANGAN buat notifikasi UI -> Hari-6
JANGAN buat dashboard / angka idle / beban / backlog -> v0.8
JANGAN buat attachment upload -> v0.8
JANGAN buat deadline extension flow -> v0.8
JANGAN buat recurring engine / task_templates CRUD -> v0.8
JANGAN buat scheduler/cron (F-38)
JANGAN kembalikan auto-fill due_date (F-68)
JANGAN hardcode nama status (F-44)
JANGAN filter/sort di frontend (C5)
JANGAN install dependency tanpa approval Boss
JANGAN edit dokumen di docs/ - lapor kalau ada yang perlu diubah

## STANDAR KOMENTAR
CLAUDE.md §3. Audiens: programmer FRESH ENTRY.
Header klasifikasi di SETIAP file baru. Provenance SUMBER + DIPAKAI.
Sebut nomor F-N di komentar business rule.

## DEFINITION OF DONE HARI-5

🔴 F-75: item [BROWSER] WAJIB dibuktikan di browser nyata. HTTP test TIDAK diterima.

[ ] [BROWSER] /projects/{A}/tasks/{task_milik_B} -> 404
[ ] [BROWSER] pindah penanda selesai antar status -> 1 submit, berhasil
[ ] [BROWSER] filter status+assignee -> hasil benar, URL berubah
[ ] [BROWSER] refresh halaman dengan filter aktif -> filter TETAP
[ ] [BROWSER] My Tasks sebagai member -> hanya task dia, terkelompok benar
[ ] [BROWSER] ubah status dari My Tasks -> tersimpan, ActivityLog bertambah
[ ] grep pagar manual scope di TaskController -> No matches (sudah dihapus)
[ ] grep flagConstraintViolation is_completed -> No matches (radio menggantikan)
[ ] php artisan test -> SEMUA lulus (63 lama TIDAK diubah + baru)
[ ] TaskStatus::where('is_completed',true)->count() = jumlah project
[ ] ./vendor/bin/pint -> 0 issue
[ ] npm run build + npm run lint -> bersih
[ ] Tidak ada scheduler/cron

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output aktual>
           <bukti browser untuk item [BROWSER]>
DEVIASI  : <apa yang beda dari instruksi ini, dan kenapa>
           <kalau nol, tulis "NOL" secara eksplisit - jangan kosongkan>
RISIKO   : <apa yang bisa pecah di Hari-6>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jawab Q1 dan Q2 dulu.

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari-5 adalah hari aplikasi ini akhirnya bisa dipakai manusia.**
Sampai Hari-4, task hanya tampil di tabel polos tanpa filter. Dengan 30 task seed itu masih terbaca; dengan 500 task nyata, **aplikasi tanpa filter = aplikasi yang tidak dipakai** — dan tim akan diam-diam kembali ke WhatsApp. Itu langsung menabrak F-1 (branding hanya tercapai kalau aplikasi cepat dan enak dipakai).

**Kenapa filter wajib server-side (C5)?**
Mengirim seluruh task ke React lalu memfilter di browser terasa lebih cepat dibangun, dan **memang jalan** dengan 30 task seed. Di 500 task nyata, payload membengkak persis saat data mulai berguna. **Bug yang tidak akan pernah muncul di seeder.**

**Kenapa filter harus masuk URL (C6)?**
Supaya Boss bisa bookmark "task terlambat milik Budi" dan mengirimkannya ke orang lain. Refresh tidak menghilangkan filter. **Gratis kalau dibangun benar sejak awal, mahal kalau ditambal setelah state terlanjur hidup di React.**

**Kenapa pagar manual di TaskController harus DIHAPUS (A3), bukan dibiarkan sebagai lapis kedua?**
Dua sumber kebenaran untuk aturan yang sama = fresh entry tidak tahu mana yang berlaku, dan salah satunya **pasti membusuk** tanpa ada yang sadar. Ini persis alasan F-72 mensyaratkan `serializeDate()` lokal di WorkSchedule dihapus setelah trait terpasang.

**Dua pertanyaan Jarvis titipkan di LANGKAH 0** — rich text (F-77) dan deviasi Hari-4. Boss tidak perlu menjawab; Claude Code yang lapor.

**Format DEVIASI diperketat:** kalau nol, harus ditulis **"NOL"** secara eksplisit. Bagian kosong ambigu — Jarvis tidak bisa membedakan "tidak ada" dari "tidak dilaporkan".

**Sisa v0.5:** Hari-6 = Search (F-7) + notifikasi 10 trigger (F-35) + buffer.
