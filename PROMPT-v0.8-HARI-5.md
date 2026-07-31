# PROMPT v0.8 HARI-5 — ATTACHMENT (F-49, F-105, F-106)

> Upload file hasil kerja. Infrastruktur dipakai juga oleh evidence extension (H6).
> Data lokal. Storage lokal non-publik.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-103..F-106 (gap F-103 diperbaiki), keputusan attachment
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md §3.12 attachments · docs/03-BUSINESS-FLOW.md §4
· docs/04-FINDING-REGISTRY.md (F-49, F-105, F-106, F-95, F-39).

LAPORKAN:
- Konfirmasi paham: output BEKU saat approve (F-105), hanya admin hapus + member
  APPEND-ONLY (F-106), gating assignee/membership bukan permission (F-95)
- F-97: 5 item browser tertunda. Extension tersedia? Kalau tidak, lanjut, catat
- Checklist Fase A-D
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Tabel attachments sudah ada (skema Hari-1 §3.12): task_id, deadline_extension_id
(NULL untuk output), type(output/evidence), file_path, file_name, file_size,
mime_type, uploaded_by. Hari ini bangun infrastruktur + wire OUTPUT.
EVIDENCE (untuk extension) wire di H6 — tabel & storage sama, tinggal dipakai.

## FASE A — STORAGE & KEAMANAN (fondasi, jangan buru-buru)

A1. Storage: storage/app/private/attachments (DI LUAR public root).
    🔴 File TIDAK boleh diakses via URL tebakan. Download HANYA lewat controller
    yang cek permission. Path traversal dicegah.

A2. 🔴 VALIDASI MIME NYATA, bukan cuma ekstensi:
    Laravel rule mimes: (cek isi file, bukan nama). File .exe di-rename .pdf -> DITOLAK.
    Izin: pdf, jpg, jpeg, png, docx, xlsx, zip. Maks 10 MB.

A3. 🔴 NAMA FILE:
    - Simpan dengan nama GENERATED (mis. uuid), JANGAN pakai nama user sebagai path
      (cegah path traversal / collision)
    - Simpan nama asli di kolom file_name (untuk ditampilkan saat download)
    - Sanitasi file_name sebelum simpan/tampil

A4. file_size & mime_type diisi dari file NYATA, bukan dari klaim klien.

## FASE B — UPLOAD/DOWNLOAD OUTPUT

B1. Upload output ke task (type=output, deadline_extension_id=NULL):
    - Siapa: assignee task ATAU admin (F-95: gating assignee/membership, BUKAN
      permission RBAC — member nol permission)
    - Kapan: saat task BELUM approved. Selama siklus review boleh menambah (F-106)

B2. 🔴 F-105 — BEKU SAAT APPROVE:
    Task sudah is_completed + approved_at terisi -> upload output DITOLAK.
    Output jadi bukti beku (sejalan F-39: actual_minutes juga beku saat approve).
    Cek pakai FLAG is_completed + approved_at, bukan nama status (F-44).

B3. 🔴 F-106 — HAPUS HANYA ADMIN:
    - Member TIDAK bisa hapus attachment (bahkan miliknya sendiri)
    - Member "revisi" = UPLOAD versi baru (append-only). File lama tetap ada
    - Admin bisa hapus (mis. bereskan salah-upload member, atau file usang)
    - Setelah approve: bahkan admin sebaiknya tidak hapus output (bukti beku) —
      kalau kamu nilai admin tetap boleh, LAPOR alasannya, jangan putuskan sepihak

B4. Download:
    - Siapa boleh lihat/download attachment task: assignee, member project task itu,
      admin (F-95 membership-based)
    - Member project LAIN -> 404 (jangan bocorkan keberadaan)
    - Lewat controller + cek permission, stream dari storage privat (A1)

B5. Log: upload -> activity_log 'attachment_uploaded' (event sudah ada, lewat observer).
    Hapus oleh admin -> log juga.

B6. UI:
    - Di detail task: daftar attachment output (nama, ukuran, siapa upload, kapan),
      tombol download. Tombol upload kalau boleh (B1). Tombol hapus HANYA admin (B3)
    - Task approved: area upload disembunyikan/disabled dengan keterangan "terkunci"

## FASE C — TEST (MySQL, F-83)

C1. tests/Feature/AttachmentUploadTest.php
    - assignee upload output ke task-nya -> sukses, tersimpan di storage privat
    - non-assignee non-admin upload -> 403
    - file > 10MB -> ditolak
    - .exe di-rename .pdf (mime spoofing) -> DITOLAK (A2)
    - upload ke task approved -> DITOLAK (F-105)

C2. tests/Feature/AttachmentDeleteTest.php
    - member hapus attachment -> 403 (F-106)
    - admin hapus -> sukses + log
    - member "revisi" = upload kedua -> DUA file ada (append-only, F-106)

C3. tests/Feature/AttachmentDownloadTest.php
    - assignee/member-project/admin download -> sukses
    - member project lain -> 404 (F-95)
    - path traversal attempt -> ditolak (A1)

C4. 189 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN simpan file di public root / akses via URL langsung (A1)
JANGAN percaya ekstensi/mime klaim klien (A2)
JANGAN pakai nama file user sebagai path (A3)
JANGAN izinkan member hapus attachment (F-106)
JANGAN izinkan upload output ke task approved (F-105)
JANGAN pakai permission RBAC untuk gating member (F-95 — assignee/membership)
JANGAN bangun evidence/extension flow -> H6 (tabel siap, jangan di-wire sekarang)
JANGAN pakai S3 / cloud storage -> v1 (lokal saja)
JANGAN hardcode nama status (F-44)
JANGAN scan virus / dependency baru tanpa approval
JANGAN deploy/L13 · JANGAN edit dokumen docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-N di komentar keamanan
(kenapa mime nyata, kenapa storage privat, kenapa beku saat approve).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] file tersimpan di storage/app/private, TIDAK bisa diakses via URL langsung
[ ] mime spoofing (.exe->.pdf) ditolak (A2)
[ ] upload output ke task approved ditolak (F-105)
[ ] member hapus attachment -> 403 (F-106)
[ ] member revisi = append (dua file), bukan overwrite (F-106)
[ ] member project lain download -> 404 (F-95)
[ ] upload/hapus tercatat di activity_log (F-51)
[ ] php artisan test -> SEMUA lulus MySQL (189 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini fondasinya keamanan, bukan fitur.** Upload file adalah pintu masuk paling umum untuk serangan di aplikasi web — file berbahaya menyamar sebagai dokumen, nama file yang menembus folder lain, file yang bisa diunduh siapa saja lewat URL tebakan. Fase A menutup ketiganya: mime divalidasi dari isi (bukan nama), file disimpan dengan nama acak di folder privat, unduhan hanya lewat controller yang cek izin. Ini bukan paranoia — ini standar minimum, dan lebih murah dibangun benar sekarang daripada ditambal setelah ada file jahat masuk.

**F-105 + F-106 bekerja sama menjaga jejak kerja jujur.** Output beku saat approve (tidak bisa ditukar setelah dinilai), dan member append-only (riwayat revisi tersimpan penuh, admin yang membersihkan). Untuk sistem yang nanti menentukan reward/punishment (v2.0), "apa yang benar-benar diserahkan dan kapan" harus tidak bisa dimanipulasi belakangan. Ini fondasi audit yang Boss butuh sebelum uang terlibat.

**Satu titik Jarvis minta Claude Code lapor, bukan putuskan sendiri (B3):** apakah admin tetap boleh menghapus output SETELAH approve. Bekunya F-105 soal member; apakah admin juga terkunci dari menghapus bukti beku adalah pertanyaan integritas yang Boss mungkin punya pendapat. Dibiarkan terbuka supaya Boss yang tentukan, bukan Claude Code diam-diam.

**F-97 sekarang 5 item browser tertunda.** Bukan blokir Hari-5, tapi Jarvis tandai keras: **sebelum v0.8 ditutup (H7), kelimanya wajib diverifikasi** — entah Chrome extension akhirnya tersedia, entah Boss klik manual 10 menit di dev server. v0.8 tidak boleh ditutup dengan lima layar yang belum pernah dilihat mata manusia. Boss kena pola ini sekali (F-73).

**Peta v0.8:** ~~H1~~ ~~H2~~ ~~H3~~ ~~H4~~ -> H5 attachment -> H6 extension + 2 notif (lengkapi 10 trigger) -> H7 buffer + verifikasi utuh + tutup F-97.
