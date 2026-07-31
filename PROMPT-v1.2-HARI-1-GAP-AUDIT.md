# PROMPT v1.2 HARI-1 — AUDIT GAP MOCKUP v1.7 (RENCANA, BELUM NGODING)

> Langkah 1 dari integrasi mockup v1.7. HANYA analisa + rencana. NOL kode hari ini.
> Prinsip aditif (F-121): perkaya, jangan hapus. Konfirmasi bertahap.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-120..F-123 (keputusan integrasi v1.7)
```
Dan sediakan file mockup v1.7 di root project (mis. `docs/mockup-v1.7.html`) supaya Claude Code bisa membacanya baris-demi-baris.

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## TUGAS HARI INI: AUDIT GAP + RENCANA AKSI. DILARANG MENULIS KODE APA PUN.
Ini langkah analisa. Output = laporan pemetaan + rencana bertahap. Boss konfirmasi
dulu sebelum ada baris kode ditulis di hari berikutnya.

## LANGKAH 0 — BACA SEMUA (baris demi baris)

Baca: CLAUDE.md · docs/01-PRD.md · docs/02-DATA-MODEL.md · docs/03-BUSINESS-FLOW.md
· docs/04-FINDING-REGISTRY.md (KHUSUS F-120, F-121, F-122, F-123, dan F-38, F-41,
F-94, F-45, F-4, F-52, F-96, F-118, F-91, F-20).
Baca: file mockup v1.7 (docs/mockup-v1.7.html) SELURUHNYA.
Audit kode aktual: migrations, models, controllers, routes, resources/js/pages.

## KEPUTUSAN TERKUNCI (JANGAN LANGGAR — ini batas audit)

1. 🔴 F-120 — TIMER: tombol Start/Stop di v1.7 = KONTROL WORK-STATE, BUKAN timer manual.
   Realisasi TETAP dihitung dari segmen (F-38/F-41). JANGAN rancang stopwatch manual
   yang menyimpan menit dari klik. "Start" = transisi ke status kerja (segmen buka).
2. 🔴 F-121 — v1.7 = PENGAYAAN VISUAL, BUKAN pengganti. Fitur yang SUDAH dibangun tapi
   ABSEN/di-stub di v1.7 (activity log UI, komentar/mention, UI RBAC/peran, jam kerja,
   hari libur, tugas berulang, perpanjangan detail) WAJIB DIPERTAHANKAN. JANGAN hapus/
   regres jadi stub. Login role-picker v1.7 = demo, JANGAN ganti auth asli (F-91).
3. 🔴 F-122 — Prioritas Eisenhower p1-p4 = FIELD BARU, di samping enum lama. Aditif.
4. 🔴 F-123 — Checklist dalam-tugas = tabel baru (beda subtask F-20). Gate →REVIEW
   ditegakkan SERVER-side (F-111), bukan cuma client.
5. 🔴 F-4 tetap: dashboard v1.7 lebih kaya (donut/heatmap/top-10) TAPI nol rupiah/skor/
   reward. Heatmap beban = tampilan dari F-118, bukan rumus baru.

## OUTPUT WAJIB — LAPORAN PEMETAAN (3 tabel + rencana)

### TABEL A — FITUR/FIELD v1.7 vs BACKEND SEKARANG
Untuk tiap elemen v1.7 (dashboard command-center, donut prioritas, progress bar,
kategori, master calendar heatmap, recent activity, top-10 tasks + filter, workload
top-5+modal, status project table, project goal/status/due, datetime tenggat,
Eisenhower prioritas, checklist+gate review, tombol timer, kanban toggle, dst):
kolom = [Elemen v1.7 | Sudah didukung backend? | Aksi: PERTAHANKAN / TAMBAH BARU / SAMBUNG | Finding terkait]

### TABEL B — FITUR SUDAH JADI yang ABSEN di v1.7 (F-121 — daftar yang DIPERTAHANKAN)
Daftar eksplisit halaman/komponen/endpoint yang sudah dibangun tapi tidak muncul di
v1.7. Untuk tiap: [Fitur | File | Status: DIPERTAHANKAN | Bagaimana hidup berdampingan
dengan UI v1.7]. Ini jaring pengaman supaya tidak ada yang terhapus.

### TABEL C — MIGRATION/FIELD BARU yang DIBUTUHKAN (aditif)
[Tabel/field baru | Untuk elemen v1.7 apa | Aditif? (ya, nol drop kolom lama) | Risiko]
Contoh dugaan: priority_quadrant (F-122), task_checklist_items (F-123),
projects.goal/status/due_date, tasks.due_at (datetime). Konfirmasi mana yang benar
BUTUH vs sudah ada.

### RENCANA BERTAHAP (usul urutan hari, belum dieksekusi)
Pecah integrasi jadi langkah kecil aman, tiap langkah = 1 sesi + test + konfirmasi Boss.
Usulkan urutan (mis: H2 backend field aditif + migration; H3 dashboard command-center
read-only; H4 Eisenhower+checklist+gate; H5 timer-as-workstate + kanban sambung;
H6 buffer+integrasi+regresi-check). Kamu yang usulkan, Boss yang putuskan.

### PERTANYAAN TERBUKA
Apa pun yang ambigu di v1.7 yang butuh keputusan Boss (mis. Eisenhower auto-hitung
dari enum lama atau input manual terpisah? checklist wajib untuk SEMUA task atau
opsional per-task? project.status manual atau turunan dari task?).

## DILARANG KERAS HARI INI
JANGAN tulis kode/migration/test APA PUN (hari analisa)
JANGAN hapus/regres fitur yang sudah jadi (F-121)
JANGAN rancang timer manual (F-120)
JANGAN timpa enum prioritas lama (F-122 aditif)
JANGAN ubah docs/ · JANGAN install dependency

## FORMAT LAPORAN
TABEL A / TABEL B / TABEL C / RENCANA BERTAHAP / PERTANYAAN TERBUKA / RISIKO

Kerjakan audit, lapor. BERHENTI, tunggu Boss konfirmasi rencana sebelum ngoding.

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Prompt asli Boss meminta Claude Code "analisa gap dulu, lapor rencana sebelum ngoding" — itu benar, dan Jarvis memperkuatnya dengan tiga jaring pengaman** yang tidak ada di prompt asli: batasan timer (F-120), perlindungan frontend yang sudah jadi (F-121), dan prioritas aditif (F-122). Tanpa ini, "HTML sebagai patokan frontend" bisa diterjemahkan Claude Code sebagai "hapus yang tidak ada di HTML" — dan itu akan menghancurkan activity log, komentar, RBAC UI, dan lima fitur lain yang sudah teruji.

**Tabel B adalah jaring pengaman terpenting.** Ia memaksa Claude Code mendaftar SECARA EKSPLISIT semua fitur jadi yang tidak muncul di v1.7, dan menyatakan "DIPERTAHANKAN" untuk masing-masing — sebelum menyentuh apa pun. Kalau daftar itu lengkap dan Boss setujui, tidak ada yang terhapus tanpa sengaja.

**Kenapa hari ini nol kode:** integrasi ini besar (dashboard baru, 2 field, 1 tabel, timer, kanban, sambungan). Menulis kode sebelum peta lengkap = menambal buta. Satu hari analisa menghemat berhari-hari perbaikan. Ini disiplin LANGKAH 0 yang sama yang menangkap lima bug KPI selama proyek.

**Satu hal yang Jarvis senang lihat di v1.7:** master calendar heatmap (beban harian rendah/tengah/tinggi) **selaras persis dengan F-118** yang baru kita bangun — sebar beban ke hari kerja. Jadi visual keren itu langsung didukung data yang sudah benar. Bukan kebetulan; itu karena fondasinya sudah jujur.

**Catatan status:** v1.0 masih menunggu Boss klik manual F-97. v1.2 (integrasi v1.7) bisa jalan paralel, tapi F-97 tetap utang terbuka — makin banyak UI baru (dashboard v1.7) berarti makin banyak yang akhirnya perlu mata manusia. Pertimbangkan klik F-97 di sela-sela.
