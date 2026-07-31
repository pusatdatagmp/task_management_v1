# PROMPT v0.8 HARI-6 — EXTENSION FLOW + 2 TRIGGER NOTIF (F-50, F-47, F-107)

> Melengkapi 10 trigger notifikasi (kini 8). Perpanjangan deadline pakai attachment evidence (H5).
> Data lokal. Setelah ini tinggal H7 (buffer + verifikasi utuh + tutup F-97).

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-107 (kunci hapus pasca-approve), audit H5
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md §3.11 deadline_extensions · §3.12 attachments
· docs/03-BUSINESS-FLOW.md §4 · docs/04-FINDING-REGISTRY.md (F-50, F-47, F-62, F-104..F-107).

LAPORKAN:
- Konfirmasi paham: original_due_date dijaga saat approve (F-47), evidence pakai
  infrastruktur attachment H5 (type=evidence), jumlah extension = metrik bukan hukuman (F-62)
- 🔴 KERJAKAN DULU F-107 (utang H5): kunci hapus attachment task approved — bahkan admin.
  destroy() sekarang cuma cek can:task.manage, belum cek approved_at. Tambahkan.
- F-97: 6 item browser tertunda. Extension tersedia? Kalau ya, ini saat verifikasi batch
  (H7 sudah dekat). Kalau tidak, nyatakan, lanjut
- Checklist Fase A-D
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Tabel deadline_extensions & attachments sudah ada (skema Hari-1). Attachment
infrastruktur + type=output selesai H5. Hari ini: extension flow + wire evidence +
2 trigger notif terakhir (#9 diajukan, #10 diputuskan) -> genap 10 (F-35).

## FASE A — TUTUP UTANG F-107 (dari H5)

A1. AttachmentController::destroy(): tolak hapus kalau task attachment itu sudah
    approved (approved_at terisi / is_completed). Berlaku UNTUK SEMUA termasuk admin.
    Pesan: "Lampiran task yang sudah disetujui terkunci permanen."
    Cek pakai FLAG is_completed + approved_at (F-44), bukan nama status.

A2. Test: admin hapus attachment task approved -> DITOLAK (F-107).
    Admin hapus attachment task belum approved -> tetap boleh (F-105).

## FASE B — EXTENSION FLOW (F-50, F-47)

B1. Member ajukan perpanjangan (assignee task, gating assignee bukan permission F-95):
    - task (yang di-assign ke dia, belum selesai)
    - requested_due_date (tenggat baru)
    - additional_minutes (tambahan budget estimasi)
    - reason (wajib, teks)
    - evidence: attachment type=evidence, deadline_extension_id terisi (infra H5)
    Status awal: pending.

B2. Admin approve/reject (permission task.approve — RBAC):
    - 🔴 APPROVE (F-47): SEBELUM ubah due_date, SIMPAN original_due_date kalau belum
      terisi (task belum pernah diperpanjang). due_date -> requested_due_date.
      estimated_minutes += additional_minutes.
      ALASAN F-47: metrik tepat-waktu harus tetap jujur. Kalau due_date asli hilang,
      task yang telat lalu diperpanjang akan tampak "selalu tepat waktu" -> KPI bohong.
      original_due_date jadi jangkar kejujuran.
    - REJECT: status rejected + alasan. due_date TIDAK berubah.
    - Satu task bisa diperpanjang >1x: original_due_date diisi HANYA saat pertama
      (jangan timpa dengan due_date yang sudah tergeser).

B3. F-62: jumlah extension per user = metrik KPI mentah. CATAT, JANGAN hukum/blokir
    otomatis. Tidak ada batas jumlah pengajuan.

B4. UI:
    - Member: halaman "Perpanjangan Saya" — ajukan + daftar status pengajuan
    - Admin: halaman "Perpanjangan" — antrean pending + approve/reject + lihat evidence
    - Di detail task: tampilkan kalau ada original_due_date ("tenggat asli: X, diperpanjang")

## FASE C — 2 TRIGGER NOTIF TERAKHIR (F-35 -> genap 10)

C1. Trigger #9: extension DIAJUKAN -> notif ke ADMIN (task.approve holder)
C2. Trigger #10: extension DIPUTUSKAN (approve/reject) -> notif ke PEMOHON
    Sertakan hasil + alasan (kalau reject).
C3. 🔴 F-36: pelaku tidak dapat notif atas aksinya sendiri.
    🔴 Lewat pola notifikasi yang sudah ada (H6 v0.5). Idempotency untuk yang
    lewat scheduler tidak relevan di sini (ini event-driven, bukan cron).
C4. Verifikasi: total trigger notif kini 10 (F-35 lengkap). Daftar & konfirmasi
    kesepuluhnya di laporan.

## FASE D — TEST (MySQL, F-83)

D1. tests/Feature/ExtensionFlowTest.php
    - member ajukan -> pending, evidence tersimpan (type=evidence)
    - non-assignee ajukan -> ditolak (F-95)
    - admin approve -> original_due_date terisi, due_date & estimated_minutes update (F-47)
    - approve KEDUA kalinya -> original_due_date TIDAK ditimpa (tetap yang pertama)
    - reject -> due_date tak berubah, pemohon dapat notif + alasan
    - member tanpa task.approve approve extension -> 403

D2. tests/Feature/ExtensionNotifTest.php
    - ajukan -> admin dapat notif (#9)
    - putuskan -> pemohon dapat notif (#10)
    - pelaku tak dapat notif sendiri (F-36)

D3. tests/Feature (F-107 dari Fase A):
    - hapus attachment task approved -> ditolak bahkan admin

D4. 200 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN biarkan hapus attachment task approved (F-107 — tutup di Fase A)
JANGAN timpa original_due_date saat perpanjangan ke-2+ (F-47)
JANGAN hukum/batasi jumlah extension otomatis (F-62)
JANGAN pakai permission untuk gating member ajukan (F-95 assignee)
JANGAN kirim notif ke pelaku aksinya sendiri (F-36)
JANGAN hardcode nama status (F-44)
JANGAN buat trigger notif ke-11 -> genap 10 saja (F-35)
JANGAN deploy/L13 · JANGAN edit dokumen docs/ · JANGAN dependency tanpa approval

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] hapus attachment task approved -> ditolak bahkan admin (F-107)
[ ] member ajukan extension + evidence -> pending
[ ] approve -> original_due_date terisi, due_date+estimasi update (F-47)
[ ] approve ke-2 -> original_due_date TIDAK ditimpa
[ ] reject -> due_date tak berubah
[ ] trigger #9 (admin) + #10 (pemohon) jalan, pelaku tak dapat notif sendiri
[ ] total 10 trigger notif dikonfirmasi di laporan (F-35 lengkap)
[ ] php artisan test -> SEMUA lulus MySQL (200 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DAFTAR 10 TRIGGER / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini menutup satu utang lalu melengkapi satu sistem.** Utang: F-107 (kunci hapus attachment approved) yang sengaja ditunda dari H5 supaya jadi keputusan sadar Boss, bukan default diam-diam. Sistem: notifikasi genap 10 trigger — angka yang Boss tetapkan sejak awal (F-35).

**F-47 adalah jantung hari ini, dan halus.** Saat perpanjangan disetujui, `due_date` bergeser — tapi `original_due_date` menyimpan tenggat asli. Tanpa ini, task yang **telat lalu diperpanjang** akan tampak "selalu tepat waktu" di metrik. `original_due_date` adalah jangkar kejujuran: perpanjangan mengubah target kerja, **tidak** menghapus fakta bahwa target semula terlewat. Untuk sistem yang menilai kedisiplinan (v1.5), ini menentukan apakah angka on-time jujur atau kosmetik.

**F-62 sengaja tidak menghukum.** Jumlah perpanjangan dicatat sebagai metrik, tapi sistem tidak memblokir otomatis. Kenapa: perpanjangan yang sah (nunggu data pihak lain) tidak boleh dihukum sama dengan yang tidak. Penilaian itu tugas manusia di v1.5, bukan aturan buta. Ini prinsip anti-Goodhart (F-4) yang sama.

**Setelah H6, v0.8 tinggal H7:** buffer, verifikasi seluruh fitur end-to-end, dan **wajib tutup F-97 (6 item browser)**. Jarvis akan mendorong keras verifikasi manusia di H7 — v0.8 tidak boleh ditutup dengan enam layar yang belum pernah dilihat mata. Kalau Chrome extension tetap absen sampai H7, Boss klik manual di dev server; itu 15 menit yang mencegah pengulangan F-73.

**Peta v0.8:** ~~H1..H5~~ -> H6 extension + notif genap 10 -> **H7 buffer + verifikasi + tutup F-97 -> v0.8 TUTUP.**
