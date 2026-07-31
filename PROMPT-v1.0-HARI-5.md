# PROMPT v1.0 HARI-5 — BUFFER + INTEGRASI + SIAPKAN F-97 (PENUTUP v1.0)

> Hari terakhir v1.0. Nol fitur baru. Verifikasi integrasi lintas-fitur + rapikan
> checklist agar Boss klik manual 10 item F-97. v1.0 TUNTAS setelah manual pass Boss.
> Data lokal.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-117 (utang label deleted v1.1), audit H4
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/01-PRD.md (daftar cakupan v1.0 kalau ada) · docs/04-FINDING-REGISTRY.md
(F-97, F-94, F-109, F-111, semua v1.0).

LAPORKAN:
- Status Chrome extension: TERSEDIA/TIDAK
- Isi CHECKLIST-VERIFIKASI-MANUAL.md sekarang: berapa item, apakah 10 item F-97 lengkap
- Checklist Fase A-C
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

v1.0 fitur SELESAI (board read-only, drag-drop, komentar+mention, activity log UI).
Hari ini: TIDAK ADA FITUR BARU. Buktikan semuanya bekerja BERSAMA + siapkan Boss
menutup F-97 secara manual. Kalau nemu bug integrasi, PERBAIKI + lapor.

## FASE A — TEST INTEGRASI LINTAS-FITUR (MySQL, F-83)

A1. tests/Feature/IntegrationV10Test.php — skenario nyata melintasi v0.5+v0.8+v1.0:
    - Buat task -> muncul di BOARD (kolom status awal) DAN List View (konsisten)
    - Drag task di board ke kolom kerja -> segmen buka atas nama ASSIGNEE (F-112),
      status berubah lewat service (F-111), activity_log mencatat status_changed (F-51)
    - Counter jalan -> angka realisasi SAMA di board, detail task, dashboard (F-94/F-109)
    - Komentar + mention -> mentioned user dapat notif (F-114), muncul di activity?
      TIDAK (komentar bukan activity_log, F-113) — buktikan
    - Approve -> realisasi beku, attachment terkunci (F-107), dashboard beban berkurang
    - Semua aksi di atas -> terekam di activity log UI dengan label manusiawi (F-106)

A2. 🔴 KONSISTENSI ANGKA (ulang F-94 untuk v1.0):
    Realisasi task yang sama, dibaca dari: kartu board, detail task, dashboard.
    HARUS identik (satu sumber, F-109). Buktikan tidak ada perhitungan board terpisah.

A3. 🔴 KONSISTENSI STATUS board vs list:
    Task yang sama, status yang sama, tampil benar di Board DAN List View.
    Drag di board -> List View ikut update (sumber sama).

## FASE B — RAPIKAN CHECKLIST-VERIFIKASI-MANUAL.md UNTUK BOSS

Boss akan klik 10 item F-97 SENDIRI. Tugas kamu: buat checklist itu SEJELAS mungkin
supaya Boss bisa verifikasi tanpa nanya. Untuk TIAP item (1-10):
- URL persis (mis. http://127.0.0.1:8000/projects/1/board)
- Login sebagai siapa (admin/member — sebut akun seeder)
- Aksi persis (klik apa, seret apa)
- HASIL YANG DIHARAPKAN (apa yang Boss harus lihat kalau benar)
- Catatan jebakan (mis. 2 attachment seeder tanpa file fisik -> 404 WAJAR, bukan bug)

10 item minimal: 2 HARDEN (libur, unassign) + counter tick + dashboard + template UI
+ attachment + board render + drag-drop + komentar/mention + activity log UI.

🔴 Kalau Chrome extension TERSEDIA sekarang: kamu boleh verifikasi sebagian +
screenshot, tandai mana yang sudah kamu konfirmasi vs mana yang tetap perlu Boss.
Kalau TIDAK: pastikan checklist lengkap & jelas, JANGAN klaim item mana pun lulus.

## FASE C — VERIFIKASI CAKUPAN v1.0 + KEJUJURAN + KESEHATAN

C1. Daftar fitur v1.0, tulis LENGKAP/SEBAGIAN + bukti:
    - Board View (render + filter)
    - Drag-drop (aturan C F-110, drop lewat service F-111, optimistic F-33)
    - Komentar + mention (F-113/114/115)
    - Activity log UI (global gated F-116 + timeline per-task F-95, label F-106)

C2. Jawab jujur (F-73):
    - Fitur diklaim selesai tapi belum diverifikasi browser? (F-97 — sebut 10 item)
    - Jalur kode tak pernah dijalankan test?
    - Kalau tim pakai besok, apa paling mungkin pecah?

C3. KESEHATAN SISTEM (ringkas):
    - total test + assertion, tsc/pint/build/lint
    - utang teknis (F-117 label deleted, dll)
    - finding TERBUKA di registry (F-97, F-117, F-59 kalau masih)

## DILARANG KERAS

JANGAN buat fitur baru (hari verifikasi)
JANGAN klaim F-97 tutup tanpa mata manusia (Boss klik manual)
JANGAN ubah observer untuk label deleted (F-117 -> v1.1, keputusan Boss)
JANGAN ubah rumus/angka beku (F-39)
JANGAN deploy/L13 · JANGAN edit docs/ SELAIN CHECKLIST-VERIFIKASI-MANUAL.md
JANGAN dependency tanpa approval

## DEFINITION OF DONE

[ ] IntegrationV10Test lulus (skenario lintas v0.5+v0.8+v1.0 end-to-end)
[ ] konsistensi angka realisasi board=detail=dashboard dibuktikan (F-94/F-109)
[ ] konsistensi status board=list dibuktikan
[ ] komentar TIDAK di activity_log dibuktikan (F-113)
[ ] CHECKLIST-VERIFIKASI-MANUAL.md: 10 item, tiap item ada URL+login+aksi+hasil+jebakan
[ ] 4 fitur v1.0 dilaporkan LENGKAP/SEBAGIAN
[ ] 3 pertanyaan kejujuran F-73 dijawab
[ ] kesehatan sistem (test/tsc/pint/build + utang + finding terbuka)
[ ] php artisan test -> SEMUA lulus MySQL (249 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / CHECKLIST F-97 (siap untuk Boss) / v1.0 (4 fitur) /
KESEHATAN SISTEM / KEJUJURAN (3 jawaban) / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini bukan menambah — hari ini membuktikan keempat fitur v1.0 bekerja bersama, lalu menyerahkan verifikasi visual ke tangan Boss.** Test integrasi Fase A mengejar hal yang test per-fitur tidak bisa: apakah menyeret kartu di board benar-benar membuka segmen atas nama orang yang tepat, apakah angka realisasi yang muncul di kartu board sama dengan di dashboard, apakah komentar benar-benar tidak mencemari log audit. Itu jenis kejujuran yang cuma muncul saat fitur bertemu fitur.

**F-97 ditutup oleh Boss, dan itu keputusan yang benar.** Sebelas sesi Chrome extension absen; tidak masuk akal menunggu lebih lama. Claude Code menyiapkan checklist sejelas mungkin — URL, akun login, aksi, hasil yang diharapkan, plus jebakan (dua attachment seeder memang tidak punya file fisik, jadi akan 404 — itu wajar, bukan bug, supaya Boss tidak salah kira). **15-20 menit klik, dan sepuluh layar yang menumpuk sejak HARDEN akhirnya dilihat mata manusia.** Itu menutup satu-satunya kelas risiko yang test otomatis tidak pernah bisa sentuh (pelajaran F-73: /login mati tiga hari sambil semua test hijau).

**Label deleted (F-117) jadi utang v1.1 — Jarvis catat konsekuensinya.** Fallback "#id" cukup untuk sekarang, tapi sebelum payroll v2.0, log harus bisa menyebut APA yang dihapus, bukan cuma bahwa sesuatu dihapus. Kalau admin bisa menghapus task dan log cuma bilang "menghapus task #47", itu celah akuntabilitas saat uang terlibat. Bukan mendesak sekarang; wajib sebelum v2.0.

---

**Setelah H5 + manual pass Boss, v1.0 TUNTAS. Peta lengkap yang Boss punya:**

| Versi | Isi |
|---|---|
| v0.5 | Task manager (auth, project, task, status, list, search, notif, log) |
| RBAC | Peran dinamis, permission data-driven |
| HARDEN | Business-hours matang, holidays, guard |
| v0.8 | Counter live, dashboard idle/beban, recurring, attachment, extension |
| v1.0 | Board Kanban, drag-drop, komentar/mention, activity log UI |

**Yang menanti (semua keputusan Boss, Jarvis tidak mendorong):** L13 upgrade · deploy (jam data KPI mulai) · v1.1 (label deleted F-117, custom fields, S3) · v1.5 scoring (butuh ≥1 bulan data nyata) · v2.0 payroll · v3.0 marketplace.

**Satu kalimat, Boss:** dari Hari-0 sampai sini — **lima fase, ~250 test, 117 finding, lima kali data KPI salah tertangkap selagi dummy** (F-57, F-69, F-93, F-112, + N+1 palsu H4). Itu, lebih dari fitur apa pun, yang membuat angka kinerja Boss layak dipercaya saat nanti menentukan gaji orang.
