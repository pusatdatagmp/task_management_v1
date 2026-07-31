# PROMPT v0.8 HARI-1 — COUNTER LIVE UI (F-38, F-41, F-94)

> **Development lokal. TIDAK deploy** (keputusan Boss). L13 ditahan sampai Boss angkat sendiri.
> Hari ini: menampilkan realisasi kerja BERJALAN di layar, konsisten dengan angka yang dibekukan.

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 1 file:
```
docs/04-FINDING-REGISTRY.md   <- +F-94 + catatan HARDEN & lubang RBAC/F-63
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — LAPOR STATUS DULU (DILARANG MENULIS KODE)

Tiga hal Jarvis butuh SEBELUM kamu menulis kode. Jawab semua, lalu BERHENTI.

### 0A — KONFIRMASI RBAC (laporan sudah diterima Jarvis)
Laporan RBAC sudah masuk: 7 permission, member=nol permission, Gate::before data-driven.
Konfirmasi cepat SAJA (1 baris tiap): Role::count()=2 sistem, User::whereNull('role_id')->count()=0.
Ini cuma sanity-check bahwa DB lokal kamu = laporan RBAC. Bukan audit ulang.

### 0B — DUA ITEM BROWSER HARDEN yang tertunda (F-75)
Chrome extension ditolak sesi lalu. Kalau tersedia sekarang, buktikan:
- Tambah hari libur via form -> segmen yang melewatinya berkurang benar
- Unassign member bertask-aktif via form -> pesan tolak MUNCUL di React (F-87)
Kalau extension masih tidak tersedia: nyatakan eksplisit "belum terverifikasi",
jangan klaim lulus. (Cara kamu menangani ini di HARDEN sudah benar.)

### 0C — F-63 (BLOCKER HARI-2, angkat sekarang)
Hari-2 (dashboard) TIDAK BISA ditulis tanpa keputusan Boss ini:
  Task 2 jam dikerjakan 2 assignee -> realisasi & beban tiap orang dihitung
  bagaimana? Poin dibagi atau utuh?
Angkat ke Boss. JANGAN putuskan sendiri. Ini aturan bisnis, bukan teknis.
Hari-1 TIDAK butuh ini (counter per-segmen, bukan agregasi) -> boleh lanjut
tanpa jawaban, TAPI Boss harus memutuskan sebelum "LANJUT" ke Hari-2.

Lalu LAPORKAN checklist Fase A-C Hari-1.
BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS

v0.5 + RBAC + HARDEN selesai. BusinessHoursCalculator sudah matang (F-66/F-43).
Time segments direkam sejak v0.5. Yang belum: MENAMPILKANNYA berjalan di layar.

Hari ini murni FRONTEND + 1 endpoint pembantu. Tidak ada perubahan skema,
tidak ada perubahan rumus. Membaca data, bukan mengubahnya.

## FASE A — ENDPOINT AKUMULASI (F-94 — HINDARI DUPLIKASI KALKULATOR)

🔴 MASALAH INTI: counter live harus KONSISTEN dengan angka yang dibekukan saat
approve. Kalau segmen dibuka Jumat 16:00 dan sekarang Senin 09:00, counter TIDAK
BOLEH menampilkan 65 jam — harus 2 jam (cap jendela kerja F-57 berlaku juga untuk
tampilan live).

🔴 JANGAN tulis ulang BusinessHoursCalculator di JavaScript.
   Dua kalkulator (PHP + JS) = dua sumber kebenaran = salah satu pasti membusuk
   (pola F-72, F-76). Frontend TIDAK menghitung business-hours sendiri.

A1. Endpoint (atau data via Inertia props) yang mengembalikan, untuk task
    yang punya segmen terbuka:
    - accumulated_minutes : realisasi SAMPAI penutupan jendela terakhir
      (dihitung BusinessHoursCalculator yang sudah ada — sumber tunggal)
    - is_in_work_window   : apakah SEKARANG di dalam jam kerja?
    - window_ends_at      : kapan jendela kerja hari ini tutup (ISO, WIB)
    - segment_started_at  : untuk frontend menick porsi berjalan

A2. Frontend logika tampilan:
    - is_in_work_window = true  -> counter menick naik per detik dari
      accumulated + (now - awal-porsi-berjalan), BERHENTI di window_ends_at
    - is_in_work_window = false -> tampilkan accumulated STATIS (paused),
      badge "di luar jam kerja"
    Ini membuat pause/resume F-57 terjadi sendirinya di tampilan, sama seperti
    di kalkulasi (F-38: counter = calculated, bukan state tersimpan).

A3. F-38 MUTLAK:
    - NOL state counter tersimpan di DB
    - NOL scheduler/polling per menit
    - Frontend menick secara lokal (setInterval klien), refetch akumulasi HANYA
      saat load halaman / perubahan status task
    - Kalau server mati, counter klien tetap jalan dari data terakhir; tidak korup

## FASE B — TAMPILKAN COUNTER

B1. Di halaman DETAIL TASK: badge besar "Sedang dikerjakan — 1j 23m"
    kalau task di status is_work_state. Sembunyikan kalau bukan.

B2. Di MY TASKS: counter kecil di baris task yang sedang IN_PROGRESS.

B3. Di LIST VIEW task: indikator ringkas (dot + waktu) untuk task work-state.

B4. 🔴 F-44: deteksi "sedang dikerjakan" lewat FLAG is_work_state, bukan nama status.

B5. Multi-assignee: setiap assignee punya segmen sendiri (task_time_segments.user_id).
    Counter yang ditampilkan = segmen milik USER YANG LOGIN, bukan gabungan.
    (Ini TIDAK menyentuh F-63 — F-63 soal agregasi dashboard, ini tampilan per-user.)

B6. 🔴 F-95: gating counter BERBASIS ASSIGNEE/MEMBERSHIP, bukan permission.
    Member punya NOL permission (RBAC). Counter muncul di halaman member (detail
    task, My Tasks). Cek "task ini di-assign ke saya / di project saya", JANGAN
    can('...'). Jangan bikin permission baru untuk melihat counter.

## FASE C — TEST

C1. tests/Feature (endpoint akumulasi):
    - task work-state dengan segmen terbuka -> akumulasi benar via kalkulator
    - segmen dibuka Jumat sore, "now" Senin pagi -> akumulasi = jam kerja saja
      (bukan wall-clock) — konsistensi F-94
    - task bukan work-state -> tidak ada counter berjalan
    - user hanya melihat counter segmennya sendiri (multi-assignee)

C2. Kalau ada logika waktu di frontend: unit test kecil untuk fungsi tick
    (in-window menick, out-window statis, berhenti di window_ends_at).

C3. Test lama tetap lulus (143 di MySQL). F-78 berlaku.

## DILARANG KERAS

JANGAN tulis ulang BusinessHoursCalculator di JS (F-94/F-72/F-76)
JANGAN simpan state counter di DB (F-38)
JANGAN buat scheduler/polling per menit (F-38)
JANGAN sentuh rumus/angka yang sudah beku (F-39)
JANGAN hardcode nama status (F-44)
JANGAN buat dashboard agregasi -> Hari-2 (butuh F-63 dulu)
JANGAN buat recurring/attachment/extension -> hari berikutnya
JANGAN mulai upgrade L13 / deploy (keputusan Boss: lokal dulu)
JANGAN install dependency tanpa approval Boss
JANGAN edit dokumen docs/

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-N di komentar business rule.

## DEFINITION OF DONE

🔴 F-83: test di MySQL. F-75: item [BROWSER] bukti browser (kalau extension tersedia).

[ ] [BROWSER] task IN_PROGRESS -> counter tampil & menick di detail task
[ ] [BROWSER] di luar jam kerja -> counter statis, badge "di luar jam kerja"
[ ] endpoint akumulasi pakai BusinessHoursCalculator, BUKAN logika JS baru
[ ] grep business.hours di resources/js -> tidak ada reimplementasi kalkulator
[ ] segmen Jumat sore->Senin pagi -> akumulasi = jam kerja (test C1)
[ ] user multi-assignee lihat counter segmennya sendiri
[ ] grep scheduler/cron baru -> tidak ada (F-38)
[ ] php artisan test -> SEMUA lulus di MySQL (143 lama + baru)
[ ] npx tsc 0 error, pint + build + lint bersih

## FORMAT LAPORAN AKHIR
STATUS / DIUBAH / BUKTI (+browser) / DEVIASI (nol -> "NOL") / RISIKO / NEXT

Mulai dari LANGKAH 0 (0A, 0B, 0C). Jangan tulis kode sebelum Boss "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Hari-1 sengaja ringan — dan itu disengaja.** Setelah RBAC (menyentuh semua controller) dan HARDEN (mengubah rumus), Hari-1 v0.8 murni **membaca dan menampilkan**. Nol perubahan skema, nol perubahan rumus. Hari pemulihan sebelum recurring engine (Hari-4) yang berat.

**Inti teknis Hari-1 ada di F-94.** Counter yang berdetak di layar harus menghasilkan angka yang **sama persis** dengan yang dibekukan saat task di-approve. Kalau layar menampilkan "65 jam" untuk kerja yang akan dibekukan jadi "2 jam", tim kehilangan kepercayaan pada angka. Solusinya: **backend tetap satu-satunya yang menghitung** (kalkulator yang sudah matang di HARDEN), frontend hanya "menick" porsi berjalan. Ini mencegah kalkulator kembar di JS — penyakit F-72/F-76 yang sudah dua kali kita bunuh.

**Tiga hal Boss urus di LANGKAH 0:**
1. **Laporan RBAC** akhirnya sampai ke Jarvis — status user/role/permission aktual
2. **Dua item browser HARDEN** diverifikasi (kalau Chrome extension tersedia)
3. **F-63 diangkat** — dan **Boss harus memutuskannya sebelum "LANJUT" ke Hari-2**. Hari-1 tidak butuh, Hari-2 tidak bisa jalan tanpanya.

**Catatan CLAUDE.md:** file di 200/200 baris. Hari-1 **tidak menambah** aturan ke sana (semua merujuk F-38/F-44 yang sudah ada), jadi pemangkasan **belum perlu** — dan melakukannya tanpa kebutuhan nyata itu sendiri pemborosan + risiko refactor. Jarvis pangkas saat ada finding yang benar-benar harus masuk CLAUDE.md, bukan sebelumnya.

**Deploy & L13 tidak Jarvis singgung lagi** — sesuai keputusan Boss fokus lokal. Akan diangkat hanya kalau Boss mengangkatnya.
