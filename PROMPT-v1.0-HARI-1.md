# PROMPT v1.0 HARI-1 — BOARD VIEW (READ-ONLY)

> Papan Kanban TANPA drag dulu. Layout & data benar sebelum interaksi rumit (H2).
> Data lokal. Reuse komponen v0.8 — nol hitung ulang (F-109).

---

## §0. YANG BOSS LAKUKAN DULU
Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-109 (Board=tampilan), F-87 ditutup, audit H7, kickoff v1.0
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca: CLAUDE.md · docs/02-DATA-MODEL.md §3.7 statuses §3.9 tasks
· docs/03-BUSINESS-FLOW.md §2 lifecycle · docs/04-FINDING-REGISTRY.md (F-109, F-44, F-94, F-95).

LAPORKAN:
- Konfirmasi paham F-109: Board = TAMPILAN, reuse komponen v0.8, nol hitung ulang
- Komponen v0.8 yang akan di-reuse: sebutkan file counter (H1) & status badge yang ada
- F-97: 6 item browser masih tertunda. Extension tersedia sekarang? (Board mewarisi
  counter/dashboard — kalau bisa verifikasi F-97 sekalian, ideal). Kalau tidak, lanjut
- Checklist Fase A-C
- CATATAN: H2 (drag-drop) butuh keputusan Boss (F-45 aturan) + dnd-kit approval —
  JANGAN kerjakan drag hari ini, cukup pastikan layout siap menerimanya nanti
BERHENTI. Tunggu Boss "LANJUT".

## KONTEKS

Sistem punya List View (v0.5) & detail task. v1.0 menambah papan Kanban.
HARI INI READ-ONLY: render papan, belum ada drag (H2). Kenapa bertahap: drag-drop +
optimistic + revert rumit; layout yang benar dulu = pijakan stabil.

🔴 F-109: board TIDAK menghitung ulang apa pun. Angka realisasi/counter/beban dari
komponen & service v0.8 yang SAMA. Kalau kamu tergoda menulis ulang perhitungan di
board -> STOP, reuse. Dua sumber = salah satu membusuk (F-72/F-76).

## FASE A — LAYOUT PAPAN

A1. Route: GET /projects/{project}/board (permission: sama dengan lihat project —
    admin semua, member hanya project yang di-assign, F-95 membership; bukan permission
    RBAC baru). scopeBindings (F-76). Member project lain -> 404.

A2. Kolom = TaskStatus project, urut position (F-44: dari data, JANGAN hardcode nama).
    Header kolom: nama status + warna + jumlah kartu.

A3. Kartu = task di project itu, dikelompokkan per status. Tiap kartu:
    - Judul
    - Assignee (avatar stack)
    - Counter live KALAU status is_work_state + segmen terbuka (REUSE komponen H1,
      F-94 — jangan bikin counter baru)
    - Badge tenggat (hari ini/terlambat/dst — pola yang ada)
    - Poin, tipe task (badge)
    - Indikator subtask kalau ada
    Klik kartu -> buka detail task (halaman/modal yang SUDAH ADA, jangan bikin baru)

A4. Subtask: tampilkan di kartu parent (indikator "2 subtugas"), JANGAN jadi kartu
    terpisah di board (mereka bukan entitas board mandiri).

A5. Kolom kosong: empty state ringkas ("Belum ada tugas"), bukan kosong membingungkan.

## FASE B — FILTER (server-side, pola v0.5 H5)

B1. Filter: per assignee, per prioritas. Server-side (JANGAN filter di React).
B2. Filter tercermin di URL (bisa bookmark/share, konsisten v0.5 H5).
B3. Toggle: List View <-> Board View untuk project yang sama (link antar keduanya).

## FASE C — TEST (MySQL, F-83)

C1. tests/Feature/BoardViewTest.php
    - board render kolom sesuai statuses project (urut position)
    - kartu muncul di kolom status-nya yang benar
    - member project lain -> 404 (F-95)
    - member project sendiri -> 200
    - filter assignee -> hanya kartu assignee itu
    - angka counter di kartu = sumber yang sama dengan detail (F-94/F-109) — kalau
      bisa diuji, buktikan tidak ada perhitungan board terpisah
C2. 215 test lama tetap lulus. F-78.

## DILARANG KERAS

JANGAN buat drag-drop -> H2 (butuh keputusan F-45 + dnd-kit dulu)
JANGAN install dnd-kit / dependency apa pun hari ini (H2, dengan approval)
JANGAN hitung ulang counter/realisasi/beban di board (F-109 — reuse)
JANGAN bikin komponen counter/badge baru (reuse v0.8)
JANGAN hardcode nama status (F-44)
JANGAN buat halaman detail task baru (reuse yang ada)
JANGAN ubah rumus/service KPI (board cuma menampilkan)
JANGAN buat komentar/activity-log-UI -> H3/H4
JANGAN deploy/L13 · JANGAN edit docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-109 di komentar board
(kenapa reuse, bukan hitung ulang).

## DEFINITION OF DONE

🔴 F-83 test MySQL. F-75 [BROWSER] kalau extension tersedia.

[ ] /projects/{id}/board render kolom dari statuses (urut position)
[ ] kartu di kolom status yang benar, klik -> detail task yang ADA
[ ] counter di kartu = komponen H1 (reuse, bukan baru) — grep tidak ada counter board baru
[ ] member project lain -> 404
[ ] filter assignee/prioritas server-side + tercermin URL
[ ] toggle List <-> Board jalan
[ ] grep perhitungan business-hours/beban baru di board -> tidak ada (F-109)
[ ] php artisan test -> SEMUA lulus MySQL (215 lama + baru)
[ ] npx tsc 0, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI / DEVIASI (nol->"NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0. Jangan tulis kode sebelum "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa read-only dulu, bukan langsung drag-drop?**
Board View punya dua bagian: layout (susun kartu di kolom) dan interaksi (seret + optimistic + revert + integrasi status). Yang kedua jauh lebih rumit dan rawan. Membangun layout dulu memberi pijakan stabil — kalau kartu sudah tampil benar di kolom yang benar, Hari-2 tinggal menambah gerakan di atas fondasi yang sudah terbukti. Menggabung keduanya = dua sumber bug bercampur, susah dilacak.

**F-109 adalah rem penting hari ini.** Board menampilkan counter, tenggat, beban — semua sudah dihitung service v0.8. Godaan terbesar saat membangun tampilan baru adalah "hitung ulang saja di sini biar gampang". Itu menciptakan kalkulator kembar — penyakit F-72 (dua serializeDate) dan F-76 (dua cek scope) yang sudah dua kali kita bunuh. Board **reuse**, tidak menghitung. Kalau counter di kartu board beda semenit dari counter di detail task, itu tanda F-109 dilanggar.

**Tiga keputusan untuk Hari-2/3 — belum sekarang:**
- **F-45 × drag-drop** (A/B/C) sebelum H2 — rekomendasi C (disable kolom tak-sah saat menyeret)
- **dnd-kit** approval sebelum H2
- **mention = trigger ke-11?** sebelum H3

Hari-1 tidak butuh satu pun — layout read-only aman jalan. Jarvis tagih ketiganya di prompt harinya.

**F-97 tetap dibawa.** Board mewarisi komponen counter & dashboard v0.8. Kalau Chrome extension muncul saat Hari-1, verifikasi F-97 sekalian akan sangat menghemat — karena Hari-2 (drag-drop) adalah tempat terburuk untuk menemukan bug UI lama yang lolos test.

**Peta v1.0:** H1 board read-only -> H2 drag-drop -> H3 komentar -> H4 activity log UI -> H5 buffer.
