# PROMPT v1.0 HARI-2 — DRAG-DROP + F-45 + OPTIMISTIC (F-110, F-111)

> Hari PALING RAWAN v1.0. Drag lewat dnd-kit; drop = status change lewat service lama.
> Kalau molor, boleh pecah: drag mekanik dulu, integrasi service kemudian.
> Data lokal. Aturan C (kolom tak-sah disable saat menyeret).

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-110 (aturan C+dnd-kit), F-111 (drop lewat service)
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/03-BUSINESS-FLOW.md §2 lifecycle (F-45 transisi)
· docs/04-FINDING-REGISTRY.md (F-110, F-111, F-45, F-41, F-51, F-33, F-95).

LAPORKAN:
- Ringkas dengan kata-katamu: aturan C (kolom disable), drop lewat service, optimistic+revert
- Konfirmasi kamu akan pasang dnd-kit (@dnd-kit/core + @dnd-kit/sortable) — Boss approve.
  Sebutkan versi + ukuran sebelum install
- Rencana: bagaimana drop memanggil endpoint status-change yang SUDAH ADA (bukan bikin baru)
- F-97: 7 item tertunda. Extension tersedia? Board+drag paling butuh mata manusia
- Checklist Fase A-D
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Board read-only (H1) sudah render. Hari ini: bikin kartu bisa diseret antar kolom,
dan drop mengubah status LEWAT JALUR YANG SAMA dengan dropdown status di detail task.

🔴 PRINSIP INDUK (F-111): drag adalah UI. Perubahan status TETAP lewat service +
observer (F-45 validasi, F-41 segmen buka/tutup, F-51 activity_log). JANGAN bikin
endpoint status baru yang bypass observer -> itu bikin segmen/log bolong = data KPI rusak.

## FASE A — DND-KIT SETUP

A1. Install @dnd-kit/core + @dnd-kit/sortable (Boss sudah approve). Laporkan versi.
A2. Kartu jadi draggable, kolom jadi droppable. Keyboard-accessible (dnd-kit default).
A3. Belum sambung ke server dulu — pastikan gerakan visual mulus dulu (drag mekanik).

## FASE B — ATURAN C (F-110): KOLOM TAK-SAH DI-DISABLE SAAT MENYERET

B1. Saat user MULAI menyeret kartu dari status posisi P:
    - Kolom SAH: posisi P (batal), P+1 (maju satu, F-45), dan semua posisi < P (mundur bebas)
    - Kolom TAK-SAH: posisi > P+1 (lompat maju) -> redup + tidak menerima drop
    Hitung "sah" dari FLAG/posisi status (F-44), bukan nama.
B2. Visual jelas: kolom tak-sah opacity turun / overlay "tidak bisa ke sini".
    User paham SEBELUM melepas, bukan ditolak sesudah (inti F-110).
B3. Kalau user tetap paksa lepas di kolom tak-sah (mis. keyboard) -> no-op, kartu diam.

## FASE C — DROP = STATUS CHANGE (F-111, optimistic F-33)

C1. Drop di kolom sah -> panggil ENDPOINT STATUS-CHANGE YANG SUDAH ADA (yang dipakai
    dropdown detail task). JANGAN bikin baru. Lewat service -> observer jalan:
    - F-45 validasi (harusnya sudah sah dari aturan C, tapi server tetap validasi —
      JANGAN percaya client saja)
    - F-41 segmen buka saat masuk is_work_state / tutup saat keluar
    - F-51 activity_log status_changed
C2. 🔴 OPTIMISTIC (F-33): kartu pindah ke kolom baru INSTAN di layar. Lalu server konfirmasi.
    - Server sukses -> tetap
    - Server GAGAL (validasi/error) -> kartu BALIK ke kolom asal + toast jelas. Revert mulus,
      jangan biarkan kartu "nyangkut" di tempat salah
C3. 🔴 SIAPA "PEKERJA" saat drop membuka segmen (multi-assignee)?
    Board dipakai admin & member. Kalau ADMIN menyeret kartu ke is_work_state, segmen
    dibuka untuk SIAPA? Bukan admin (dia tidak mengerjakan).
    ATURAN: drop ke is_work_state oleh admin -> segmen TIDAK otomatis dibuka atas nama
    admin. Segmen kerja hanya untuk assignee. Kalau perlu, buka untuk assignee (kalau
    tunggal) atau JANGAN buka (kalau ambigu/banyak assignee) dan andalkan assignee
    memulai sendiri dari detail/My Tasks. LAPORKAN pilihanmu — ini keputusan halus.
C4. Member hanya bisa seret task yang di-assign ke dia (F-95). Task orang lain -> tidak draggable.

## FASE D — TEST (MySQL, F-83)

D1. tests/Feature/BoardDragTest.php (uji SERVER-side dari drop, karena drag visual
    tak bisa di-unit-test PHP — uji endpoint yang dipanggil drop):
    - drop maju +1 (TODO->DIKERJAKAN) -> status berubah, segmen dibuka (F-41)
    - drop lompat maju (TODO->SELESAI) -> DITOLAK server (F-45), meski client harusnya cegah
    - drop mundur -> boleh, rejection tak berubah
    - drop -> activity_log status_changed tercatat (F-51)
    - member seret task orang lain -> 403 (F-95)
    - admin drop ke is_work_state -> segmen TIDAK atas nama admin (C3)
D2. Kalau ada logika "kolom sah" di frontend -> unit test kecil (node --test):
    dari posisi P, sah = {<P, P, P+1}, tak-sah = {>P+1}
D3. 222 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN bikin endpoint status-change baru yang bypass observer (F-111)
JANGAN percaya validasi client saja — server tetap validasi F-45 (C1)
JANGAN buka segmen atas nama admin yang menyeret (C3)
JANGAN aturan-per-peran untuk F-45 (Boss pilih C, bukan B)
JANGAN hardcode nama status (F-44)
JANGAN hitung ulang counter/beban di board (F-109)
JANGAN buat komentar/activity-log-UI -> H3/H4
JANGAN deploy/L13 · JANGAN edit docs/ · JANGAN dependency lain selain dnd-kit

## STANDAR KOMENTAR
CLAUDE.md §3. Sebut F-110/F-111 di komentar drag (kenapa disable-kolom, kenapa
drop lewat service, kenapa optimistic revert).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] — drag SANGAT butuh mata manusia; kalau extension
absen, tambahkan ke CHECKLIST-VERIFIKASI-MANUAL.md dengan langkah drag eksplisit.

[ ] kartu bisa diseret antar kolom (dnd-kit, keyboard-accessible)
[ ] saat menyeret: kolom lompat-maju REDUP + tak menerima (aturan C, F-110)
[ ] drop sah -> status berubah lewat service, segmen+log jalan (F-111)
[ ] drop lompat maju di server -> DITOLAK (F-45, jangan percaya client)
[ ] optimistic: drop instan, gagal -> revert mulus + toast (F-33)
[ ] admin drop ke work-state -> segmen TIDAK atas nama admin (C3, dilaporkan)
[ ] member tak bisa seret task orang lain (F-95)
[ ] grep endpoint status baru -> tidak ada (reuse yang lama, F-111)
[ ] php artisan test -> SEMUA lulus MySQL (222 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol->"NOL") / KEPUTUSAN C3 (segmen saat admin drop) / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari ini paling rawan di v1.0 — dan intinya satu kalimat: drag hanya menggerakkan gambar, keputusannya tetap di tempat lama.** Godaan terbesar drag-drop adalah bikin "jalan pintas" — endpoint baru yang langsung ubah status supaya drag terasa cepat. Itu melewati observer yang mencatat segmen waktu (F-41) dan activity log (F-51) — dua sumber utama data KPI Boss. Kalau drag bypass observer, task yang dipindah lewat board tidak tercatat waktunya, dan dashboard bohong. **F-111 mengunci: drop memanggil jalur yang sama persis dengan dropdown status yang sudah terbukti.**

**Aturan C yang Boss pilih membuat F-45 terasa alami, bukan menghukum.** Saat user menyeret kartu, kolom yang tidak boleh langsung redup — user lihat batasannya sebelum melepas. Bandingkan dengan "seret ke mana saja lalu ditolak": yang kedua membuat orang merasa sistemnya rewel. C mengubah aturan kaku (transisi berurutan) jadi panduan visual yang membantu.

**Satu titik Jarvis minta Claude Code lapor, bukan putuskan (C3):** saat ADMIN menyeret kartu ke kolom "dikerjakan", segmen waktu dibuka atas nama siapa? Admin tidak mengerjakan — dia cuma memindahkan. Membuka segmen atas nama admin akan mencatat waktu kerja palsu. Ini keputusan halus yang menyentuh kejujuran data, jadi Claude Code lapor pilihannya dulu.

**Kalau Hari-2 molor, boleh dipecah** — drag mekanik (Fase A-B) satu bagian, integrasi service (Fase C) bagian lain. Drag-drop terkenal makan waktu; lebih baik dua hari benar daripada satu hari rapuh.

**F-97 kini 7 item.** Drag-drop adalah fitur yang PALING butuh mata manusia — animasi, revert, kolom disable semuanya visual murni yang test tidak bisa buktikan. Kalau extension masih absen, checklist manual dapat langkah drag eksplisit. Jarvis makin mendorong: sebelum v1.0 ditutup, tumpukan ini harus dilihat.

**Peta v1.0:** ~~H1~~ -> H2 drag-drop -> H3 komentar (+ keputusan mention=trigger ke-11?) -> H4 activity log UI -> H5 buffer.
