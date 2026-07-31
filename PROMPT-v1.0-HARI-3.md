# PROMPT v1.0 HARI-3 — KOMENTAR TASK + MENTION (F-113, F-114, F-115)

> Diskusi di dalam task + @mention -> notifikasi. Tabel komentar terpisah dari audit log.
> Data lokal.

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-112 (resolveSegmentWorker), F-113/114/115 (komentar), audit H2
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md (skema notifikasi & activity_log)
· docs/03-BUSINESS-FLOW.md §9 notifikasi · docs/04-FINDING-REGISTRY.md (F-113, F-114, F-115, F-51, F-95, F-36).

LAPORKAN:
- Konfirmasi: komentar tabel TERPISAH (F-113), mention=notif kolaborasi (F-114),
  soft-delete oleh penulis (F-115)
- Mekanisme notifikasi yang sudah ada (H6 v0.5 + H6 v0.8) yang akan di-reuse untuk mention
- F-97: 8 item tertunda. Extension tersedia?
- Checklist Fase A-D
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Task punya detail + attachment + riwayat status. Belum ada DISKUSI. Hari ini:
komentar per task + @mention yang memberi notifikasi.

🔴 F-113: komentar TABEL SENDIRI (comments), BUKAN activity_log. Activity log adalah
jejak audit murni (F-51) — sumber 4/6 metrik KPI. Mencampur komentar user ke sana =
mencemari sumber KPI. Pisahkan.

## FASE A — SKEMA & MODEL KOMENTAR

A1. Migration comments:
    id, task_id, user_id (penulis), body (teks/rich?), 
    deleted_at (SOFT DELETE, F-115), created_at, updated_at
    organization_id (F-5). Index task_id.
A2. Model Comment: SoftDeletes trait, SerializesDatesInAppTimezone (F-72),
    BelongsToOrganization (F-5).
A3. body: teks biasa cukup untuk v1.0 (rich text komentar -> v1.1 kalau perlu).
    Simpan mention sebagai referensi user_id yang bisa di-parse (mis. @[nama](id)
    atau simpan daftar mentioned_user_ids). Pilih pola yang JELAS, jelaskan.

## FASE B — CRUD KOMENTAR (F-115)

B1. Buat komentar di task:
    - Siapa: assignee task ATAU member project ATAU admin (F-95 membership —
      diskusi terbuka untuk yang terlibat di project, bukan permission RBAC)
    - body wajib (tidak boleh kosong)
B2. Edit komentar: HANYA penulis. Tandai "diedit" (updated_at != created_at).
B3. Hapus komentar: HANYA penulis, SOFT DELETE (F-115).
    - Tampilan: "komentar dihapus" placeholder, atau sembunyikan — pilih, jelaskan
    - Data tetap ada di DB (audit). Admin bisa lihat yang terhapus? -> putuskan + lapor
B4. 🔴 Komentar TIDAK masuk activity_log (F-113). Ini tabel sendiri.
    Kalau mau jejak "ada komentar baru" di log, itu boleh sebagai EVENT ringkas
    (comment_added) TANPA menyalin isi komentar — tapi ini opsional, tanya dulu kalau ragu.

## FASE C — MENTION -> NOTIFIKASI (F-114)

C1. Parse @mention di body -> user_id yang disebut (harus member project itu).
    Mention ke non-member project -> abaikan/tolak (jangan notif orang di luar).
C2. Mention -> notifikasi ke user yang disebut. REUSE mekanisme notifikasi yang ada.
    Kategori: KOLABORASI (F-114) — bukan salah satu 10 trigger lifecycle. Beri
    tipe notif baru mis. 'mentioned', jangan paksa ke tipe lifecycle.
C3. 🔴 F-36: penulis tidak dapat notif kalau mention dirinya sendiri.
C4. Edit komentar yang MENAMBAH mention baru -> notif ke yang baru disebut saja,
    jangan spam ulang yang sudah disebut.
C5. Notifikasi mention muncul di bell dropdown (UI notif yang ada), klik -> ke task.

## FASE D — UI + TEST

D1. UI komentar di detail task:
    - Daftar komentar (penulis, waktu relatif, isi, tanda "diedit")
    - Kotak tulis + tombol kirim. @mention autocomplete member project (kalau mudah;
      kalau tidak, ketik @nama polos cukup untuk v1.0)
    - Tombol edit/hapus HANYA di komentar sendiri
D2. tests/Feature/CommentTest.php
    - member project buat komentar -> tersimpan
    - non-member project -> tidak bisa (F-95)
    - edit komentar orang lain -> 403 (F-115)
    - hapus komentar sendiri -> soft delete (deleted_at terisi, row masih ada)
    - komentar TIDAK muncul di activity_log (F-113)
D3. tests/Feature/MentionNotifTest.php
    - mention member -> dia dapat notif (F-114)
    - mention diri sendiri -> tidak dapat notif (F-36)
    - mention non-member project -> tidak ada notif
    - edit tambah mention baru -> hanya yang baru dapat notif
D4. 231 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN simpan komentar di activity_log (F-113 — tabel sendiri)
JANGAN hard delete komentar (F-115 soft delete)
JANGAN izinkan edit/hapus komentar orang lain (F-115 penulis saja)
JANGAN notif mention ke non-member project (C1)
JANGAN notif ke diri sendiri (F-36)
JANGAN pakai permission RBAC untuk komentar (F-95 membership)
JANGAN buat activity-log-UI -> H4
JANGAN deploy/L13 · JANGAN edit docs/ · JANGAN dependency tanpa approval

## STANDAR KOMENTAR
CLAUDE.md §3. Sebut F-113 (kenapa tabel terpisah), F-115 (kenapa soft delete).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] komentar di task oleh member project -> tersimpan (F-95)
[ ] edit/hapus HANYA penulis, hapus = soft delete (F-115)
[ ] komentar TIDAK di activity_log (F-113) — grep/test buktikan
[ ] mention member -> notif kategori 'mentioned' (F-114)
[ ] mention diri sendiri -> tidak ada notif (F-36)
[ ] mention non-member -> tidak ada notif
[ ] php artisan test -> SEMUA lulus MySQL (231 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Keputusan terpenting hari ini sudah dibuat sebelum kode ditulis: komentar tidak menyentuh activity log.** Activity log adalah kotak hitam yang merekam 4 dari 6 metrik KPI Boss (F-51) — ia harus tetap murni jejak fakta sistem, bukan tempat orang mengobrol. Kalau komentar bercampur ke sana, nanti sulit membedakan "sistem mencatat status berubah" dari "Budi menulis 'nanti aku cek'". Tabel komentar sendiri menjaga sumber KPI tetap bersih. Ini prinsip yang sama dengan kenapa kita pisahkan banyak hal sepanjang proyek: satu sumber, satu tujuan.

**Mention sebagai kategori kolaborasi (F-114) menjaga angka 10 tetap bermakna.** Sepuluh trigger yang Boss tetapkan adalah event siklus hidup tugas — hal yang terjadi PADA tugas. Mention adalah orang memanggil orang — event antar-manusia. Membedakannya berarti kalau nanti Boss meninjau "notifikasi lifecycle", angkanya tetap 10; kolaborasi dihitung terpisah. Bukan melanggar keputusan lama, memperluas dengan kategori baru.

**Soft delete (F-115) memberi keluwesan tanpa kehilangan jejak.** Orang perlu bisa menghapus komentar yang salah ketik atau keliru — diskusi bukan catatan resmi. Tapi di sistem akuntabilitas kerja, menghapus total bisa jadi celah ("aku tidak pernah bilang begitu"). Soft delete menyelesaikan keduanya: hilang dari pandangan, tetap ada di catatan.

**F-97 kini 8 item.** Board + drag (item 7-8) ditandai paling butuh mata manusia. Jarvis ulangi: sebelum v1.0 ditutup (H5), tumpukan ini wajib dilihat. Komentar & mention (H3) menambah item 9 nanti. Makin lama ditunda, makin banyak yang menumpuk untuk satu sesi verifikasi.

**Peta v1.0:** ~~H1~~ ~~H2~~ -> H3 komentar + mention -> H4 activity log UI -> H5 buffer + verifikasi (termasuk tutup F-97).
