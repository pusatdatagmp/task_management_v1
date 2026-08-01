# CHECKLIST VERIFIKASI MANUAL — F-97 (v0.8, 6 item) + v1.0 Board/Komentar/Log (4 item) + v1.2 H4-H5 (3 item) + v1.2 DS-1 (1 item) + v1.2 DS-4b (1 item)

> **Kenapa file ini ada:** Chrome extension untuk Claude Code absen 12+ sesi berturut.
> 12 fitur di bawah SUDAH lulus test otomatis (unit + feature HTTP end-to-end di
> MySQL), tapi **belum pernah dilihat mata manusia** (F-73/F-75 — `/login` pernah
> mati 3 hari sambil test hijau, jangan terulang). Jarvis TIDAK BOLEH klaim "LULUS"
> untuk item-item ini sampai Boss coret satu per satu di sini.
>
> **v1.0 H1 (item 7), H2 (item 8), H3 (item 9) & H4 (item 10):** Board View +
> drag-drop + komentar/mention + log aktivitas BARU dibangun v1.0 (belum bagian
> F-97 asli v0.8), ditambahkan ke checklist yang sama supaya Boss tidak perlu buka
> file terpisah. **Item 8 (drag) PALING butuh mata manusia** — gerakan visual,
> redup kolom, revert optimistic tidak bisa dibuktikan lewat test otomatis sama sekali.
>
> **v1.2 H4 (item 11):** halaman Command Center BARU (`/dashboard/overview`) —
> heatmap navigasi bulan, 5 kartu ringkas, donut/bar/kategori, + dashboard 3-angka
> lama dipertahankan sebagai section "Beban Tim". Sangat visual, extension masih absen.
>
> **v1.2/v1.5 (item 12):** Leaderboard skor MANAGEMENT-ONLY BARU (`/leaderboard`,
> F-134) — permission `leaderboard.view` NOL pemegang default TERMASUK admin, WAJIB
> di-assign manual lewat UI Role Management yang sudah ada. **Item ini butuh 2 langkah
> manusia paling kritis**: (a) buktikan admin BIASA benar-benar 403 sebelum diberi izin,
> (b) buktikan permission itu muncul OTOMATIS di form Role tanpa Jarvis sentuh kode UI.
>
> **v1.2 H5 (item 13):** Eisenhower quadrant (F-122/F-126) + Checklist dalam-tugas
> + gate transisi →review (F-123/F-127/F-111) — BARU dibangun hari ini. Chrome
> extension masih absen sesi ini juga (percobaan connect gagal), jadi 3 fitur ini
> **belum pernah dilihat mata manusia sama sekali**, cuma lulus 23 test otomatis
> HTTP (MySQL). **Item ini paling kritis dari semua** — kalau gate-nya bocor,
> assignee bisa submit ke review tanpa menuntaskan syarat kerja.
>
> **Waktu:** ±15 menit. **Server:** `composer run dev` (kalau belum jalan).
> **Login:** `admin@deevatech.test` / `password` (admin) — `member1@deevatech.test`
> s/d `member9@deevatech.test` / `password` (member, ganti angka sesuai instruksi tiap item).

**Urutan PENTING** — item 1 (hari libur) mengubah data yang dipakai item 3 (counter).
Kerjakan **urut nomor 1→6**, dan **HAPUS holiday percobaan di langkah 1** sebelum
lanjut ke langkah 3, atau counter hari ini ikut ter-nol-kan.

---

## 1. Hari Libur mempengaruhi realisasi (F-43)

1. Login **admin**. Buka `/pengaturan/hari-libur`.
2. Perhatikan dulu di `/dashboard` (buka tab baru) — kolom **Aktif** untuk
   **Member 4** (task sedang berjalan, lihat langkah 3 di bawah untuk detail).
   Catat angkanya.
3. Kembali ke tab Hari Libur. Tambah libur baru dengan **tanggal HARI INI** dan
   nama bebas (mis. "Tes Verifikasi F-97").
4. Refresh tab Dashboard (`/dashboard`). Kolom **Aktif** Member 4 **HARUS turun**
   (idealnya ke 0 kalau seluruh segmen berjalan hari ini) — karena hari ini sekarang
   dihitung sebagai libur, bukan hari kerja.
5. 🔴 **WAJIB:** kembali ke `/pengaturan/hari-libur`, **HAPUS** holiday "Tes
   Verifikasi F-97" yang baru dibuat — kalau tidak, langkah 3 (counter tick) di
   bawah akan gagal karena hari ini masih tercatat libur.

**Hasil diharapkan:** angka Aktif berkurang setelah holiday ditambah, kembali normal
setelah holiday dihapus.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 2. Unassign member yang punya task aktif ditolak (F-87)

1. Login **admin**. Buka `/projects`, klik **Edit** salah satu project (mis.
   "Website Revamp" atau "Operasional Harian").
2. Di form edit, **hapus centang** salah satu member dari daftar member (pilih
   member yang kira-kira sedang mengerjakan task — kalau tidak yakin, coba
   Member 1-9 satu-satu, seeder menjamin minimal beberapa di antaranya punya
   task IN PROGRESS/REVIEW aktif).
3. Klik Simpan.

**Hasil diharapkan:** form **DITOLAK** (tidak tersimpan), muncul pesan error
merah di bawah field member: *"Member punya task sedang dikerjakan, tidak bisa
dihapus dari project: [nama]. Selesaikan atau pindahkan assignee dulu."*
Kalau member yang dicoba TIDAK punya task aktif, tidak ada penolakan (itu benar) —
coba member lain sampai menemukan satu yang menolak.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 3. Counter live menick di jam kerja (F-38/F-94)

**Prasyarat:** holiday percobaan di langkah 1 SUDAH dihapus. Jalankan ini di jam
kerja (Senin-Jumat 08:00-17:00 WIB) — di luar jam itu counter memang statis
(itu perilaku BENAR, F-57, bukan bug — lihat catatan di bawah).

1. Login **member4@deevatech.test**. Buka `/my-tasks`.
2. Cari task dengan badge **"Sedang dikerjakan"** (status IN PROGRESS, sudah
   ada dari seeder — segmen mulai berjalan sejak seeder di-run).
3. Klik masuk ke detail task-nya. Perhatikan angka counter di bagian atas.
4. **Tunggu/refresh setelah ±1 menit** — angka **HARUS bertambah** (menick).

**Hasil di luar jam kerja (opsional, sulit diuji real-time tanpa menunggu):**
Kalau dicoba di luar Senin-Jumat 08:00-17:00, angka counter **statis** (tidak
menambah walau ditunggu) — ini sudah teruji otomatis
(`tests/Unit/BusinessHoursCalculatorTest.php`, `tests/Feature/LiveTaskCounterTest.php`),
tidak wajib dibuktikan manual kalau Boss testing di luar jam kerja.

[ ] LULUS (menick di jam kerja) — dicoret Boss tanggal: ______

---

## 4. Dashboard — 3 angka + bar + tooltip (F-52/F-96)

1. Login **admin**. Buka `/dashboard`.
2. Untuk beberapa baris user, perhatikan: **Beban**, **Backlog**, **Aktif** tampil
   sebagai angka (menit/jam), plus **idle plan** dan **idle real** di bawahnya.
3. Arahkan mouse (hover) ke ikon **ⓘ** di kolom Beban — tooltip **HARUS muncul**:
   *"Beban dibagi rata antar assignee (F-96) — task 4 jam dengan 2 assignee
   menyumbang 2 jam ke masing-masing, bukan 4 jam penuh."*
4. Perhatikan bar/visual kapasitas per user (kalau ada elemen progress bar,
   pastikan proporsinya masuk akal — beban tinggi = bar lebih penuh).
5. **Multi-assignee:** cari task dengan 2+ assignee (mis. template "Laporan
   mingguan tim" di project Operasional Harian, assigned ke Member 2 & 3) —
   pastikan beban masing-masing HANYA separuh estimasi, bukan penuh dobel.

**Hasil diharapkan:** 3 angka (beban/backlog/aktif) + idle plan/real tampil per
user, tooltip F-96 muncul saat hover.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 5. Halaman Template render + form (F-46)

1. Login **admin**. Buka `/projects`, klik **Template** pada project
   "Operasional Harian" (3 template sudah ada dari seeder: Rekap absensi harian/
   daily, Laporan mingguan tim/weekly, Tutup buku bulanan/monthly).
2. Pastikan daftar 3 template tampil dengan info tipe (daily/weekly/monthly) +
   assignee default.
3. Klik **Buat Template** (atau tombol serupa) — form muncul, coba isi field
   dasar (title, tipe, estimasi) TANPA submit (sekadar pastikan form render,
   tidak wajib disimpan).
4. Klik **Edit** salah satu template yang ada — form edit muncul terisi data lama.

**Hasil diharapkan:** halaman index + form create + form edit semuanya render
tanpa blank page/error React.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 6. Attachment — upload, download, hapus, terkunci pasca-approve (F-49/F-104/F-105/F-107)

> ⚠️ **Catatan:** 2 attachment dari seeder (`laporan-hasil.pdf`,
> `bukti-scope-tambahan.png`) adalah **baris DB tanpa file fisik nyata** (dibuat
> langsung via `Attachment::create()` di seeder, bukan lewat upload asli) —
> **link download-nya akan GAGAL/404 kalau diklik**. Itu BUKAN bug, itu batasan
> data seeder. Gunakan **upload baru** (langkah 1) untuk menguji upload+download.

1. Login **member1@deevatech.test** (atau member manapun yang punya task
   assigned, belum DONE). Buka `/my-tasks`, masuk ke detail salah satu task.
2. Di bagian **Lampiran Output**, klik **Upload**, pilih file PDF/gambar kecil
   apa saja dari komputer. Submit.
3. File **HARUS muncul** di daftar lampiran (nama, ukuran, nama uploader, waktu).
4. Klik nama file untuk **download** — file **HARUS terunduh** dengan benar
   (bukan file dari seeder di langkah sebelumnya, tapi yang baru diupload).
5. Login **admin**, buka task yang sama. Tombol **Hapus** di sebelah lampiran
   **HARUS muncul** untuk admin (tidak muncul untuk member).
6. **Approve task** tersebut (kalau belum di status REVIEW, ubah status dulu ke
   REVIEW sebagai admin/assignee, lalu Approve dengan quality rating).
7. Kembali ke detail task yang SUDAH approved — area upload **HARUS
   hilang/disabled** dengan keterangan terkunci, dan tombol **Hapus** attachment
   (walau login admin) **HARUS TIDAK ADA/gagal** (F-107 — terkunci permanen
   pasca-approve, bahkan admin).

**Hasil diharapkan:** upload→muncul di daftar→download sukses→admin bisa hapus
SEBELUM approve→setelah approve, upload & hapus keduanya terkunci.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 7. Board View render + toggle List↔Board (v1.0 H1, F-109)

1. Login **admin** (atau member). Buka `/projects`, klik project mana saja, masuk
   ke List View (`/projects/{id}/tasks`).
2. Klik tombol **"Board View"** di pojok kanan atas — halaman papan Kanban muncul,
   kolom sesuai status project (TODO/IN PROGRESS/REVIEW/DONE untuk project seeder),
   tiap kolom menampilkan jumlah kartu di header.
3. Perhatikan kartu: judul, badge tipe/prioritas/poin, avatar assignee (inisial),
   dan — untuk task IN PROGRESS yang sedang ada segmen berjalan (lihat item 3 F-97
   di atas) — **titik hijau + angka counter** persis seperti di List View/My Tasks
   (komponen yang SAMA, bukan counter baru).
4. Cari task yang overdue (lihat List View filter "Terlambat" untuk referensi) —
   di Board, kartunya harus punya badge merah **"Terlambat"**.
5. Coba filter **Assignee** di sidebar kiri Board — kartu yang tampil harus
   menyusut ke assignee yang dicentang saja, dan URL berubah (bisa di-refresh
   tanpa kehilangan filter).
6. Klik tombol **"List View"** di Board untuk kembali — pastikan balik ke halaman
   List View project yang sama (bukan project lain).
7. Klik salah satu kartu — **HARUS** membuka halaman detail task yang SUDAH ADA
   (`/projects/{id}/tasks/{taskId}`), bukan modal/halaman baru.

**Hasil diharapkan:** Board render tanpa blank page, counter/badge terlihat identik
gaya dengan List View, filter jalan, toggle List↔Board dua arah jalan, klik kartu
buka halaman detail yang sudah dikenal.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 8. Drag-drop kartu Board (v1.0 H2, F-110/F-111) 🔴 PALING BUTUH MATA MANUSIA

> Login sebagai **member yang jadi assignee** salah satu task (cek dulu di List
> View siapa assignee-nya), supaya kartu itu bisa diseret (member cuma bisa
> menyeret task miliknya sendiri, F-95 — kalau login admin, semua kartu bisa diseret).

1. Buka Board (`/projects/{id}/board`). **Mulai seret** (tekan & tahan mouse,
   jangan cuma klik) salah satu kartu di kolom TODO.
2. **SAAT masih menyeret** (belum dilepas): perhatikan kolom lain —
   - Kolom **IN PROGRESS** (posisi TODO+1) harus tetap NORMAL (bisa nerima drop)
   - Kolom **REVIEW** dan **DONE** (posisi lebih jauh) harus **REDUP** (opacity
     turun) — dan kalau kartu diarahkan ke sana, kolom itu TIDAK menyala/highlight
     seperti target yang valid.
3. Lepas kartu di kolom **IN PROGRESS** (posisi sah, +1) — kartu harus **langsung
   pindah** ke kolom itu SAAT ITU JUGA (instan, sebelum halaman reload) — ini F-33
   optimistic UI.
4. Refresh halaman (F5) — pastikan posisi kartu di kolom IN PROGRESS **bertahan**
   (bukan cuma ilusi visual yang hilang saat refresh — berarti server benar-benar
   menyimpannya).
5. Buka halaman detail task itu (klik kartu) — pastikan **counter mulai jalan**
   (task sekarang IN PROGRESS = is_work_state, segmen kerja terbuka).
6. **Uji revert (opsional, agak teknis):** coba matikan koneksi internet sebentar
   lalu seret kartu — request akan gagal, kartu HARUS balik sendiri ke kolom asal
   + muncul pesan error kecil di bagian bawah layar. Kalau sulit disimulasikan,
   lewati langkah ini — sudah tercover test otomatis server-side.
7. Coba **klik biasa** (tanpa menyeret) pada kartu lain — harus tetap membuka
   halaman detail seperti biasa, TIDAK dianggap sebagai drag.
8. **(Opsional) Keyboard:** klik kartu untuk fokus, tekan Space/Enter lalu tombol
   panah — dnd-kit mendukung drag via keyboard bawaan, kolom tak-sah tetap tidak
   bisa dituju.

**Hasil diharapkan:** drag terasa mulus (bukan patah-patah), kolom tak-sah jelas
terlihat redup SEBELUM dilepas (bukan ditolak sesudah), drop tersimpan permanen,
klik biasa tidak terganggu drag.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 9. Komentar + @mention (v1.0 H3, F-113/F-114/F-115)

1. Login **member1@deevatech.test**. Buka task apa saja yang dia bisa lihat
   (project-nya sendiri), scroll ke bawah — kartu **"Komentar"** harus tampil di
   bawah layout utama (full width, bukan di sidebar sempit).
2. Ketik komentar biasa (tanpa @), klik **Kirim** — komentar muncul di daftar
   dengan nama kamu, waktu "baru saja".
3. Ketik komentar baru, ketik **`@`** lalu beberapa huruf nama member lain —
   dropdown kecil autocomplete harus muncul di bawah kotak teks. Klik salah satu
   nama — teks `@Nama` (bukan markup mentah `@[Nama](id)`) harus tersisip rapi di
   kotak teks. Kirim.
4. Login sebagai **member yang barusan disebut** — buka bell notifikasi (ikon
   lonceng di header) — harus ada notifikasi **"...menyebut kamu di komentar
   task..."**. Klik notifikasi itu — harus membuka halaman detail task yang benar.
5. Kembali ke **member1**, klik **Edit** pada komentar milik sendiri — ubah isi,
   Simpan — label **"(diedit)"** harus muncul di sebelah waktu komentar.
6. Klik **Hapus** pada komentar sendiri — konfirmasi — komentar berubah jadi teks
   abu-abu miring **"[Komentar dihapus]"** (BUKAN hilang total dari daftar).
7. Login sebagai member LAIN (bukan penulis) — buka task yang sama — pastikan
   tombol **Edit/Hapus TIDAK ADA** di komentar member1 (hanya ada di komentar
   milik sendiri, kalau dia punya).
8. (Opsional) Coba ketik `@` lalu nama member yang **BUKAN** anggota project ini
   (kalau ada user lain di organisasi) — dropdown autocomplete seharusnya tidak
   menampilkannya sama sekali (daftar cuma berisi member project ini).

**Hasil diharapkan:** komentar tersimpan & tampil rapi, autocomplete mention
berfungsi, notifikasi mention muncul di bell dan klik-nya membuka task yang benar,
edit/hapus cuma bisa oleh penulis, komentar terhapus jadi placeholder bukan hilang.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 10. Log Aktivitas — global + riwayat per-task (v1.0 H4, F-116/F-106)

1. Login **admin**. Cek sidebar kiri — menu **"Log Aktivitas"** harus muncul (ikon
   jam berputar). Klik — halaman daftar kejadian tampil, terbaru di atas.
2. Baca beberapa baris — HARUS berupa **kalimat Indonesia wajar** (mis. "Admin Boss
   mengubah status task \"...\" dari \"TODO\" ke \"IN PROGRESS\"") — **BUKAN**
   string mentah seperti `status_changed` atau `attachment_uploaded` di mana pun.
3. Coba filter **Pelaku** (pilih salah satu nama) — daftar menyusut ke kejadian
   orang itu saja. Coba filter **Tipe event** — begitu juga. Coba **Dari tanggal**/
   **Sampai tanggal** — daftar menyesuaikan. Refresh halaman (F5) — filter yang
   dipilih **HARUS bertahan** (ada di URL).
4. Pastikan **TIDAK ADA** tombol edit/hapus di halaman ini sama sekali (read-only).
5. Login sebagai **member** (mis. member1@deevatech.test) — menu "Log Aktivitas"
   **HARUS TIDAK ADA** di sidebar. Coba akses langsung `/pengaturan/activity-log`
   di address bar — harus kena halaman 403 (ditolak).
6. Masih sebagai member, buka salah satu task yang dia assignee-nya — scroll ke
   bawah, cari card **"Riwayat"** — harus ada beberapa baris kejadian task itu
   (mis. "... membuat task ...", "... mengubah status ..."), juga kalimat wajar,
   bukan string mentah. Ini TIDAK butuh permission khusus (beda dari log global).

**Hasil diharapkan:** log global cuma untuk admin (atau role yang diberi izin),
semua label manusiawi, filter+URL jalan, nol tombol mutasi, riwayat per-task
tampil untuk siapa pun yang boleh lihat task itu.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 11. Dashboard "Command Center" (v1.2 H4, F-52/F-109/F-118/F-121/F-128/F-131)

1. Login **admin**. Sidebar kiri — klik **"Dashboard"**. Harus mendarat di
   `/dashboard/overview` (URL baru, BUKAN `/dashboard` lama), judul halaman
   "Command Center".
2. **5 kartu ringkas** di atas: Beban Harian (format "X/Y jam"), To Do, In
   Progress, Review, Overdue. Bandingkan angka To Do/In Progress/Review dengan
   jumlah kartu di halaman Board/List project — HARUS masuk akal (bukan 0 semua
   kalau ada task, bukan angka aneh).
3. **Donut Prioritas** — lingkaran warna-warni (merah/amber/biru/abu) dengan
   angka di tengah = total task. Kalau semua task belum ditandai prioritas,
   donut abu-abu semua — itu benar (bukan bug).
4. **Distribusi Progress** — 4 bar (To Do/In Progress/Review/Selesai), panjang
   proporsional jumlah task.
5. **Kategori Tugas** — daftar task_type + jumlah.
6. 🔴 **Kalender Beban Tim** (heatmap) — grid satu bulan. Klik panah **◀ ▶** di
   pojok kanan atas kartu — bulan harus berganti (judul kartu ikut berubah),
   TANPA reload penuh browser terasa aneh (Inertia partial navigation, halaman
   sedikit redup sesaat lalu normal). **Tanggal-tanggal SEBELUM hari ini HARUS
   abu-abu netral** (bukan hijau/kuning/merah) walau ada beban tercatat di hari
   itu. Tanggal hari ini & seterusnya berwarna sesuai beban (hijau=aman,
   kuning=tengah, merah=overload) — hover kursor ke sel untuk lihat tooltip menit.
7. **Top-10 Task** — daftar task urut prioritas, task yang sudah **selesai**
   TIDAK BOLEH muncul di sini.
8. **Workload Top-5** — daftar user + beban, urut dari terbesar.
9. **Aktivitas Terbaru** — daftar kejadian, kalimat Indonesia wajar (pola sama
   item 10 di atas), BUKAN string mentah seperti `created`/`status_changed`.
10. Scroll ke bawah — card **"Beban Tim"** (tabel Aktif/Beban/Backlog/Anomali
    per user) HARUS ADA — ini dashboard 3-angka LAMA yang dipertahankan (F-121),
    bukan dihapus. Klik tombol **"Detail & filter →"** di pojok kartu ini —
    harus membawa ke halaman `/dashboard` LAMA (dengan filter tanggal/user).
    Pastikan halaman `/dashboard` lama itu masih berfungsi normal seperti
    sebelumnya (tidak rusak oleh perubahan hari ini).
11. Pastikan **TIDAK ADA** angka rupiah/Rp atau "skor" di seluruh halaman ini
    (F-4 — halaman ini cuma beban & aktivitas, bukan penilaian kinerja).
12. Login sebagai **member** (mis. member1@deevatech.test). Menu "Dashboard"
    **HARUS TIDAK ADA** di sidebar. Coba akses langsung `/dashboard/overview`
    di address bar — harus kena halaman 403.

**Hasil diharapkan:** semua widget tampil dari data asli (bukan kosong/error),
heatmap navigasi bulan jalan dengan hari-lewat netral, Beban Tim lama utuh &
bisa diakses detailnya, member tetap 403, nol rupiah/skor di mana pun.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 12. Leaderboard — skor MANAGEMENT-ONLY (v1.2/v1.5, F-134/F-135/F-2/F-4/F-62/F-47)

1. Login **admin biasa** (admin@deevatech.test, BELUM diberi permission apa pun
   tambahan). Menu **"Leaderboard"** **HARUS TIDAK ADA** di sidebar (F-134 — admin
   TIDAK otomatis dapat, beda dari menu lain). Coba akses langsung `/leaderboard`
   di address bar — harus kena halaman **403**.
2. Buka **"Kelola Role"** (dari menu User) → edit role **admin** (atau buat role
   baru) → cari grup permission **"leaderboard"** di form — harus ada checkbox
   **`leaderboard.view`** otomatis muncul di situ (F-135, TANPA saya ubah kode
   form sama sekali). Centang, simpan.
3. Refresh halaman manapun (atau login ulang) — menu **"Leaderboard"** sekarang
   **HARUS MUNCUL** di sidebar. Klik.
4. Tabel ranking tampil: Rank (🥇🥈🥉 untuk 3 teratas) · Nama · Point · Rating ·
   Revisi · Ditolak · On-time%. Urutan **HARUS** dari Point terbesar ke terkecil.
5. Baris 3 terakhir (Bottom-3) harus ada badge **"Perlu dibantu"** — pastikan ini
   BUKAN nada menghukum/malu-maluin (cek teksnya netral, F-134 manajemen bukan
   papan malu).
6. User yang **belum punya task disetujui** di periode ini harus tetap muncul di
   daftar dengan Point **0** dan kolom Rating/On-time% **"-"** (bukan "0" atau error).
7. Coba filter: klik **"Hari ini"**, **"Minggu ini"**, **"Bulan ini"** — tabel
   harus berubah, dan tanggal di URL (`?from=...&to=...`) ikut berubah. Ubah manual
   input tanggal "Dari"/"Sampai" — tabel ikut menyesuaikan. Refresh (F5) — filter
   **HARUS bertahan** (ada di URL).
8. Pastikan catatan kecil **"Skor provisional — kalibrasi final v1.5"** tampil di
   bawah tabel — ini PENTING, jangan sampai hilang (Boss/management tidak boleh
   salah anggap ini angka final).
9. Pastikan **TIDAK ADA** angka rupiah/Rp/gaji di halaman ini sama sekali (F-4 —
   ini skor ranking, bukan nominal uang).
10. Login sebagai **member** (mis. member1@deevatech.test) — menu "Leaderboard"
    **HARUS TIDAK ADA**. Coba akses langsung `/leaderboard` — harus 403.
11. (Opsional, kalau mau lebih yakin) Coba approve satu task dengan quality rating
    rendah (mis. 2) dan tandai revisi dengan reject dulu sebelum approve — cek di
    Leaderboard, task itu tetap MENYUMBANG Point penuh (poin task, bukan dikurangi
    rating rendahnya) — cuma kolom Rating/Revisi yang mencerminkan itu (F-62).

**Hasil diharapkan:** admin biasa 403, permission muncul otomatis di form Role yang
sudah ada, setelah diberi izin baru bisa lihat, ranking benar & urut, user nol-task
tetap tampil, filter+URL jalan, catatan provisional ada, nol rupiah, member tetap 403.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 13. Eisenhower quadrant + Checklist + Gate →review (v1.2 H5, F-122/F-123/F-126/F-127/F-111)

1. Login **admin**. Buka project mana saja → **Task Baru**. Field **"Prioritas"**
   lama (Low/Normal/High/Urgent) **HARUS TIDAK ADA** di form — yang tampil cuma
   **"Prioritas (Eisenhower)"** dengan opsi "Belum diklasifikasi" + 4 kuadran
   berlabel (mis. "#1 Penting – Mendesak"). Pilih salah satu, isi field wajib
   lainnya, Simpan.
2. Di halaman List/Board/Detail task yang baru dibuat — badge kuadran berwarna
   HARUS tampil (merah=P1, amber=P2, biru=P3, abu=P4) menggantikan tampilan
   lama "Low/Normal/High/Urgent" (enum lama itu TIDAK BOLEH tampak di mana pun).
3. Buat 1 task lagi TANPA memilih kuadran (biarkan "Belum diklasifikasi") — pastikan
   TIDAK ada badge kuadran yang muncul (bukan badge "P4" — nullable, bukan dipaksa
   rendah).
4. Buka halaman **Detail** task manapun — scroll ke card **"Checklist"**. Tambah
   2-3 item lewat kotak "Tambah item checklist...". Item harus muncul di daftar,
   progress di judul card berubah jadi "(0/3)" dsb.
5. **Centang** satu item — progress harus naik (mis. "(1/3)"), teks item jadi
   coret (strikethrough).
6. Ubah status task ke **Review** (dropdown status di card "Status") SELAGI
   masih ada item checklist yang BELUM dicentang — transisi **HARUS DITOLAK**,
   muncul pesan error *"Centang semua checklist dulu sebelum submit ke review
   (F-127)."* Task TETAP di status semula.
7. Centang SEMUA item checklist sisanya, coba ubah status ke Review lagi —
   kali ini **HARUS LOLOS**, task pindah ke Review.
8. Buat 1 task baru TANPA checklist sama sekali, ubah statusnya sampai ke Review
   — **HARUS LOLOS** (checklist kosong = lolos, F-127, bukan setiap task wajib
   punya item).
9. Di **Board View** project yang sama: seret kartu task yang PUNYA checklist
   belum tuntas dari kolom kerja ke kolom **Review** — drop **HARUS DITOLAK**
   (kartu balik ke kolom asal + pesan error di bawah layar) — gate yang SAMA
   berlaku di drag, bukan cuma dropdown (F-111).
10. Login sebagai **member** yang jadi assignee salah satu task di atas — buka
    detail task itu — dia **HARUS BISA** mencentang item checklist DAN menambah
    item baru sendiri, tapi tombol **Edit/Hapus** pada item **HARUS TIDAK ADA**
    untuknya (cuma admin/task.manage yang punya tombol itu).

**Hasil diharapkan:** enum priority lama hilang dari UI, badge kuadran benar &
nullable, checklist bisa dikelola sesuai peran (admin: tambah/edit/hapus+centang;
assignee: tambah+centang saja), gate menolak transisi review di DUA jalur
(dropdown & drag) saat ada item belum tuntas, dan LOLOS saat semua tuntas atau
checklist kosong.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 14. Lapisan Visual TEMPO — token + sidebar bergrup (v1.2 DS-1, F-140/F-143/F-144)

> Murni VISUAL + navigasi. Nol perubahan data/logika/route/permission (dibuktikan
> grep — lihat laporan Fase C). Item ini eksis SEMATA karena F-75: perubahan CSS/
> React tidak bisa dibuktikan lewat test HTTP, harus dilihat mata.

1. Login **admin** (`admin@deevatech.test`). Sidebar kiri **HARUS navy gelap**
   (bukan putih/abu seperti sebelumnya), workspace di kanan **tetap terang**.
2. Grup sidebar admin harus tampil urut: **Ringkasan** (Dashboard, +Leaderboard
   kalau admin ini sudah di-assign `leaderboard.view`) → **Kerja** (Proyek, Tugas
   Saya, Semua Tugas, Tugas Berulang, Perpanjangan, Perpanjangan Saya) →
   **Organisasi** (Pengguna & Peran, Jam Kerja, Hari Libur, Log Activity, Setelan).
3. Item **"Semua Tugas"**, **"Tugas Berulang"**, **"Setelan"** harus tampil **redup
   dan TIDAK BISA DIKLIK** dengan label kecil "SEGERA" di kanan — ini disengaja
   (route-nya belum dibangun, F-140), BUKAN bug.
4. Logo di pojok kiri atas sidebar: ikon lama + teks **"TEMPO"** (placeholder,
   BUKAN lagi "Laravel Starter Kit").
5. Login **member** (mis. `member1@deevatech.test`). Sidebar HARUS cuma 1 grup:
   **"Kerja Saya"** (Tugas Saya, Proyek Saya, Perpanjangan Saya) — TIDAK ADA grup
   Ringkasan/Organisasi sama sekali, dan sidebar tetap navy juga untuk member.
6. Buka beberapa halaman (Dashboard, Proyek, detail Task) — cek tombol (Button),
   badge, dan card di WORKSPACE (bukan sidebar) berwarna **amber** untuk aksi
   utama (dulu hitam/abu-abu) — ini efek token `--primary` baru.
7. Buka **Pengaturan → Pengguna & Peran** — pastikan fungsinya (tambah/edit user,
   Kelola Role) **berjalan seperti biasa**, cuma warnanya ikut palet baru (F-135 —
   struktur/fungsi RBAC tidak boleh berubah, cuma re-theme).
8. Cek toggle tema gelap/terang yang sudah ada (kalau ada tombolnya di halaman
   Settings/Appearance) — workspace boleh ikut gelap/terang seperti biasa, TAPI
   **sidebar harus tetap navy** di kedua mode (sidebar TEMPO tidak ikut toggle ini).
9. Notifikasi bell (ikon lonceng) — kalau ada notifikasi belum dibaca, badge
   angka merah kecil di pojok ikon lonceng harus tetap terlihat jelas (warnanya
   sekarang ikut token `destructive`, seharusnya masih terlihat seperti merah).

**Hasil diharapkan:** sidebar navy konsisten di semua halaman & kedua mode tema,
grup+gating sesuai role, 3 item placeholder redup-nonaktif, logo "TEMPO", RBAC
existing tidak berubah fungsi.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 15. Command Center — filter per-widget + widget "Status Project" (v1.2 DS-4b, F-109/F-131/F-148/§12.5)

> Chrome extension **masih absen** sesi ini juga (percobaan connect gagal lagi) —
> filter per-widget (backend+frontend) dan widget "Status Project" BARU
> (`statusProjects()`, tabel COUNTS) belum pernah dilihat mata manusia sama
> sekali, cuma lulus 28 test otomatis HTTP (MySQL) + `tsc`/build/lint bersih.

1. Login **admin**. Buka `/dashboard/overview`.
2. Kartu **Donut Prioritas**: klik selector periode ("Hari ini/Minggu ini/Bulan
   ini") dan/atau dropdown user — angka donut **HARUS berubah** menyempit sesuai
   filter, tanpa me-reset kartu lain di sekitarnya.
3. Ulangi untuk kartu **Distribusi Progress**, **Kategori Tugas**, **Top-10
   Task**, **Aktivitas Terbaru** — masing-masing filter independen (klik satu
   TIDAK boleh mereset yang lain).
4. Tombol global **"Last Week" / "Last Month" / "Pilih Tanggal"** di bagian atas
   — klik salah satu, **kelima** kartu di atas harus ikut menyempit bersamaan
   (heatmap & Workload Top-5 SENGAJA TIDAK ikut, itu benar bukan bug).
5. Kartu **Heatmap**: pakai dropdown user-only-nya, filter ke SATU user — grid
   warna harus berubah (skala threshold ikut turun karena cuma 1 user), hari
   yang SUDAH LEWAT tetap abu-abu netral (bukan berwarna) apa pun filternya.
6. Kartu **Workload Top-5**: ganti tanggal anchor-nya sendiri (input date di
   kartu itu) — **HARUS TIDAK** mengubah tabel "Beban Tim" di bagian paling
   bawah halaman (itu section terpisah, read-only, tanggalnya sendiri).
7. **Widget baru "Status Project"** (tabel proyek) — cek: kolom Task/Todo/
   Progress/Selesai/Overdue/Deadline muncul, klik header kolom mana pun untuk
   sort naik/turun (tanda panah ↑/↓ muncul di header aktif), tombol "Show
   More →" mengarah ke halaman Proyek (`/projects`).
8. Proyek yang sudah **diarsip** TIDAK BOLEH muncul di tabel Status Project.

**Hasil diharapkan:** semua filter menyempit data sesuai kartunya masing-masing
tanpa saling mereset, heatmap hari-lewat tetap netral, Beban Tim tak ikut geser,
widget Status Project tampil rapi + sortable + kecualikan proyek arsip.

[ ] LULUS — dicoret Boss tanggal: ______

---

## 16. "Semua Tugas" + "Tugas Berulang" — nav diaktifkan (v1.2 H7b, F-140/F-144/F-147/F-139)

> Chrome extension **masih absen** sesi ini juga (percobaan connect gagal lagi) —
> 2 halaman BARU ini (flat lintas-project, sebelumnya `disabled` di sidebar sejak
> item 14/DS-1) cuma lulus 11 test otomatis HTTP (MySQL) + `tsc`/build/pint bersih,
> **belum pernah dilihat mata manusia**. Ini SUPERSEDE item 14 langkah 3 — 2 dari
> 3 item placeholder di situ ("Semua Tugas", "Tugas Berulang") **TIDAK LAGI**
> redup/disabled, sekarang aktif & mengarah ke halaman sungguhan. "Setelan" TETAP
> disabled (DS-2/DS-3, di luar scope sesi ini).

1. Login **admin**. Sidebar kiri, grup **Kerja** — klik **"Semua Tugas"**. Harus
   mendarat di `/tasks` (bukan lagi redup/"SEGERA"), tabel task dari **LEBIH DARI
   1 project sekaligus** tampil di satu halaman (bandingkan kolom "Project" di
   tabel — harus ada nama project yang berbeda-beda pada baris berbeda, kalau
   organisasi Boss punya >1 project aktif).
2. Coba filter **Project** (dropdown) — pilih 1 project. Tabel harus menyempit ke
   task project itu saja, DAN tombol **"Board View"** yang tadinya tidak ada/redup
   sekarang **muncul**. Klik tombol itu — harus membuka halaman Board (Kanban)
   project itu, TAMPILANNYA SAMA PERSIS dengan Board yang sudah ada sebelumnya
   (kolom TODO/IN PROGRESS/REVIEW/DONE, drag-drop, dst — F-109, nol kode board baru).
3. Kembali ke `/tasks`, kosongkan filter Project ("Semua Project") — tombol
   **"Board View"** harus **hilang lagi**, diganti keterangan kecil "Pilih 1
   project untuk lihat Board View" (Kanban memang sengaja tidak tersedia lintas
   banyak project sekaligus — tiap project bisa punya susunan status berbeda).
4. Coba filter **Status** (checkbox Belum dikerjakan/Dikerjakan/Review/Selesai) —
   pastikan hasil filter masuk akal biarpun dicoba lintas project yang punya nama
   status berbeda-beda (filter ini pakai kategori umum, bukan nama status project
   tertentu).
5. Coba filter **Prioritas (Eisenhower)** — badge warna P1-P4 di kolom Prioritas
   tabel harus konsisten dengan badge yang sama di halaman List/Board project biasa.
6. Coba ubah **Urutkan** ke "Prioritas (Quadrant)" — urutan baris harus mengikuti
   P1 di atas → P4 di bawah (atau sebaliknya kalau diklik Turun/Naik).
7. Klik **"Ubah status..."** pada salah satu baris (kolom Aksi) — pastikan
   dropdown-nya berisi status MILIK PROJECT TASK ITU (bukan status project lain),
   dan perubahan tersimpan (refresh, status baru bertahan).
8. Klik judul salah satu task — harus membuka halaman detail task yang sudah
   dikenal (`/projects/{id}/tasks/{taskId}`).
9. Login sebagai **member** (mis. `member1@deevatech.test`) — menu **"Semua Tugas"**
   **HARUS TIDAK ADA** di sidebar (member cuma lihat "Tugas Saya"). Coba akses
   langsung `/tasks` di address bar — harus kena **403**.
10. Kembali login **admin**. Sidebar grup **Kerja** — klik **"Tugas Berulang"**.
    Harus mendarat di `/task-templates`, daftar TEMPLATE dari **beberapa project**
    tampil sekaligus (kolom Project berbeda-beda per baris).
11. Pilih project dari dropdown di pojok kanan atas, klik **"Template Baru"** —
    harus membuka form create template project itu (halaman yang SUDAH ADA
    sebelumnya, bukan form baru).
12. Klik **Edit** pada salah satu baris — harus membuka form edit template yang
    sudah ada, terisi data lama dengan benar (title/jadwal/estimasi cocok dengan
    yang tampil di tabel listing).
13. Klik **Aktifkan/Nonaktifkan** pada salah satu baris — badge status harus
    berubah di tempat (tanpa pindah halaman), refresh — perubahan bertahan.
14. Login sebagai **member** — menu **"Tugas Berulang"** **HARUS TIDAK ADA**.
    Akses langsung `/task-templates` — harus **403**.

**Hasil diharapkan:** kedua halaman render tanpa blank page/error React, listing
benar-benar lintas-project, Board View cuma muncul saat 1 project dipilih dan
sama persis dengan Board lama, filter status pakai kategori umum bukan nama status
mentah, member 403 di keduanya, CRUD template tetap lewat halaman project-scoped
lama (nol endpoint baru tersentuh).

[ ] LULUS — dicoret Boss tanggal: ______

---

## SETELAH SEMUA DICORET

Kabari Jarvis "F-97 tutup, X/16 lulus" + status tiap item (atau sebutkan
item mana yang gagal) supaya status finding **F-97** di `docs/04-FINDING-REGISTRY.md`
bisa diupdate dari 🟡 TERBUKA ke 🟢. Jarvis tidak akan mengubah dokumen ini sendiri
tanpa konfirmasi.
