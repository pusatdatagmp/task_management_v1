# 04 — FINDING REGISTRY

> **Jangkar permanen (Mandatory #6).** Nomor F-N **TIDAK PERNAH** diubah, didaur ulang, atau dihapus.
> Finding yang tidak berlaku lagi ditandai **SUPERSEDED**, bukan dihilangkan.
> **Finding baru mulai dari F-65.**
> Terakhir diaudit: 2026-07-15

---

## STATUS

| Kode | Arti |
|------|------|
| 🟢 **AKTIF** | Aturan berlaku, wajib ditegakkan |
| 🔵 **SELESAI** | Sudah diputuskan Boss, tertanam di spec |
| 🟡 **TERBUKA** | **Menunggu keputusan Boss** |
| ⚪ **SUPERSEDED** | Digantikan finding lain. Nomor tetap dipesan |

---

## REGISTRY F-1 … F-64

| ID | Isi | Status | Lokasi |
|----|-----|:------:|--------|
| **F-1** | Branding hanya tercapai bila app cepat & stabil | 🟢 | PRD §1 |
| **F-2** | Scoring ditunda v1.5 — kalibrasi dari data nyata | 🟢 | PRD §2 |
| **F-3** | Risiko legal potongan gaji — clearance HR/legal sebelum v2.0 | 🟡 | PRD §2 |
| **F-4** | Goodhart's Law — skor jadi uang = perilaku menyimpang | 🟢 | PRD §2 |
| **F-5** | `organization_id` di semua tabel bisnis sejak hari-1 | 🟢 | DM §1 |
| **F-6** | Notifikasi = database, Firebase ditunda v3.0 | 🔵 | PRD §3 |
| **F-7** | Search = MySQL FULLTEXT, bukan Elasticsearch | 🔵 | PRD §3 |
| **F-8** | Hierarki 4 level, bukan 8 level ClickUp | 🔵 | PRD §3 |
| **F-9** | **Activity log = bahan mentah scoring, bukan sekadar audit** | ⚪ → **F-51** | — |
| **F-10** | "Jadi" = jalan di localhost ≠ dipakai produksi | 🟢 | PRD §7 |
| **F-11** | Nama produk & branding belum ditentukan | 🟡 | PRD §11 |
| **F-12** | Hosting belum ditentukan | 🟡 | PRD §11 |
| **F-13** | Strategi backup DB belum ditentukan | 🟡 | PRD §11 |
| **F-14** | **Soft delete vs hard delete** — diputuskan: soft delete | ⚪ → **F-16** | — |
| **F-15** | Global scope organization wajib di semua model | 🟢 | DM §1 |
| **F-16** | Soft delete `users`/`projects`/`tasks`. Hard delete DILARANG | 🟢 | DM §1 |
| **F-17** | Konvensi penamaan Laravel — jangan kreatif | 🟢 | DM §1 |
| **F-18** | **`employment_type` ditanam v0.5 meski dipakai v3.0** — kolom enum murah, migrasi data mahal | 🟢 | DM §3.4 |
| **F-19** | `is_completed` — penanda selesai independen dari nama status | 🟢 | DM §3.7 |
| **F-20** | Subtask maks 1 level. Nested = ditolak | 🟢 | DM §3.9 |
| **F-21** | `completed_at` diisi otomatis oleh observer | 🟢 | DM §3.9 |
| **F-22** | Activity log via **Observer**, bukan panggilan manual | 🟢 | CLAUDE §1 |
| **F-23** | `activity_logs` IMMUTABLE selamanya | 🟢 | DM §3.14 |
| **F-24** | FULLTEXT butuh raw `DB::statement`, migration terpisah | 🟢 | DM §6 |
| **F-25** | Konflik terjadwal v1: `tasks.project_id` → `list_id` | 🟢 | DM §7 |
| **F-26** | Seeder bukan opsional — tanpa data realistis, bug baru ketemu saat demo | 🟢 | DM §8 |
| **F-27** | **Business flow awal = asumsi Jarvis, wajib dikoreksi Boss** | 🔵 **selesai** — Boss sudah koreksi F-28..F-37 | — |
| **F-28** | Reviewer = **Admin/owner project** | 🔵 | PRD §4 |
| **F-29** | Member **TIDAK** boleh buat task / geser due_date | 🔵 | BF §6 |
| **F-30** | Description = **rich text** | 🔵 | DM §3.9 |
| **F-31** | `due_date` **WAJIB** | 🔵 | DM §3.9 |
| **F-32** | Transisi status **BERURUTAN** | 🔵 → impl **F-45** | PRD §4 |
| **F-33** | **Optimistic UI wajib untuk drag-drop** — tanpa itu drag terasa lag (lihat F-1) | 🟢 **v1.0** | Board View |
| **F-34** | Search wajib difilter permission — bug keamanan paling umum | 🟢 | BF §10 |
| **F-35** | Notifikasi **WAJIB** — 10 trigger | 🔵 | BF §9 |
| **F-36** | Pelaku tidak dapat notifikasi atas aksinya sendiri | 🟢 | BF §9 |
| **F-37** | **6 metrik KPI, semua dibutuhkan.** RAW vs DERIVED | 🔵 | DM §4 |
| **F-38** | **Counter = calculated, BUKAN stateful.** Nol scheduler | 🟢 | CLAUDE §1 |
| **F-39** | **FREEZE `actual_minutes` + `rejection_count` saat approve** | 🟢 | CLAUDE §1 |
| **F-40** | `work_schedules` **versioned** — insert, bukan update | 🟢 | DM §3.2 |
| **F-41** | Realisasi = Σ segmen (`task_time_segments`) | 🟢 | DM §3.10 |
| **F-42** | `daily_capacity_minutes` boleh ≠ panjang jendela kerja | 🟢 | DM §3.2 |
| **F-43** | Holidays ditunda v0.8 — bisa dihitung mundur | 🔵 | DM §3.3 |
| **F-44** | **JANGAN hardcode nama status.** Pakai 3 flag | 🟢 | DM §3.7 |
| **F-45** | Transisi: maju +1, mundur bebas. Validasi di service layer | 🟢 | DM §3.7 |
| **F-46** | Recurring = template → instance | 🟢 | DM §3.8 |
| **F-47** | `original_due_date` — tanpa ini metrik on-time bohong | 🟢 | DM §3.9 |
| **F-48** | Maks 1 segmen terbuka per task | 🟢 | DM §3.10 |
| **F-49** | Attachment 2 jenis: `output` & `evidence` | 🟢 | DM §3.12 |
| **F-50** | Alur extension: ajukan → evidence → approve admin | 🟢 | BF §4 |
| **F-51** | **Log tidak boleh bolong 1 event pun** — sumber 4 dari 6 metrik KPI | 🟢 | CLAUDE §1 |
| **F-52** | **Dua idle: PLANNED & REAL.** Satu angka bohong, tiga angka jujur | 🟢 | BF §5 |
| **F-53** | Flag anomali (realisasi > 3× estimasi) — **bukan penalti otomatis** | 🟢 | DM §5 |
| **F-54** | Circular FK `work_schedules.created_by` → tambah belakangan | 🟢 | DM §6 |
| **F-55** | **Koreksi prioritas: time budget = core, Board View = pelengkap** | 🟢 | PRD §1 |
| **F-56** | Kenapa bukan 3 hari — recurring engine = subsistem | 🟢 | PRD §2 |
| **F-57** | **Cap jendela kerja** — Jumat 16:00 → Senin 09:00 = 2 jam, bukan 65 | 🟢 | PRD §6 |
| **F-58** | Formula scoring: bobot points/on-time/quality/rejection | 🟡 **v1.5** | PRD §11 |
| **F-59** | Task besar > kapasitas harian — **DIPUTUSKAN: opsi B, dashboard SEBAR beban ke hari kerja sampai tenggat** (tidak wajib pecah subtask). Detail mekanik → F-118. Implementasi sebelum deploy | 🔵 **selesai** v1.0.1 | BF §12 |
| **F-60** | Instance recurring lama tidak dihapus — jadi overdue | 🟢 | BF §3 |
| **F-61** | `last_generated_date` = idempotency guard | 🟢 | BF §3 |
| **F-62** | Jumlah extension per user = metrik KPI v1.5 | 🟢 | BF §4 |
| **F-63** | **Multi-assignee: realisasi & poin dibagi atau digandakan?** | 🟡 **sebelum v0.8** | BF §12 |
| **F-64** | Finding baru mulai dari F-169 | 🟢 | CLAUDE §2 |
| **F-65** | **Laravel 12 bug-fix berakhir ±Agu 2026** — keputusan Boss tetap L12. Upgrade ke L13 wajib dijadwalkan | 🟡 **AKTIF** | Tutorial §0 |
| **F-66** | **Segmen menyeberang perubahan `work_schedules`** → v0.5 pakai config aktif saat `started_at`. Resolusi per-hari → v0.8 | 🟢 | H2 §C2 |
| **F-67** | **Guard FULLTEXT harus EXCLUDE sqlite, bukan INCLUDE mysql** — driver `mariadb` bikin search mati diam-diam | 🟢 | H2 §B1 |
| **F-68** | **`due_date` default +7 hari = UI pre-fill, BUKAN `Task::booted()`** — auto-fill menyembunyikan kesalahan input admin | 🟢 | H2 §B2 |
| **F-69** | **TZ = `Asia/Jakarta` di DB, bukan UTC.** Naive comparison jadi benar. Migrasi ke UTC deterministik (+7j, Indonesia tanpa DST) bila v3.0 butuh multi-zona | 🟢 | H3 §A |
| **F-70** | **`effective_from` DILARANG backdate** — mengubah jendela kerja masa lalu = menulis ulang realisasi task yang belum di-approve | 🟢 | H3 §B |
| **F-71** | **`project_user` belum punya observer** — sync member tidak tercatat. Lubang audit trail | 🔵 **selesai** H3 | — |
| **F-72** | **Trait `SerializesDatesInAppTimezone` wajib di SEMUA model** — `Model::serializeDate()` hardcode UTC, mengabaikan `APP_TIMEZONE` | 🟢 | H4 §0 |
| **F-73** | **`/login` crash sejak Hari-1** — `route('register')` dipanggil tanpa guard padahal route dicabut. Ziggy throw → blank page | 🔵 **selesai** H4 | — |
| **F-74** | **"Tepat 1 dari sekian" = RADIO, bukan checkbox** — struktur UI harus mencerminkan struktur constraint | 🔵 **selesai** H5 | — |
| **F-75** | **DoD fitur UI WAJIB bukti BROWSER NYATA** — Feature test HTTP melewati render React. `/login` mati 3 hari sambil test hijau | 🟢 | CLAUDE §5 |
| **F-76** | **Nested route WAJIB `->scopeBindings()`** — pagar manual per-controller pasti ada yang lupa | 🔵 **selesai** H5 | — |
| **F-77** | Status rich text — **TERJAWAB: editor terpasang, F-30 terpenuhi** | 🔵 **selesai** | — |
| **F-78** | **Menambal ≠ memperbarui test.** Dilarang ubah test agar LULUS; wajib perbarui bila perilaku sengaja diubah instruksi (cakupan setara + lapor) | 🟢 | CLAUDE §5 |
| **F-79** | **Rich text merusak FULLTEXT** — HTML terindeks (`strong`, `href`, `https`). Butuh kolom `description_plain` | 🟢 | H6 §B |
| **F-80** | **Notifikasi scheduler WAJIB guard idempotency** — cron 2× = notif ganda = inbox banjir = orang berhenti membaca | 🟢 | H6 §C |
| **F-81** | **Klarifikasi F-38:** larangan scheduler itu soal COUNTER. Cron harian untuk notifikasi & recurring **SAH** | 🟢 | CLAUDE §1 |
| **F-82** | **Halaman detail task WAJIB** — member tidak bisa membaca `description` (tidak ada di kolom List View, dan F-29 melarang member edit). Rich text dibayar tapi tak terbaca pekerja | 🟢 | H7 §A |
| **F-83** | **Test WAJIB jalan di engine DB produksi.** sqlite→LIKE, MySQL→MATCH AGAINST: 6 test search tidak pernah menyentuh FULLTEXT. Pola F-73 terulang | 🟢 | CLAUDE §5 |
| **F-84** | **Trigger #3 skip bila transisi sudah ditangkap #6/#7/#8** — 2 notif untuk 1 aksi menabrak F-36. Spec 10-trigger Jarvis tidak memeriksa tumpang tindih | 🟢 | H7 §B |
| **F-85** | **`Model::preventLazyLoading()` di non-produksi** — N+1 ketahuan saat ditulis, bukan saat tim mengeluh lambat | 🟢 | H7 §D |
| **F-86** | **Seeder dev melanggar invarian** — assignee di-`attach` tanpa jadi `project_user`, padahal `StoreTaskRequest` mewajibkannya. State mustahil dibuat lewat UI | 🟢 | RBAC §F |
| **F-87** | **Belum ada guard: hapus member project yang punya task aktif** — segmen tetap terbuka, terus menghitung, `actual_minutes` tak pernah beku. Pola F-19 | 🔵 **selesai** HARDEN | — |
| **F-88** | **RBAC penuh: `roles`+`permissions`+pivot.** `enum admin/member` PENSIUN → jadi role sistem (`is_system`). SATU sumber kebenaran izin — bukan enum + RBAC berdampingan (pola F-72/F-76) | 🟢 | RBAC |
| **F-89** | **1 role per user** (`users.role_id`). Blueprint. Bila v3.0 butuh multi-role → retrofit ke pivot (diketahui, dicatat) | 🟢 | RBAC §B |
| **F-90** | **Cek izin berbasis PERMISSION, bukan role.** `can('task.create')` bukan `isAdmin()`. Matriks Hari-3 (F-29 dll) diterjemahkan jadi permission konkret di SETIAP controller | 🟢 | RBAC §D |
| **F-91** | **Onboarding = ADMIN-ONLY, bukan self-signup.** Blueprint "buat login user" TIDAK menghidupkan registrasi | 🔵 **selesai** RBAC | — |
| **F-92** | **Password hash SINKRON** via cast bawaan Laravel. Blueprint "asinkron" keliru teknis, tidak diterapkan | 🔵 **selesai** RBAC | — |
| **F-93** | **Pematangan business-hours (F-66,F-43) WAJIB pra-deploy** — F-39 membekukan `actual_minutes`; matang setelah deploy = data KPI dua rumus permanen. Pola F-57/F-69, kali ini satu arah bila telat | 🟢 | HARDEN |
| **F-94** | **Counter live WAJIB konsisten dengan angka freeze** — tampilan live harus terapkan cap jendela kerja (F-57). Jangan duplikasi kalkulator di JS (pola F-72/F-76): backend beri akumulasi + status in-window, frontend cuma menick | 🟢 | v0.8 H1 |
| **F-95** | **Aksi member dijaga assignee/membership, BUKAN permission RBAC.** Member = nol permission. Gating counter (v0.8 H1), attachment (H5) berbasis "task saya / project saya", jangan bikin permission baru untuk aksi member | 🟢 | v0.8 |
| **F-96** | **Beban multi-assignee = estimated_minutes ÷ jumlah assignee** (F-63a). Realisasi tetap per-user dari segmen. Poin UTUH tiap assignee, tidak dibagi (F-63b) | 🟢 | v0.8 H2 |
| **F-97** | **3 item belum terverifikasi browser** (2 HARDEN + counter menick) — Chrome extension absen 3 sesi. Bukan blocker (terbukti 3 lapis test) tapi pola F-73. Bawa di LANGKAH 0 sampai ada mata manusia | 🟡 **TERBUKA** | tiap prompt |
| **F-98** | **Realisasi diatribusikan ke hari segmen DITUTUP, tidak dipecah per-hari** — segmen lintas-hari masuk penuh ke hari-tutup. Distorsi IDLE_REAL harian utk segmen lintas-hari. AMAN: segmen mentah tersimpan → v1.5 bisa hitung ulang presisi | 🟢 | v0.8 H2 |
| **F-99** | tsc regresi 0→3 — **DIREKONSILIASI H3:** `tsconfig` exclude `*.test.ts` (file node --test bukan untuk tsc), turun ke 0 tanpa dependency | 🔵 **selesai** | — |
| **F-100** | **Recurring: HARI INI SAJA, tidak backfill.** Scheduler telat/terlewat → hanya generate untuk hari berjalan, tidak rekonstruksi hari terlewat. Data KPI tak tercemar obligasi retroaktif | 🟢 | v0.8 H4 |
| **F-101** | **Monthly tanggal > jumlah hari bulan → clamp ke hari terakhir bulan.** 31 Jan template → 28/29 Feb. Urutan: clamp DULU, baru shift libur (F-102) | 🟢 | v0.8 H4 |
| **F-102** | **Recurring di libur/akhir pekan: weekly/monthly GESER maju ke hari kerja; daily SKIP.** Daily-shift memaksa backfill (langgar F-100); daily skip tetap menumpuk via carryover F-60. Weekly/monthly shift maju = forward-computable, tak langgar F-100 | 🟢 | v0.8 H4 |
| **F-103** | **Recurring resolusi tanggal cek 2 periode (kini + sebelumnya), bukan 1** — occurrence tergeser lintas batas bulan/minggu (28 Feb→2 Mar) takkan ketemu kalau cuma cek periode berjalan. Tetap hormati F-100 (generate hanya bila efektif==hari ini). Temuan Claude Code H4 | 🟢 | v0.8 H4 |
| **F-104** | **Attachment output BEKU saat task approved (DONE)** — sejalan F-39 (actual_minutes beku). Bebas selama siklus review. Jejak kerja tak bisa dimanipulasi pasca-approve (dasar scoring v1.5/payroll v2.0) | 🟢 | v0.8 H5 |
| **F-105** | **Hanya ADMIN hapus attachment; member APPEND-ONLY.** "Bebas selama review" = bebas MENAMBAH revisi, BUKAN menghapus. Riwayat submit tersimpan penuh (audit). Member tak bisa bereskan salah-upload sendiri | 🟢 | v0.8 H5 |
| **F-106** | Event log baru `recurring_assignee_dropped` (H4) — di luar 12 event wajib DM §3.14, sah (varchar bebas). Katalog event activity_log kini +1; v1.5 yang baca log harus tahu event ini ada | 🟢 | v0.8 |
| **F-107** | **Attachment task approved TERKUNCI dari hapus — bahkan admin.** F-104 kunci upload; ini kunci hapus. Bukti kerja = catatan sejarah dasar quality_rating & payroll v2.0, tak boleh lenyap siapa pun. Salah-upload → arsip/tandai, bukan hapus. Pola F-39 | 🟢 | v0.8 H6 |
| **F-108** | **Perpanjangan: `requested_due_date` ≥ `due_date` saat ini** (tolak mundur, izinkan sama = nambah budget tanpa geser tenggat). **Evidence OPSIONAL, reason WAJIB.** Pola penyalahgunaan terlihat dari metrik F-62, bukan dari memaksa lampiran | 🟢 | v0.8 H6 |
| **F-109** | **Board View = TAMPILAN, bukan jalur data baru.** Reuse komponen v0.8 (counter F-94, status badge, kalkulator business-hours). NOL hitung ulang di board. Perubahan status (H2) lewat service+observer yang sama (F-45/F-41/F-51), drag cuma UI | 🟢 | v1.0 H1 |
| **F-110** | **Drag-drop board pakai aturan C: kolom tak-sah DI-DISABLE saat menyeret** (bukan tolak-setelah-drop). User lihat batasan F-45 SEBELUM melepas. F-45 utuh, nol aturan-per-peran. Library: dnd-kit (aksesibel) | 🟢 | v1.0 H2 |
| **F-111** | **Drop = perubahan status lewat SERVICE+OBSERVER yang sama** (F-45/F-41/F-51). Drag hanya UI. Optimistic (F-33): kartu pindah instan, server konfirmasi; gagal → revert mulus. JANGAN bypass observer (segmen/log bolong) | 🟢 | v1.0 H2 |
| **F-112** | **Segmen dibuka atas nama ASSIGNEE-pekerja, bukan pelaku transisi** (`resolveSegmentWorker`). Pelaku=assignee→pelaku; pelaku≠assignee & tunggal→assignee; 0/2+→nol segmen. Berlaku SEMUA jalur (dropdown+drag). Cegah admin tercatat "kerja" saat merapikan papan. Ke-4 near-miss KPI (F-57/69/93) | 🟢 | v1.0 H2 |
| **F-113** | **Komentar = tabel `comments` TERPISAH, bukan `activity_log`.** Log = audit murni (F-51, 4/6 metrik KPI); mencampur komentar mencemari sumber KPI | 🟢 | v1.0 H3 |
| **F-114** | **Mention @user = notifikasi kategori KOLABORASI** — bukan melanggar 10 trigger lifecycle (F-35). Trigger lifecycle & kolaborasi kelas berbeda | 🟢 | v1.0 H3 |
| **F-115** | **Komentar: penulis edit/hapus SENDIRI via SOFT-DELETE** — ditandai terhapus, catatan tetap (audit). Diskusi butuh koreksi, jejak tak hilang total | 🟢 | v1.0 H3 |
| **F-116** | **Activity log GLOBAL di-gate permission `activity.view`** (admin default, assignable ke peran lain — INSERT baris, F-90). Timeline per-task tetap membership-based (F-95). Log = data pengawasan lintas-proyek | 🟢 | v1.0 H4 |
| **F-117** | **Event `deleted` tak simpan identitas objek** (observer `properties=null` sejak awal) → label jatuh ke `#id`. Perbaikan = observer snapshot nama SEBELUM hapus (hook `deleting`). Utang v1.1 — WAJIB tutup sebelum payroll v2.0 (jejak "admin hapus apa" = akuntabilitas) | 🟡 **TERBUKA** | v1.1 |
| **F-118** | **SEBAR beban (F-59 opsi B):** kontribusi harian = (estimasi ÷ assignee) ÷ jumlah HARI KERJA today→tenggat (lewati weekend/libur F-43). ESTIMASI PENUH (bukan sisa; progres tampil terpisah via counter F-94, beban tetap metrik perencanaan murni). Overdue → semua hari ini. "Beban hari ini" kini = porsi hari ini dari kerja berjalan, bukan yang jatuh tempo hari ini. IDLE_PLAN lebih jujur | 🟢 | v1.0.1 |
| **F-119** | **`DashboardService::aktif()` = dead code pra-eksisting** (publik, tak pernah dipanggil; forUsers hitung aktif inline). Ditemukan v1.0.1, TIDAK disentuh (di luar scope). Bersihkan v1.1 — risiko: seseorang panggil method mati kira sumber | 🟡 **TERBUKA** | v1.1 |
| **F-120** | **Integrasi mockup v1.7: tombol Start/Stop Timer = KONTROL WORK-STATE, BUKAN timer manual.** Menekan Start → pindah status kerja → segmen F-41 buka. Realisasi TETAP dihitung dari segmen (F-38), bukan tombol. F-94 terjaga. Timer manual DITOLAK (gameable, langgar integritas) | 🟢 | v1.2 |
| **F-121** | **Mockup v1.7 = REFERENSI PENGAYAAN VISUAL, bukan pengganti frontend harfiah.** ADD-DON'T-DELETE berlaku ke FRONTEND juga. Fitur sudah jadi yang ABSEN/di-stub di v1.7 (activity log UI, komentar/mention, UI RBAC, jam kerja, libur, template, perpanjangan) DIPERTAHANKAN utuh. Login role-picker demo TIDAK ganti auth asli (F-91) | 🟢 | v1.2 |
| **F-122** | **Prioritas Eisenhower (p1-p4: Penting×Mendesak) = FIELD BARU** di samping `enum(low/normal/high/urgent)` lama (aditif, tidak menimpa). Bobot p1=4..p4=1 untuk sorting dashboard. UI pakai Eisenhower | 🟢 | v1.2 |
| **F-123** | **Checklist dalam-tugas (BEDA dari subtask F-20)** = tabel ringan baru (text+is_done). Gate baru: transisi →REVIEW ditolak bila checklist belum tuntas. Ditegakkan SERVER-side via service (F-111 spirit), bukan cuma client. Aditif ke F-45 | 🟢 | v1.2 |
| **F-124** | **Fitur MEETINGS** (tabel `meetings` + pivot `meeting_user`): admin buat, invite peserta, project opsional (FK NULL-able), start_at/end_at. Notif "diundang meeting" = **kategori KOLABORASI (keluarga F-114), BUKAN trigger lifecycle ke-11.** F-35 "10 lifecycle" TETAP utuh. F-36 dihormati (pembuat tak dapat notif sendiri) | 🟢 | v1.2 |
| **F-125** | **`projects.status` DITURUNKAN dari agregasi task** (todo/aktif/selesai), bukan kolom tersimpan. `is_archived` TETAP terpisah (aksi manual admin). Aturan derivasi didefinisikan H3 | 🟢 | v1.2 |
| **F-126** | **`priority_quadrant` p1-p4 menggerakkan UI**; enum priority lama (low/normal/high/urgent) DIPERTAHANKAN tapi disembunyikan (legacy, tak dihapus — F-121). Bobot p1=4..p4=1 utk sort | 🟢 | v1.2 |
| **F-127** | **Gate checklist ditegakkan SERVER-side di SEMUA jalur transisi** (F-111/F-123); template salin item ke instance (sentuh recurring engine, jaga F-100/101/102/F-61). RESOLVED (mockup v1.7 `moveTaskNext`: allDone = checklist kosong ATAU semua done → **kosong LOLOS**): "wajib" = item yang ADA wajib dicentang sebelum review, BUKAN setiap task wajib punya item | 🔵 **RESOLVED: gate-only** | v1.2 H5 |
| **F-128** | **Threshold heatmap kalender:** Aman <210m · Tengah 210-419m · Overload ≥420m per user aktif (×N utk agregat semua-user). Pakai beban F-118, NOL rumus baru | 🟢 | v1.2 |
| **F-129** | 🔵 **DIPERBAIKI (v1.2 H3 Fase 0)** — seeder pakai `withoutGlobalScope(OrganizationScope)` HANYA pada query eksplisit ber-organization_id; scope global tak disentuh; isolasi F-5/F-15 utuh (test regresi+idempotency). ~~Bug laten: `Role::firstOrCreate()` × OrganizationScope.~~ Role pakai BelongsToOrganization → scope AND-kan `org=Auth::org` ke SELECT firstOrCreate. Saat seed role utk org B sambil actingAs org A → SELECT tak pernah match → INSERT ganda → duplicate-key crash. LATEN (alur single-org DEEVATECH belum memicu; muncul di test multi-org & saat multi-org/marketplace v3.0). Diperbaiki di TEST saja (urut buat user sebelum actingAs); FIX KODE-APP perlu (withoutGlobalScope saat seed per-org) SEBELUM fitur multi-org | 🔵 **selesai** | v1.2 H2 |
| **F-130** | 🔵 **DIPERBAIKI (v1.2 H3 Fase 0)** — `travelTo(next Wednesday 10:00)`, deterministik lintas-hari (lulus akhir pekan). ~~Test flaky: `BoardViewTest` "live counter identik"~~ pakai `now()` tanpa `travelTo()`; WorkSchedule fixture Sen–Jum → 0 menit di Sabtu/Minggu → assertion gagal tiap akhir pekan, lolos hari kerja. Langgar disiplin travelTo (pelajaran H2 v0.5). "Semua hijau" jadi bersyarat-hari (pola F-73). Fix: pin ke hari kerja via travelTo | 🔵 **selesai** | v1.2 H2 |
| **F-131** | **Heatmap kalender = grid BULAN, navigasi prev/next.** Hari LEWAT = NETRAL (abu-abu), BUKAN realisasi — beban maju-saja (F-118, semangat F-100). Mencampur realisasi ke hari-lewat DILARANG (nodai batas beban-vs-realisasi F-94). Warna pakai threshold F-128, beban dari workloadSpread F-118 (satu sumber, F-109). "Rencana vs aktual" = widget lain kalau perlu | 🟢 | v1.2 H3 |
| **F-132** | **MODEL WAKTU KERJA = tombol Mulai/Hold/Lanjut/Submit (sisi user).** Mulai→segmen buka+status dikerjakan+tombol Submit muncul · Hold→segmen tutup (jeda)+jadi Lanjut · Lanjut→segmen buka+jadi Hold · Submit→**CEK GATE F-127** (ada checklist belum dicentang→Submit GAGAL; lengkap/kosong→lolos)→segmen tutup+JUMLAH semua segmen=realisasi+status review. Realisasi DIHITUNG dari segmen (F-38/F-41), bukan stopwatch manual. Start/Stop timer di HTML = salah desain, DIBUANG. Menggantikan/menyempurnakan F-120 | 🟢 | v1.2 |
| **F-133** | **`projects.status` diturunkan otomatis di sisi USER** (F-125). Admin/super-admin/top-management yang DIBERI AKSES tetap melihat SELURUH task (termasuk selesai, tak diturunkan) untuk analisa capaian tiap user | 🟢 | v1.2 |
| **F-134** | **LEADERBOARD: strategi poin→SKOR, MANAGEMENT-ONLY** (bukan member-visible). Point = Σ pts task disetujui/periode (Level 1). Kolom konteks Rating/Revisi/Ditolak/On-time% = tampilan, TIDAK dibaur punitif (hormati F-62). Bottom-3 utk analisa manajemen, BUKAN papan malu member. Exception SADAR ke F-4; PROVISIONAL sampai v1.5 kalibrasi data nyata (F-2). Member tak lihat → tak bisa game → data payroll tetap jujur | 🟢 | v1.2/v1.5 |
| **F-135** | **RBAC (User & Peran) yang SUDAH dibangun Claude Code = BENAR & SUMBER KEBENARAN — JANGAN diubah** (F-121). User list (Nama/Email/Role/Tipe Internal/Status/Aksi) + Role list (Sistem tak-terhapus) + form Role Baru dengan permission per-modul (activity/dashboard/project/status/task/user + workschedule). Form "Role Baru" di HTML v1.7 KURANG lengkap → DIABAIKAN. **Akses leaderboard = tambah permission `leaderboard.view` ke katalog (INSERT baris, F-90), diberikan ke user/peran terpilih lewat UI RBAC yang ADA.** Bukan tier top-management hardcoded. Email admin@deevatech.test terkonfirmasi | 🟢 | v1.2 |
| **F-136** | **Admin ≠ semua permission lagi.** Flag `default_admin=false` di katalog → admin di-seed semua permission KECUALI yang berflag itu (opt-in). `leaderboard.view` yang pertama (F-134). Konsisten F-90 (permission-driven, NOL isAdmin-bypass — admin biasa 403 di leaderboard, terbukti C1). `syncWithoutDetaching` jaga grant manual tak tercabut re-seed | 🟢 | v1.2 H5 |
| **F-137** | 🔵 **DEDUP SELESAI** (ditemukan sudah ada di working tree DS-4, kini bertest). command-center pakai lib/priority-quadrant.ts, satu sumber warna Eisenhower | 🔵 **selesai** | v1.2 |
| **F-138** | **Keputusan model waktu (H7, menyempurnakan F-41):** (a) segmen terbuka HANYA lewat Mulai/Lanjut eksplisit — TIDAK lagi otomatis saat masuk status dikerjakan (F-41 lama diubah, test terkait update jujur F-78); (b) "JEDA" = kondisi TURUNAN (dikerjakan + nol segmen terbuka), bukan field baru; (c) drag ke kolom dikerjakan = STATUS saja, nol segmen (mover tak mulai jam assignee, semangat F-112); (d) task DITOLAK (review→dikerjakan) = JEDA, assignee klik Lanjut; (e) F-57 tetap cap jam kerja (lupa Hold tak meledakkan realisasi); (f) jeda = counter abu-abu. Realisasi TETAP Σ segmen (F-38/F-94 utuh) | 🟢 | v1.2 H7 |
| **F-139** | **Filter/sort prioritas List/Board MIGRASI legacy enum → priority_quadrant** (deviasi #1 H6; task baru tak punya nilai enum → filter enum makin tak berguna). Dikerjakan di H7b bersama Kanban v1.7 visual | 🟢 | v1.2 H7b |
| **F-140** | **CELAH BLUEPRINT diakui:** blueprint v1.7 menangkap FUNGSI/DATA tapi under-specify LAPISAN VISUAL (branding, design token/tema, struktur sidebar, filter per-widget) + LUPA widget "Status Project". Prompt H4 malah "jangan salin tema mockup" → menjauh dari TEMPO. Akar mismatch dashboard-harapan. KOREKSI: blueprint diperkaya §12 (lapisan visual) + fondasi design dulu. Bukan Claude Code salah — prompt Jarvis kurang | 🟢 | v1.2 |
| **F-141** | 🟢 **PROTEKSI: sebagian "beda dari mockup" JUSTRU BENAR — JANGAN diperbaiki saat pass visual:** (a) heatmap hari-lewat netral & kosong di hari tanpa task (F-131+data nyata); (b) donut "Belum ditandai" (quadrant NULL default, data nyata); (c) Leaderboard tak muncul utk admin (F-134 — belum di-assign leaderboard.view; benar). Membuatnya "seperti mockup" = melanggar finding | 🟢 | v1.2 |
| **F-142** | **Custom BRANDING = fitur Settings (org-scoped F-5, configurable):** upload logo, nama perusahaan, alamat, wa.me, sosmed (FB/IG/LinkedIn). Ganti branding hardcode "Laravel Starter Kit". Logo=file storage (pola F-104/105). Permission-gated. Tampil di sidebar/shell | 🟢 | v1.2 DS-2 |
| **F-143** | **Custom TEMA = fitur Settings (configurable):** override design token (warna komponen) + GRADASI, per-org. Diterapkan via CSS variable. WAJIB fondasi token (DS-1) dulu. Live preview kalau bisa | 🟢 | v1.2 DS-3 |
| **F-144** | **FONDASI DESIGN SYSTEM DULU** sebelum lanjut fitur task-mgmt (Boss): token TEMPO (ink navy/amber/emerald/rose + workspace terang) sebagai default overridable, sidebar bergrup (RINGKASAN/KERJA/ORGANISASI) role-appropriate + gated, komponen bersama adopsi token. Re-theme visual RBAC BOLEH (F-135 lindungi fungsi bukan tema) | 🟢 | v1.2 DS-1 |
| **F-145** | 🔵 **`--primary` = AMBER, DITERIMA Boss** (verifikasi browser DS-1: "sudah sesuai"). Tombol default app amber = pilihan sadar Boss (beda dari mockup btn-pri=ink, tapi Boss suka). Tak dikoreksi | 🔵 **selesai** | v1.2 DS |
| **F-146** | 🟡 **Bug laten PRE-EXISTING `ui/sidebar.tsx` ~404:** `hsl(var(--sidebar-border))` nesting hsl() ganda (invalid sebelum sesi ini) → shadow rail tak render. Tak disentuh DS-1 (di luar scope). Fix saat sentuh sidebar.tsx | 🟡 **TERBUKA** | v1.2 debt |
| **F-147** | 🔵 **SELESAI — 3 nav disabled semua AKTIF** (Semua Tugas+Tugas Berulang 5f245fe, Setelan c972b04). Nol dead-end tersisa | 🔵 **selesai** | v1.2 |
| **F-148** | 🔵 **DIPERBAIKI (DS-4b)** — 7 param `*_user_id` di-cast ke int di backend, kontrak API jujur | 🔵 **selesai** | v1.2 |
| **F-149** | 🔴 **PROTOKOL COMMIT WAJIB** tiap sesi selesai. **KLARIFIKASI (AE-2):** commit di-SCOPE ke file sesi itu sendiri (`git add <file>`), BUKAN `git add -A` mentah — kalau working tree punya perubahan TAK TERKAIT (mis. UI Boss), `git add -A` melanggar "1 commit=1 perubahan logis". Kerja uncommitted = rapuh. Registry hanya klaim "ada" untuk yang SUDAH di-commit | 🟢 | v1.2 |
| **F-150** | 🔵 **BERSIH (DS-2)** — lint error DS-1 (app-logo/NavFooter) hilang saat file ditulis ulang untuk branding. Lint 0 error/warning | 🔵 **selesai** | v1.2 |
| **F-151** | **AUTOMATION ENGINE evolusi: Dynamic Event & Condition-Driven** (revisi recurring). Cron 00:01 WIB → fetch template Active → Time-Delta `DateDiff(now_WIB, last_generated) >= interval` → **Anchor Strategy** (A=time-based / B=completion-based) → Holiday Shift → generate → mutasi state (task + last_generated). Menyempurnakan F-100/101/102/104/61 | 🟢 | v1.3 AE |
| **F-152** | **Miss-run = CATCH-UP SATU** (self-heal via delta+last_generated), MENGGANTI F-100 no-backfill. Beberapa periode terlewat → generate 1 (periode kini), set last_generated=today. Idempotent (F-61): cron 2× tak generate ganda (kunci template_id+periode). Command bisa manual (sweep) | 🟢 | v1.3 AE |
| **F-153** | **Holiday/weekend = Forward-Shift SEMUA tipe TERMASUK harian** (Boss) — MENGUBAH F-102 (dulu harian=skip). Geser ke hari kerja berikutnya (reuse F-43). Efek pile-up (Opsi A harian) dimitigasi last_generated tracking + Opsi B completion-gate | 🟢 | v1.3 AE |
| **F-154** | **Anchor Opsi B (completion-based): task sebelum belum SELESAI → SKIP** (cegah backlog). Deadlock (sebelumnya tak pernah selesai) → **NOTIFIKASI ADMIN** (kategori kolaborasi F-114, bukan lifecycle trigger), tidak silent, tidak paksa. Hindari spam: notif sekali/ambang, bukan tiap run | 🟢 | v1.3 AE |
| **F-155** | **Subtask create/kelola = HANYA `task.manage`** (admin/pembuat); MEMBER hanya update progress lewat CHECKLIST (18134f9, direkonsiliasi dari laporan sistem). Konsisten F-95/F-127 | 🟢 | v1.2 |
| **F-156** | **Dependency `framer-motion` + `sweetalert2` DISETUJUI Boss** (retroaktif, jejak approval CLAUDE.md §4). framer-motion=micro-interaksi; sweetalert2=alert (lib/swal.ts). Sah dipakai | 🟢 | v1.2 |
| **F-157** | **RULE: user tanpa `user.manage` TAK bisa ganti email sendiri** (ditegakkan SERVER, diratifikasi Boss). Keamanan identitas. Field profil lain (nama, password) tetap boleh | 🟢 | v1.2 |
| **F-158** | **Automation Engine ARSITEKTUR EXTENSIBLE: pipa TRIGGER -> CONDITION(Guard chain) -> RESOLVER -> ACTION.** Anchor = Strategy pattern (TimeBased/CompletionBased, key-registered). Condition = Guard komposabel berurutan (TimeDelta, AnchorStrategy, +future). Tiap evaluasi -> objek Decision (action/reason/target_date). Config data-driven (F-90 filosofi). Pipeline trigger-agnostic -> siap future EventTrigger. Tambah kondisi=tambah Guard, tambah anchor=tambah Strategy, TANPA rewrite engine | 🟢 | v1.3 AE |
| **F-159** | **§7 automation FINAL:** (1) period_key = TANGGAL periode terjadwal (readable + kunci idempotency dgn template_id); (2) notif block = SEKALI saat blocked_since di-set, clear saat selesai (anti-spam); (3) automation_run_log = TABEL DB (queryable -> future UI riwayat automation); (4) migrasi template lama -> anchor_strategy=time_based, interval diturunkan dari recurrence lama | 🟢 | v1.3 AE |
| **F-160** | **Automation Engine BUILD APPROACH (Boss):** (1) bangun set guard/strategy LEBIH LENGKAP sejak awal (bukan minimal); (2) GANTI TOTAL engine recurring lama (cutover bersih + migrasi template + test menyeluruh, hanya setelah AE teruji penuh); (3) isolasi kegagalan PER-TEMPLATE (try/catch, log, lanjut yang lain). Set guard/strategy konkret menunggu konfirmasi Boss | 🟢 | v1.3 AE |
| **F-161** | **Automation Engine SCOPE KONKRET (Boss):** Guards = TimeDelta+AnchorStrategy+ActiveTemplate (inti) + **DateWindow** (batasi hari/tanggal/hari-dalam-bulan) + **Quota** (maks N belum-selesai). Strategies = TimeBased(A)+CompletionBased(B)+**CalendarAnchored(C, hari tetap mis. tgl 1)** — 3 dibangun. Triggers = Cron+Manual (EventTrigger = INTERFACE slot saja, tak dibangun — belum ada use-case). Resolver = HolidayShift | 🟢 | v1.3 AE |
| **F-162** | 🔵 **DITUTUP (AE-3 cutover 5176563)** — `tasks:generate-recurring` dicabut dari scheduler; `automation:run` satu-satunya generator (verified schedule:list + Schedule::events introspeksi). Command lama utuh @deprecated (rollback). Nol double-gen | 🔵 **selesai** | v1.3 AE |
| **F-163** | 🔵 **DRIFT anchor hari-tetap pasca miss-run:** template weekly/monthly dimigrasi ke `time_based` (interval bergulir dari last_generated). Setelah miss-run, catch-up set last_generated=hari-eval → anchor hari-tetap (mis. tiap Senin/tgl-1) BERGESER. Akar: hari-tetap seharusnya `calendar_anchored`, bukan time_based. DB dev kosong (belum menggigit). SELESAI (AE-2b 0fefe75) — migrasi korektif idempotent, weekly/monthly→calendar_anchored (bukti angka), daily tetap, pilihan manual Boss tak tertimpa | 🔵 **selesai** | v1.3 AE |
| **F-164** | 🔵 **CalendarAnchored day_of_month>28 = SKIP bulan pendek** (E4, mis. tgl 31 di Feb → skip total), BUKAN clamp akhir-bulan (F-101 lama). Untuk semantik "akhir bulan"/"tgl 31", perlu opsi CLAMP. SELESAI (AE-2b) — CalendarAnchored clamp akhir-bulan (tgl31 Feb→28, bukti [28]); E4 diperbarui F-78 | 🔵 **selesai** | v1.3 AE |
| **F-165** | 🟡 **workload_top5 dihitung backend (F-96/F-118) tapi TIDAK dirender frontend** command-center.tsx — beda dari Beban Tim (F-52) yang tampil. Screenshot lama menunjukkan widget "Workload Top-5" PERNAH tampil → kemungkinan REGRESI (drop saat rework UI). Data backend tetap dihitung. KEPUTUSAN Boss: re-add widget (fix regresi, rekomendasi) atau hapus komputasi (redundan) | 🟡 **TERBUKA** | v1.2 |
| **F-166** | **SISTEM KPI — pluggable + config + master toggle** (pola F-158). `KpiScoringStrategy` interface, didaftar per-key. Sekarang: `SimpleTimelinessStrategy` (ontime=5/telat=3/notdone=0, config org-level default + admin override di Setelan). Nanti: WeightedStrategy dll = tambah class+daftar. Saklar: `kpi_strategy` (pilih logika) + `kpi_enabled` (master on/off). Consumer baca field `kpi_score` — ganti strategy/disable NOL dampak. Ganti versi = ganti setting, bukan kode | 🟢 | v1.4 KPI |
| **F-167** | **KPI guardrail:** skor DIBEKUKAN saat approve (F-39, ubah poin tak retroaktif) · ontime vs telat pakai `original_due_date` (F-47, perpanjangan tak palsukan ontime) · management-only (F-134) · provisional (F-2, kalibrasi v1.5 = tukar strategy) · NOL rupiah (F-4) | 🟢 | v1.4 KPI |
| **F-168** | **`kpi_score` = KOLOM TERPISAH di leaderboard, BUKAN ganti Σpts** (DIREVISI Boss setelah laporan KPI ungkap pemisahan F-62 disengaja). **Σpts TETAP Point** (throughput). kpi_score = indikator ketepatan-waktu (Σ per-task 5/3), tampil sejajar on-time%/rating/rejection sbg konteks — F-62 DIPERTAHANKAN PENUH (timeliness transparan, tak dibaked ke Point). `points`+`quality_rating` tetap (WeightedStrategy nanti). notdone=0 = konteks (F-62), versi ketat nanti | 🟢 | v1.4 KPI |

---

## 🟡 TERBUKA — BUTUH KEPUTUSAN BOSS

| ID | Pertanyaan | Deadline |
|----|-----------|----------|
| **F-59** | Task 40 jam vs kapasitas 8 jam/hari — wajib dipecah subtask? Berdampak ke rumus BEBAN | sebelum v0.8 |
| **F-63** | **PUTUS:** realisasi per-user otomatis (segmen). **Beban/estimasi DIBAGI RATA** antar assignee (kapasitas jujur). **Poin UTUH** tiap orang (lawan Goodhart, dorong kolaborasi) | 🔵 **selesai** | v0.8 H2 |
| **F-58** | Formula scoring | v1.5 — dari data |
| **F-3** | Clearance legal potongan gaji | sebelum v2.0 |
| **F-11/12/13** | Nama produk · hosting · backup | sebelum deploy |
| **F-65** | **Jadwal upgrade Laravel 12 → 13.** Bug-fix L12 habis ±Agu 2026 | sebelum produksi |

**Tidak ada yang memblokir Hari-1.** Semua bisa diputuskan sambil jalan.

---

## CATATAN AUDIT

**2026-07-15 — Pelanggaran Mandatory #6 terdeteksi & diperbaiki.**
Saat rombak revisi-2, lima finding (**F-9, F-14, F-18, F-27, F-33**) hilang dari dokumen. Nomor finding adalah jangkar permanen — tidak boleh menguap saat refactor.
**Perbaikan:** kelimanya dipulihkan di registry ini. F-9 dan F-14 ditandai SUPERSEDED (nomor tetap dipesan, tidak akan didaur ulang). F-18, F-27, F-33 dikembalikan sebagai finding aktif/selesai.
**Pencegahan:** registry ini menjadi indeks tunggal. Audit gap nomor sebelum setiap rombak dokumen.

---

## CATATAN AUDIT — 2026-07-15 (2)

**F-65 — Laravel 12 vs 13.**
Jarvis menemukan Laravel 13 rilis 17 Maret 2026 (setelah knowledge cutoff Jarvis). Laravel 12 (rilis Feb 2025) kehilangan dukungan bug-fix ±Agustus 2026 — sebulan dari sekarang.

**Rekomendasi Jarvis:** Laravel 13 + PHP 8.4.
**Keputusan Boss:** **TETAP Laravel 12.** Dihormati (Mandatory #7 — Boss yang memutuskan).

**Konsekuensi yang wajib disadari:**
- Bug baru di framework **tidak akan diperbaiki** setelah ±Agu 2026; hanya patch keamanan sampai ±Feb 2027
- Setelah itu, aplikasi berjalan di framework **tanpa dukungan apa pun**
- Boss merencanakan pemakaian 100+ bulan — upgrade ke L13 **tidak bisa dihindari**, hanya bisa ditunda

**Mitigasi:** jadwalkan upgrade L12 → L13 **sebelum produksi** atau segera setelah v1.0. Upgrade dari 12 ke 13 disebut salah satu yang termulus dalam sejarah Laravel, dan proyek ini greenfield — biaya sekarang jauh lebih murah daripada nanti setelah tim memakai sistem dan data KPI terkumpul.

**Jebakan teknis:** perintah `laravel new` sekarang meng-install **Laravel 13**. Untuk Laravel 12 **wajib** pakai `composer create-project laravel/laravel:^12.0`. Kalau tidak, Boss dapat L13 tanpa sadar — bertentangan dengan spec.

---

## CATATAN AUDIT — 2026-07-16 (Hari-1)

**Hasil audit Hari-1: LULUS.** 11 dari 11 angka DoD sesuai. `ActivityLog::count() = 81` membuktikan observer (F-22) hidup. Grep nama status → nol match (F-44 bersih). Nol scheduler (F-38 dipatuhi). FULLTEXT terpasang.

**9 deviasi dilaporkan Claude Code secara jujur di §9 audit** — termasuk yang tidak akan ketahuan kalau dia diam. Verdict Jarvis:

| Deviasi | Verdict |
|---|---|
| `TaskUser` pivot + surrogate `id` | **TERIMA** — perlu untuk event assigned/unassigned |
| `#[ObservedBy]` attribute (bukan Service Provider) | **TERIMA** — setara secara fungsi |
| Fix `Auth::check()` → `Auth::hasUser()` | **TERIMA** — recursion di global scope jebakan klasik; fix-nya benar |
| `ProjectObserver` ditambah sendiri | **TERIMA** — sejalan F-51 |
| 3 test auth dihapus | **TERIMA** — route-nya memang dicabut |
| 4 file tanpa header klasifikasi | **PERBAIKI** → H2 §B3 |
| Guard FULLTEXT `=== 'mysql'` | **PERBAIKI** → F-67 |
| `due_date` auto-fill di `booted()` | **PERBAIKI** → F-68 |
| MariaDB 10.4, bukan MySQL 8 | **PERBAIKI** → migrasi H2 §A |

### 🔴 KESALAHAN JARVIS — KONTRADIKSI F-39 vs F-57

Jarvis menyatakan ke Boss bahwa business-hours calculator masuk v0.5 (~4 jam), Boss menyetujuinya, **lalu Jarvis menulis v0.8 di PRD**. Claude Code mengikuti dokumen — dia benar, dokumennya yang salah.

**Akibatnya:** `calculateRawActualMinutes()` menjumlah durasi mentah tanpa cap jendela kerja, lalu **F-39 membekukannya permanen**. Task yang di-approve sebelum F-57 ada akan memakai angka ngawur (65 jam untuk kerja 2 jam) — dan **F-39 melarang penghitungan ulang**. Data KPI akan berisi dua rumus yang tidak sebanding, selamanya.

**Terdeteksi saat data masih seeder (3 task dummy) — nol kerugian nyata.**
**Keputusan Boss:** F-57 dibangun di **Hari-2**, sebelum aplikasi menyentuh data nyata.
**Konsekuensi:** timeline v0.5 bergeser 5 → **6 hari**.

**Pelajaran:** keputusan yang disepakati di percakapan **wajib langsung disinkronkan ke dokumen**. Dokumen adalah ground truth; percakapan menguap.

### KEPUTUSAN DATABASE

MariaDB 10.4.32 (XAMPP) **EOL 18 Juni 2024** — dua tahun tanpa patch keamanan.
Dampak: seluruh kolom `json` → `longtext`, termasuk `activity_logs.properties` (sumber 4 dari 6 metrik KPI).
**Keputusan Boss: MySQL 8**, dieksekusi di Hari-2 §A. Biaya sekarang ≈ nol (data cuma seeder); biaya nanti = migrasi engine dengan data KPI nyata di dalamnya.

---

## CATATAN AUDIT — 2026-07-17 (Hari-2)

**Hasil Hari-2: LULUS.** MySQL 8.0.46, json native, FULLTEXT terpasang, 31 test lulus.
**Test kunci `Jumat 16:00 → Senin 09:00 = 120 menit` PASS** — Masalah A (durasi status ≠ durasi kerja) selesai.
`id=29 actual=300` dengan segmen menyeberang weekend = bukti F-57 bekerja pada data, bukan hanya pada test.

**3 deviasi — semua DITERIMA:**
- `WorkSchedule::active(?Carbon $asOf)` → plumbing wajib F-66, backward-compatible
- Test #12 di level kalkulator (bukan DB) → sah untuk `tests/Unit`. **Catatan: akumulasi multi-segmen belum diuji end-to-end → Hari-4**
- Bug seeder `subDays(5)` jatuh di Minggu → `actual = 0`. Ditemukan & diperbaiki sendiri. **Seeder non-deterministik = test lulus hari Selasa, gagal hari Senin.** Fix anchor-weekday benar

### 🔴 F-69 — LUBANG TIMEZONE (ditemukan Claude Code, bukan Jarvis)

`APP_TIMEZONE=UTC` + kalkulator membandingkan `start_time`/`end_time` **naive** terhadap timestamp segmen.

Dampak bila dibiarkan:
```
Jam kerja 08:00–17:00 WIB
Budi klik IN_PROGRESS 09:00 WIB  → DB simpan 02:00 UTC
Budi klik REVIEW      17:00 WIB  → DB simpan 10:00 UTC
overlap([02:00,10:00], [08:00,17:00]) = 2 jam
Budi kerja 8 jam. Tercatat 2 jam. Lalu F-39 membekukannya permanen.
```

**Ini pola F-57 yang berulang persis** — kalkulator yang membekukan angka salah.
**Dan 13 test semuanya hijau** karena test ditulis naive juga. **Test membuktikan konsistensi internal, bukan kebenaran semantik.** Sistem sepakat dengan dirinya sendiri, dan sama-sama salah.

### 🔴 KOREKSI JARVIS — ANALOGI F-5 KELIRU

Jarvis awalnya merekomendasikan opsi UTC + kolom `timezone`, dengan alasan *"kolom sekarang murah, retrofit mahal — sama seperti F-5."* **Analogi itu salah.**

- **F-5 mahal di-retrofit** karena informasi "milik organisasi mana" **tidak pernah tercatat** → retrofit = menebak → data hilang
- **TZ murah di-retrofit** karena konversinya **deterministik**: WIB = UTC+7, selalu, Indonesia tanpa DST → `DATE_SUB(ts, INTERVAL 7 HOUR)` → nol informasi hilang

Tidak melanggar F-39: `actual_minutes` dibekukan sebagai **angka**; menggeser timestamp tidak mengubahnya.

**Keputusan Boss: Opsi A — `APP_TIMEZONE=Asia/Jakarta`.**
Faktor penentu: **tim fresh entry.** UTC + konversi adalah sumber bug klasik bagi pemula — lupa konversi atau konversi ganda, bug halus tanpa error, lalu angkanya dibekukan jadi dasar potongan gaji. Dengan WIB di DB, fresh entry membuka database dan melihat `09:00` yang memang jam sembilan pagi.

**Mitigasi v3.0:** bila marketplace butuh WITA/WIT, migrasi timestamp **sebelum** ada satu pun data dari zona kedua.

---

## CATATAN AUDIT — 2026-07-18 (Hari-3)

**Hasil Hari-3: LULUS.** 49 test (31 lama **tidak disentuh**), TZ aktif, `actual_minutes` tidak bergeser.
**Claude Code memverifikasi lewat browser nyata (Playwright) — melampaui yang diminta Jarvis.** Justru dari situ dua bug ketahuan.

### 🔴 F-73 — KEGAGALAN DoD JARVIS

`login.tsx` memanggil `route('register')` padahal route dicabut sejak Hari-1 → Ziggy exception → **halaman login blank**.
**Sejak Hari-1, tidak ada manusia yang bisa login ke aplikasi ini.**

DoD Hari-1 Jarvis menulis *"Bisa login dari browser sebagai admin"* dan **itu diklaim lolos** — karena Feature test menembak HTTP langsung, **melewati render React sepenuhnya**.

**Test hijau. Aplikasi mati.** Pola yang sama dengan F-69: sistem sepakat dengan dirinya sendiri, dan sama-sama salah.

**Ini kesalahan Jarvis.** DoD menuntut hasil tanpa menuntut alat verifikasi yang tepat. **Klaim tanpa alat verifikasi yang benar = stempel.**
**Aturan permanen (F-73):** setiap DoD yang menyebut UI wajib disertai bukti browser nyata.

### 🔴 F-72 — F-69 PINDAH LOKASI, TIDAK HILANG

`Model::serializeDate()` memanggil `Carbon::toJSON()` yang **hardcode konversi UTC** — mengabaikan `APP_TIMEZONE` maupun `Carbon::serializeUsing()`.
`effective_from` = `2026-07-18 00:00 WIB` di DB → sampai di React sebagai `2026-07-17T17:00:00Z` → **mundur 1 hari**.

Opsi A (F-69) dipilih justru **supaya fresh entry tidak berurusan dengan UTC**. Kalau frontend tetap menerima UTC, seluruh alasan F-69 batal.

**Claude Code menolak memperbaiki global — perilaku BENAR.** >3 file = keputusan arsitektur = eskalasi. Dia menambal `WorkSchedule` saja dan melapor sisanya.
**Keputusan Boss:** trait di 13 model (base class gugur — `User extends Authenticatable`).

### 🔴 F-74 — FRAMING JARVIS SALAH: "ENDPOINT TRANSFER" MENAMBAL GEJALA

Constraint `is_completed` = **tepat 1 per project**. Claude Code membangunnya sebagai **checkbox per-status di halaman terpisah** — checkbox secara struktural **mengizinkan** 0 atau 2, maka butuh validasi penolak, maka admin terjebak: uncheck lama ditolak (jadi 0), check baru ditolak (jadi 2).

Jarvis awalnya mengusulkan **endpoint transfer** — itu menambal gejala.
**Penyakitnya di UI: "tepat 1 dari sekian" adalah definisi RADIO BUTTON.**
Radio → submit sekali → set semua false, set pilihan true, satu transaction → **tidak pernah ada state invalid karena tidak pernah ada langkah antara.**

Biaya identik (~1–2 jam); yang satu menghapus masalahnya, yang satu menutupinya.
**Keputusan Boss:** radio, dikerjakan Hari-4.

**Deviasi lain Hari-3 — DITERIMA:** `ProjectUserObserver` pakai `assigned/unassigned` (konsisten §3.14) · `destroy()` menolak hapus pemegang flag tunggal (benar — hapus DONE = project melanggar F-19) · Textarea + input color (bukan dependency).

---

## CATATAN AUDIT — 2026-07-17 (Hari-3)

**Hasil Hari-3: LULUS.** TZ aktif, 49 test (31 lama **tidak disentuh**), `actual_minutes` tidak bergeser, pint & build bersih.

**Claude Code memverifikasi lewat browser nyata (Playwright) — melampaui yang diminta Jarvis.** Justru dari situ dua bug ketahuan. Semua constraint terbukti di form sungguhan: project baru → 4 status, work schedule insert-only (3→4 baris), backdate ditolak, hapus status berisi task ditolak, 2× `is_completed` ditolak, member 403 di route admin.

### 🔴 F-73 — KEGAGALAN DoD JARVIS

`login.tsx` memanggil `route('register')` tanpa guard; route-nya dicabut sejak Hari-1. Ziggy melempar exception → **halaman login blank**.

**Artinya sejak Hari-1 tidak ada manusia yang bisa login.** DoD Hari-1 Jarvis menulis *"Bisa login dari browser sebagai admin"* dan itu **diklaim lolos** — karena Feature test menembak HTTP langsung, **melewati render React sepenuhnya**.

**Test hijau, aplikasi mati.** Pola yang sama dengan F-69: sistem sepakat dengan dirinya sendiri, dan sama-sama salah.

**Ini kesalahan Jarvis, bukan Claude Code.** DoD menuntut hasil tapi tidak menuntut alat verifikasi yang tepat. **Klaim tanpa alat verifikasi yang benar = stempel.**
→ **F-75 lahir dari sini** dan masuk CLAUDE.md §5 sebagai aturan permanen.

### 🔴 F-72 — F-69 PINDAH LOKASI, TIDAK HILANG

`Model::serializeDate()` memanggil `Carbon::toJSON()` yang **hardcoded konversi UTC** — mengabaikan `APP_TIMEZONE` maupun `Carbon::serializeUsing()`.

```
effective_from = 2026-07-18 00:00 WIB  (benar di DB)
        ↓ serialize
React menerima  2026-07-17T17:00:00Z   → tanggal MUNDUR 1 hari
```

Kita memilih Opsi A justru agar fresh entry tidak berurusan dengan UTC. Kalau frontend tetap menerima UTC, **seluruh alasan F-69 batal**.

**Claude Code menolak memperbaiki secara global — perilaku BENAR.** >3 file = keputusan arsitektur = eskalasi. Dia menambal `WorkSchedule` saja lalu melapor sisanya.

**Keputusan Boss:** trait `SerializesDatesInAppTimezone` di 13 model. Base class gugur karena `User extends Authenticatable`.

### 🔴 F-74 — FRAMING JARVIS SALAH

Jarvis menyebut deviasi #4 butuh "endpoint transfer". **Itu menambal gejala.** Penyakitnya di UI: constraint *"tepat 1 dari sekian"* dibangun sebagai **checkbox**, padahal itu definisi **radio button**.

Checkbox mengizinkan 0 atau 2 → butuh validasi penolak → admin terjebak di antara dua langkah yang keduanya sah (uncheck lama = 0 ditolak; check baru = 2 ditolak).

**Radio menghapus seluruh kelas masalah:** satu submit, satu transaction, tidak pernah ada state antara. Ongkos identik dengan endpoint transfer; yang satu menghapus masalah, yang satu menutupinya.

**Deviasi lain — semua DITERIMA:** `ProjectUserObserver` pakai `assigned/unassigned` (konsisten §3.14), `destroy()` menolak hapus pemegang flag tunggal (benar — hapus DONE = project melanggar F-19), Textarea + input color (bukan dependency).

---

## CATATAN AUDIT — 2026-07-17 (Hari-4)

**Hasil Hari-4: LULUS.** 63 test (49 lama tidak diubah + 14 baru), pint 0 issue, build & lint bersih, **5/5 bukti browser dikonfirmasi Boss langsung**.

**F3 (akumulasi multi-segmen end-to-end) LULUS** — rantai `kerja → review → ditolak → kerja lagi → approve → freeze` terbukti utuh. Ini lubang uji yang tertinggal sejak Hari-2 (test #12 hanya di level kalkulator). Sekarang tertutup.

**Fase C (radio) sengaja tidak dikerjakan** — sesuai prioritas eksplisit di prompt ("korban → Hari-5"). Claude Code menahan diri alih-alih memaksakan hari yang sudah penuh. **Perilaku benar.**

### F-76 — SCOPED BINDINGS

`/projects/1/tasks/99` me-resolve `Task::find(99)` tanpa memeriksa `project_id`. Yang menyelamatkan: `OrganizationScope` (F-15) menutup lintas-organisasi, dan `TaskController` dipagari manual. Yang terbuka: `TaskStatusController` — admin bisa mengedit status project lain lewat URL yang salah.

**Masalah sesungguhnya bukan controller yang bolong, tapi pendekatannya:** pagar manual harus diingat di setiap controller baru. **Lupa satu = celah.** `->scopeBindings()` di route group berlaku ke semua dan tidak bisa lupa.

**Pola identik F-67** (guard FULLTEXT): daftar manual per-item selalu ketinggalan; aturan di level struktur tidak bisa.

### DUA LUBANG INFORMASI

1. **Bagian DEVIASI kosong.** H2 melaporkan 4, H3 melaporkan 5, H4 nol. Mungkin memang nol (instruksi H4 sangat rinci), mungkin hilang saat diringkas.
2. **F-77 — rich text tidak disebut.** Prompt H4 mensyaratkan lapor-dan-tunggu sebelum install editor. Tidak jelas apakah editor terpasang (dependency tanpa approval?) atau textarea polos (F-30 belum terpenuhi).

Keduanya ditanyakan lewat LANGKAH 0 prompt Hari-5, bukan lewat audit terpisah.

---

## CATATAN AUDIT — 2026-07-17 (Hari-5)

**Hasil Hari-5: LULUS.** 80 test (313 assertions), pint/lint/build bersih, **6/6 bukti browser dikonfirmasi Boss**.

Claude Code menjalankan `npx tsc --noEmit` yang **tidak diminta** — menemukan 4 type error, lalu membuktikan via `git diff` bahwa semuanya pre-existing (starter kit). Proaktif, bukan sekadar patuh.

### 🔴 F-78 — INSTRUKSI JARVIS SALING BERTABRAKAN

Jarvis menulis dua aturan yang mustahil dipenuhi bersamaan:
- B4: *"HAPUS `flagConstraintViolation()`"*
- DoD: *"63 test lama HARUS tetap lulus TANPA diubah"*

Test lama **menguji fungsi yang Jarvis perintahkan hapus**.

Claude Code mengganti test ke mekanisme baru (`updateFlags`), cakupan setara, lalu melapor dan menawarkan koreksi: *"Kalau Boss menilai ini seharusnya saya tanya dulu, tolong tandai."*

**Dia benar. Aturannya yang perlu ditajamkan:**

| | Situasi | Verdict |
|---|---|---|
| **Menambal** | Test gagal karena **kode salah** → ubah test agar hijau | ❌ DILARANG |
| **Memperbarui** | Test gagal karena **perilaku sengaja diubah instruksi** → test disesuaikan, cakupan setara, dilaporkan | ✅ WAJIB |

Aturan itu ada untuk mencegah stempel, bukan membekukan perilaku yang memang diminta berubah. **Jarvis tidak membuat distingsi ini; Claude Code membuatnya sendiri.** → masuk CLAUDE.md §5.

**Deviasi lain — DITERIMA:**
- **`FIELD()` untuk sort priority** — alfabetis menghasilkan `high < low < normal < urgent`. Bug halus yang baru terlihat saat ada yang protes urutannya
- **List di-flatten + kolom "Subtask dari: X"** — **lebih benar dari instruksi Jarvis.** Dengan filter aktif, parent bisa tersaring keluar sementara subtask muncul; indentasi jadi bohong
- Assignee filter pakai member project (konsisten Hari-4)

**Utang teknis tercatat:** 4 error `tsc` pre-existing, file auth mati (`register.tsx` dkk) belum dihapus sejak Hari-1, coverage `wouldLeaveNoWorkState()` via `updateFlags()` belum penuh.

---

## CATATAN AUDIT — 2026-07-17 (3) — KEGAGALAN REGISTRY JARVIS

**Pelanggaran Mandatory #6 oleh Jarvis, ditemukan saat menyiapkan Hari-6.**

**Apa yang terjadi:** beberapa operasi `replace` pada registry **gagal diam-diam** karena string target tidak cocok. Jarvis mencetak `registry OK` **tanpa memverifikasi bahwa perubahannya benar-benar terjadi**. Tujuh entry (F-75..F-81) tidak pernah masuk tabel, dan pointer F-64 tertinggal di F-75.

**Akibat yang lebih serius — nomor jangkar bergeser:**

| | Prompt H4/H5 (sudah dieksekusi) | Registry (salah) |
|---|---|---|
| F-73 | `/login` crash | DoD bukti browser |
| F-75 | DoD bukti browser | *tidak ada* |

Kode dan komentar Claude Code sudah menanam nomor **versi prompt**. **Yang menang adalah versi yang tertanam di kode**; registry yang dikoreksi. F-73 dikembalikan ke `/login` crash, F-75 diisi DoD bukti browser.

**Ironinya tidak lepas dari perhatian:** Jarvis menuntut *"klaim tanpa bukti tidak diterima"* dari Claude Code sejak Hari-1, lalu melakukan persis itu pada dokumennya sendiri. **`echo "OK"` bukan verifikasi — itu stempel.**

**Pencegahan permanen:** setiap perubahan registry wajib diikuti audit per-entry yang mencetak ✓/✗ per nomor, bukan pesan sukses global. Diterapkan mulai catatan ini.

---

## CATATAN AUDIT — 2026-07-18 (Hari-6) + KEPUTUSAN HARI-7

**Hasil Hari-6: LULUS.** 99 test (357 assertions), `tsc` 0 error, pint/build/lint bersih.
**Verifikasi terbaik sejauh ini:** Playwright dijalankan terhadap **MySQL nyata**, bukan sqlite.

Bukti F-79 tajam: cari `"strong"` → nihil, sementara `"krusial"` (kata yang dibold) tetap ketemu — HTML dibersihkan **tanpa** ikut membuang isinya. F-80 terbukti di DB sungguhan: `17 → 0`. Rich text editor = **Tiptap**.

**Deviasi 1, 2, 4 DITERIMA.** Khususnya #1 (menolak menulis test duplikat setelah memverifikasi coverage sudah ada) dan #2 (menerapkan F-78 tanpa perlu bertanya lagi).

### 🔴 F-82 — TIDAK ADA HALAMAN DETAIL TASK (temuan terpenting)

Rangkaiannya: F-29 melarang member edit · `description` tidak ada di kolom List View · halaman detail tidak pernah dibangun.

> **Member di-assign task, membuka aplikasi, dan tidak bisa membaca deskripsi tugasnya.**

Tiptap dibayar untuk F-30 dan **tidak ada satu pun tempat pekerja bisa membacanya**. Admin bisa lewat form edit; member tidak. **Aplikasi task management di mana pekerja tidak bisa membaca instruksi tugasnya belum berfungsi.**

**Ini lubang scope Jarvis** — "Task CRUD" ditulis tanpa memeriksa siapa yang membaca hasilnya.

### 🔴 F-83 — TEST TIDAK MENGUJI PRODUKSI

Test pakai sqlite → search jatuh ke `LIKE`. Produksi MySQL → `MATCH AGAINST`. **6 test SearchTest tidak pernah menyentuh FULLTEXT.**

Yang menyelamatkan: verifikasi browser manual, sekali. Besok ada yang menyentuh query search — 99 test tetap hijau, produksi pecah, tidak ada yang tahu. **Pola F-73 persis.** Beda sqlite/MySQL bukan cuma search: collation, `enum`, `FIELD()`, `json`, FULLTEXT.

### 🔴 F-84 — SPEC 10-TRIGGER JARVIS TUMPANG TINDIH

Admin approve → assignee dapat **2 notif untuk 1 aksi** (#3 status berubah + #7 approved).

Claude Code merekomendasikan *"biarkan, sesuai spec literal"* — **dia patuh pada spec; spec-nya yang keliru.** Jarvis menulis 10 trigger tanpa memeriksa tumpang tindih. Ini menabrak **F-36** langsung — aturan yang ada justru untuk mencegah inbox banjir.

**Keputusan Boss: opsi (b)** — #3 diam bila transisi sudah ditangkap #6/#7/#8.

### CATATAN SKALA — JUJUR

Untuk **10 user, ~5rb task/tahun, tidak ada masalah skala.** MySQL polos sanggup bertahun-tahun tanpa optimasi. **Menambah cache/queue/indeks tambahan sekarang adalah pemborosan** — setiap lapisan harus dipelihara tim fresh entry selamanya.
Satu-satunya isu performa nyata di skala ini: **N+1 query** → F-85.

---

## CATATAN — 2026-07-18 — RBAC (blueprint User & Role Management)

**Keputusan Boss atas blueprint onboarding role:**
- **F-88 RBAC penuh** — `roles` + `permissions` + `role_permission` pivot
- **F-89 1 role/user** — `users.role_id`, sesuai blueprint
- **enum PENSIUN** — `admin`/`member` jadi baris `roles` dengan `is_system=true`, tidak bisa dihapus. Perilaku identik dengan hari ini; yang berubah cuma mekanismenya (enum → role_id). **Satu sumber kebenaran**, bukan enum + RBAC berdampingan
- **F-90** — izin dievaluasi per-permission. Setiap `isAdmin()` di seluruh controller diterjemahkan jadi `can(...)`. Menyentuh SEMUA controller yang sudah dibangun — konsekuensi sadar dari "RBAC penuh"
- **Urutan:** RBAC **sekarang**, sebelum deploy & sebelum upgrade L13 (F-65). Alasan Boss: enum→role_id butuh `migrate:fresh`, gratis mumpung data kosong

**Tiga jebakan blueprint yang TIDAK diteruskan ke kode:**
- **F-91** — "buat login user" ≠ self-signup. Onboarding admin-only. Register tetap mati
- **F-92** — "hash asinkron" = keliru; Laravel hash sinkron via cast, sudah aman
- Blueprint `user.role_id` tunggal dikonfirmasi cocok dengan v0.5; multi-role (v3.0) dicatat sebagai retrofit di F-89

**Yang diterima utuh dari blueprint:** transaction wrapping (anti orphaned-user), service layer terpusat (`UserService::onboardNewUser`), tiga bentuk payload (role_id / base_role_id+custom / new_role+permissions), role dinamis tanpa hardcode.

**F-86/F-87 dari audit Hari-7:** F-86 (seeder langgar invarian) dibereskan saat rebuild seeder untuk RBAC. F-87 (hapus member bertask-aktif) TERBUKA → v0.8, saat counter/dashboard dibangun.

---

## CATATAN — 2026-07-18 — PERGESERAN URUTAN (F-93)

**Temuan saat memetakan v0.8 Hari-1:** pematangan business-hours (F-66 resolusi per-hari + F-43 holidays) mengubah rumus realisasi. F-39 membekukan `actual_minutes` saat approve dan melarang hitung ulang.

**Kalau matang SETELAH deploy:** task di-approve dengan rumus lama → beku → task berikutnya rumus baru → **data KPI dua rumus, permanen, tak bisa diperbaiki.** Pola F-57/F-69 — tapi kali ini SATU ARAH bila telat, karena data sudah nyata.

**Keputusan Boss:** pindahkan ke **pengerasan PRA-DEPLOY**, bareng RBAC, selagi `migrate:fresh` gratis.

**Urutan revisi:**
```
RBAC → HARDEN (F-66,F-43,F-87 + business-hours matang) → L13 → DEPLOY → v0.8 (6 hari)
```

**v0.8 menyusut ke 6 hari:** counter live, dashboard ×2, recurring, attachment, extension. Semuanya MEMBACA data, tidak mengubah rumus beku → aman kapan saja.

Risiko split kecil (kebanyakan kasus tepi: segmen menyeberang libur/perubahan config), tapi F-39 membuatnya satu arah. Boss bangun untuk 100+ bulan → konsistensi sejak bulan pertama menentukan.

---

## CATATAN — 2026-07-18 — HARDEN selesai, RBAC lapor belum diterima Jarvis

**HARDEN (F-66/F-43/F-87): LULUS di lokal.** 143 test (495 assertions) di MySQL. Integrasi F-66+F-43 dibuktikan lewat tinker terhadap MySQL nyata (insert holiday → recompute 300→60 → rollback → frozen tetap 300, membuktikan F-39 tak terganggu). Regression F-57 utuh: 13 test Hari-2 lulus dengan assertion identik.

**Claude Code menahan klaim LULUS untuk 2 item [BROWSER]** (Chrome extension ditolak Boss sesi itu) — menyebutnya "BUTUH VERIFIKASI, bukan LULUS". Penerapan F-75 yang benar tanpa dipaksa. Kedua item dititipkan ke LANGKAH 0 v0.8 Hari-1:
- Tambah hari libur → segmen berkurang (logic terbukti 2 lapis: unit + MySQL nyata)
- Unassign member bertask-aktif → ditolak (terbukti via HTTP feature test; pesan React belum dilihat langsung)

**Deviasi HARDEN — DITERIMA:** Unit suite boot app tanpa migrate/seed (cast date butuh resolver; tetap nol DB, selesai 1 detik) · 13 test lama adaptasi kontrak `collect([$schedule])` (F-78 memperbarui, assertion identik) · Holiday reuse `workschedule.manage` · Fase A+B satu file (loop sama).

**Keputusan Boss: fokus development lokal, TIDAK deploy dulu.** Urutan L13/deploy ditahan sampai Boss angkat sendiri. v0.8 dikerjakan lokal.

**Lubang informasi terbuka menjelang v0.8:**
1. **Laporan RBAC belum diterima Jarvis** — status user/role/permission aktual belum terverifikasi. Ditanya di LANGKAH 0 Hari-1
2. **F-63 (multi-assignee) belum diputuskan** — blocker Hari-2 dashboard, hanya Boss yang bisa memutuskan. Diangkat di LANGKAH 0, tunggu Boss sebelum Hari-2

---

## CATATAN — 2026-07-18 — RBAC laporan diterima (0A tertutup)

**RBAC LULUS.** 24 test di MySQL, enum di-drop total, satu sumber kebenaran (tabel, bukan enum+RBAC berdampingan). F-86/F-88/F-89/F-90/F-91/F-92 semua SELESAI.

**Dua implementasi melampaui spec Jarvis:**
- **`Gate::before` dengan permission sebagai DATA** — nol `Gate::define()` per permission. Role/permission baru = INSERT baris, bukan deploy kode. "Dinamis tanpa hardcode" (blueprint) dieksekusi lebih bersih dari spesifikasi
- **`wouldLeaveNoHolderOfPermission`** — Claude Code menemukan sendiri skenario lock-out (lucuti `user.manage` dari pemegang tunggal → organisasi terkunci permanen, tanpa self-signup tak ada jalan buka) lalu membangun guard-nya tanpa diminta

**F-95 — konsekuensi RBAC untuk v0.8:** member = **nol permission**. Aksi member dijaga cek assignee/membership di controller (keputusan sadar, dicatat di RolePermissionSeeder). Maka gating counter (H1) & attachment (H5) berbasis "task saya / project saya", BUKAN permission baru. Prompt H1 Fase B5 sudah selaras; H5 harus ingat ini.

**Katalog permission saat ini (7):** user.manage, workschedule.manage, project.manage, project.viewAll, status.manage, task.manage, task.approve. Dashboard (H2) akan butuh permission baru — kemungkinan `dashboard.view` — INSERT baris saja, tidak deploy kode.

---

## CATATAN — 2026-07-19 — v0.8 H1 lulus, F-63 diputuskan

**Counter Live (H1): LULUS.** 149 test PHP (MySQL) + 7 test JS (node --test). F-94 bersih — grep nol reimplementasi kalkulator di JS. F-38 dipatuhi — nol scheduler, nol state counter, frontend tick lokal.

**Bug bagus tertangkap HTTP test (bukan tinker):** `Carbon\Carbon` base → 500 saat serialize Inertia (global `serializeUsing` type-hint ke `Illuminate\Support\Carbon`). Bukti kenapa C1 wajib lewat controller nyata.

**Deviasi DITERIMA:** node --test bukan vitest (hindari dependency tanpa approval) · accumulated as-of-now + tick sederhana · **deviasi #3 unggul** — B3 (dot selalu) vs B5 (angka milik-sendiri) tak bisa dipenuhi literal bersamaan → dot=status (privasi), angka=milik sendiri. Menyelesaikan kontradiksi yang Jarvis tulis tanpa sadar.

### F-63 DIPUTUSKAN (F-96)

Realisasi sudah per-user otomatis (`task_time_segments.user_id` sejak v0.5). Tinggal 2 keputusan:
- **F-63a beban/estimasi: DIBAGI RATA** — task 4j × 2 assignee = beban 2j masing-masing. Alasan Boss: kapasitas jujur (bukan double-count 8j untuk kerja 4j)
- **F-63b poin: UTUH tiap orang** — tidak dibagi. Alasan: dorong kolaborasi, lawan Goodhart (F-4) — kalau poin dibagi, orang hindari kerja bareng demi skor

**Konsisten:** beban jujur soal kapasitas, poin memotivasi. Menyentuh langsung reward/punishment v2.0.

### F-97 — UTANG VERIFIKASI BROWSER

3 item menumpuk belum dilihat mata manusia (2 HARDEN + counter menick), Chrome extension absen 3 sesi. Bukan blocker — semua terbukti 3 lapis (unit JS, feature PHP end-to-end HTTP, tinker MySQL). Tapi ini pola F-73 (`/login` mati 3 hari sambil test hijau). Dibawa di LANGKAH 0 tiap prompt sampai terverifikasi.

---

## CATATAN — 2026-07-19 — v0.8 H2 (dashboard backend) lulus

**LULUS.** 162 test (609 assertions) di MySQL. **N+1 dibuktikan angka nyata: 8 query di 3 user, tetap 8 di 11 user** (F-85 — agregasi batch, bukan loop). Rumus F-96 multi-assignee terkunci lewat DashboardMultiAssigneeTest.

**4 deviasi = keputusan Boss langsung di LANGKAH 0 (tidak lewat Jarvis):**
1. A8 poin di-skip — kolom `tasks.points` utuh, agregasi belum perlu. F-96b tetap berlaku saat poin diagregasi kelak
2. **Atribusi realisasi ke hari-tutup (F-98)** — keputusan bermakna, dijadikan finding
3. Anomali filter `approved_at` — bermakna: anomali hanya relevan setelah freeze
4. DashboardTest starter-kit ditambah bukan ditimpa (F-78)

**F-99 — diskrepansi tsc.** Laporan H2: "tsc 3 error, pre-existing sejak Hari-5." TIDAK akurat: 3 error dari `use-live-counter.test.ts` yang dibuat di **H1 v0.8**, dan laporan H1 mengklaim **tsc=0**. Entah H1 tak menjalankan tsc atas file itu, entah klaim H1 keliru. Kecil (file test, node --test vs tsc) tapi pola F-73 (klaim hijau ≠ realita). v0.5 membawa tsc ke 0 di Hari-7; sekarang regresi 3 tanpa ditandai. Rekonsiliasi di H3.

---

## CATATAN — 2026-07-19 — v0.8 H3 lulus + aturan recurring (F-100..F-102)

**Dashboard UI (H3): LULUS.** 164 test, tsc 0 (F-99 ditutup — tsconfig exclude test files). Tiga angka F-52, tooltip F-96, anomali netral F-53, nol rupiah F-4. Deviasi kosmetik (threshold idle 50% default, welcome.tsx/app-header.tsx dead-code dilaporkan) — diterima, reversibel.

**Utang F-97 kini 4 item** (2 HARDEN + counter tick + dashboard UI) — Chrome extension absen 4 sesi. Bukan blocker, dibawa terus.

### ATURAN RECURRING ENGINE (H4) — diputuskan lewat 4 pertanyaan berturut

Keputusan Boss menghasilkan satu **konflik yang ditangkap sebelum jadi bug**:
- Boss awalnya pilih "geser semua termasuk daily"
- Jarvis tunjukkan: geser-daily butuh menengok ke belakang = **backfill** = langgar F-100 (yang Boss pilih di pertanyaan sama). Dua keputusan tak bisa hidup bersama untuk daily
- Sekaligus: tumpukan yang Boss mau **sudah otomatis dari F-60** (carryover instance belum selesai), tak perlu geser-daily
- **Resolusi Boss:** daily SKIP di libur (F-102), tumpukan tetap datang dari F-60

Hasil akhir konsisten: F-100 (no backfill) + F-101 (clamp month-end) + F-102 (weekly/monthly geser maju, daily skip). Semua forward-computable, nol backfill.

---

## CATATAN — 2026-07-19 — v0.8 H4 (recurring engine) lulus

**LULUS — subsistem terberat v0.8 mendarat bersih.** 189 test (740 assertions), tsc 0, schedule terdaftar (00:05 WIB, mendahului notify 06:00).

**Kasus tanggal tersulit terbukti dengan travelTo():** 28 Feb Sabtu → clamp 28 (F-101) → geser 2 Mar Senin (F-102) melintasi batas bulan · Sabtu → Senin minggu berikutnya · scheduler terlewat 3 hari → 1 instance (F-100) · cron 2× → 1 (F-61).

**Deviasi #4 = temuan Claude Code yang menegakkan korektnya algoritma (F-103).** Algoritma 5-langkah Jarvis cuma cek periode berjalan → occurrence tergeser lintas-bulan takkan ketemu. Claude Code menambah cek periode sebelumnya. Bukan penyimpangan — koreksi. Dicatat supaya tak ada yang "menyederhanakan" balik.

**Deviasi lain diterima:** created_by=owner (proxy akuntabel) · toggleActive bukan destroy (hapus template = FK instance menggantung) · event recurring_assignee_dropped (F-106, log jujur).

### ATTACHMENT (H5) — F-104/F-105 diputuskan

Q1+Q2 Boss kompatibel di bawah tafsir APPEND-ONLY:
- **F-104:** output beku saat approve (F-39 spirit), bebas selama review
- **F-105:** hanya admin hapus; member append-only. "Bebas selama review" = bebas MENAMBAH revisi, bukan menghapus. Riwayat submit tersimpan penuh

**F-97 kini 5 item** browser tertunda (2 HARDEN + counter + dashboard + template UI). Chrome extension absen 5 sesi. WAJIB batch-verifikasi sebelum v0.8 tutup (H7), manual kalau extension tetap absen.

---

## CATATAN — 2026-07-19 — v0.8 H5 (attachment) lulus

**LULUS.** 200 test (779 assertions), tsc 0, keamanan 3 vektor tertutup (storage privat, mime dari isi, nama acak).

**Pembuktian mime spoofing kelas atas:** Claude Code menemukan `Illuminate\Http\Testing\File` menebak MIME dari NAMA, bukan isi → test spoofing naif lulus palsu. Ganti ke `UploadedFile` berisi magic bytes PE/EXE asli (MZ...), konfirmasi tinker ditolak. Semangat F-83 (test uji jalur nyata).

**Deviasi #1 (nomor finding):** prompt Jarvis tulis F-105/F-106 untuk attachment; registry pakai F-104/F-105 — geser dari renumber gap F-103 (kesalahan Jarvis, bukan Claude Code). Claude Code ikut registry, melapor. Penomoran attachment KANONIK: **F-104 (output beku upload), F-105 (admin-only hapus, member append-only), F-107 (hapus terkunci pasca-approve)**.

**Deviasi #2 → F-107 diputuskan:** admin hapus attachment pasca-approve. Claude Code benar menolak putuskan sendiri (kebijakan, bukan bug). **Keputusan Boss: Opsi A — kunci total, bahkan admin.** Bukti beku selamanya (F-39). Salah-upload pasca-approve → arsip/tandai, bukan hapus.

**F-97 kini 6 item** browser tertunda. WAJIB batch-verifikasi sebelum v0.8 tutup (H7).

---

## CATATAN — 2026-07-19 — v0.8 H6 (extension + notif genap 10) lulus

**LULUS, deviasi NOL.** 210 test (828 assertions). F-107 ditutup (kunci hapus pasca-approve, bahkan admin). 10 trigger notif genap (F-35), semua exclude-pelaku terverifikasi termasuk kasus admin ajukan+putuskan sendiri (F-36).

**Disiplin bagus:** F-47 (original_due_date) SUDAH ADA sejak Hari-1 — Claude Code tidak bangun ulang, cuma pasang trigger #9/#10 di atasnya. Tidak menyentuh yang sudah benar.

**Claude Code menangkap sisa koreksi Jarvis:** catatan registry sempat menulis "F-108" untuk finding hapus-terkunci; dia ikut konsensus mayoritas (F-107) tanpa sentuh docs. Sisa itu dibersihkan sekarang.

**2 asumsi desain → F-108 diputuskan Boss:**
- Tenggat baru **≥ due_date** saat ini (tolak mundur; sama = nambah budget tanpa geser tenggat)
- **Evidence opsional, reason wajib** — akuntabilitas dari reason + metrik F-62, bukan paksa lampiran

**v0.8 tinggal H7:** buffer + verifikasi utuh + **WAJIB tutup F-97 (6 item browser)**.

---

## CATATAN — 2026-07-19 — v0.8 H7 (verifikasi & integrasi) — v0.8 KODE+TEST SELESAI

**LULUS.** 215 test (868 assertions), tsc 0, integrasi lintas-pilar terbukti.

**Bug F-108 diperbaiki di hari verifikasi:** validasi tenggat pakai `<=` (tolak tenggat SAMA, padahal F-108 izinkan) → `<`. Ketahuan saat menulis IntegrationV08Test. Justru fungsi hari verifikasi.

**Konsistensi angka F-94 TERBUKTI:** IntegrationV08Test membuktikan realisasi counter = dashboard = freeze angka IDENTIK. Tidak ada 3 hitungan berbeda — ketakutan H1 tertutup dengan bukti.

**9 item V08 semua LENGKAP (kode+test).** Item visual (counter/dashboard/template/attachment/hari libur/unassign) masih MENUNGGU F-97.

**Kejujuran F-73:** Claude Code menyebut gap kecil — trigger #2/#6/#7/#8 tak punya test F-36 bernama eksplisit (terjamin struktural via helper sama, risiko rendah). Tidak menyembunyikan.

### KOREKSI F-87 (kesalahan dokumentasi Jarvis)

Claude Code menemukan F-87 masih 🟡 TERBUKA di registry, padahal guard SUDAH dibangun (`ProjectController::update()`) + diuji (`ProjectTest.php`) di HARDEN. **Jarvis lupa menutupnya saat HARDEN selesai.** Claude Code melapor tanpa sentuh docs (benar). **Ditutup sekarang → 🔵 selesai HARDEN.**

### F-97 — SENGAJA MENGGANTUNG (benar)

6 item browser belum dilihat mata manusia. Claude Code menolak klaim LULUS tanpa verifikasi, buat CHECKLIST-VERIFIKASI-MANUAL.md (15 menit, +peringatan 2 attachment seeder tanpa file fisik = 404 wajar). **v0.8 kode+test SELESAI; F-97 menunggu Boss klik manual.**

### STATUS v0.8
KODE+TEST SELESAI & konsisten lintas-pilar. Menunggu HANYA verifikasi visual manual Boss (F-97). Setelah itu v0.8 resmi tuntas → jalur v1.0 (Board View) atau L13/deploy per keputusan Boss.

---

## CATATAN — 2026-07-19 — v1.0 DIMULAI (Board View)

**v0.8 kode+test SELESAI** (215 test), menunggu HANYA verifikasi visual F-97 (6 item). Boss memilih lanjut v1.0.

**v1.0 = papan visual (Kanban) + kolaborasi.** Estimasi 5-7 hari: Board read-only → drag-drop → komentar → activity log UI → buffer.

**F-109 prinsip induk Board View:** board adalah TAMPILAN, bukan jalur data. Reuse komponen v0.8, nol hitung ulang. Perubahan status via drag (H2) tetap lewat service+observer (F-45/F-41/F-51) — drag cuma UI.

**3 keputusan menanti (sebelum H2/H3, BUKAN H1):**
- F-45 × drag-drop (A tegakkan / B per-peran / C disable-kolom-tak-sah). Rek: C. Sebelum H2
- dnd-kit approval (dependency frontend pertama). Sebelum H2
- mention @user = trigger notif ke-11? (F-35 tetapkan 10). Sebelum H3

**F-97 dibawa terus** — Board mewarisi counter/dashboard v0.8. Verifikasi F-97 disarankan sebelum H2 (drag-drop tempat terburuk menemukan bug UI lama).

---

## CATATAN — 2026-07-19 — v1.0 H1 (Board read-only) lulus + keputusan H2

**LULUS, deviasi NOL.** 222 test. F-109 bersih: grep nol counter/kalkulator baru di board, reuse LiveTaskCounter, nol dependency ditambah. Board murni tampilan.

**Jujur:** `due_status` (badge tenggat) satu-satunya hitungan di BoardController — perbandingan tanggal murni (pola TaskController::index()), bukan kalkulator KPI, tak langgar F-109. Claude Code menyebut eksplisit.

**Keputusan H2 (drag-drop):**
- **F-110:** aturan **C** — kolom tak-sah di-disable saat menyeret. User lihat batasan F-45 sebelum melepas. Bukan tolak-setelah-drop. Nol aturan-per-peran. **dnd-kit** (aksesibel, standar industri) — dependency frontend pertama, disetujui Boss
- **F-111:** drop = status change lewat service+observer yang SAMA (F-45/F-41/F-51), drag cuma UI. Optimistic F-33: pindah instan, gagal → revert mulus. JANGAN bypass observer

**F-97 kini 7 item** (board masuk checklist). Extension absen 8 sesi.

---

## CATATAN — 2026-07-19 — v1.0 H2 (drag-drop) lulus + keputusan H3

**LULUS.** 231 test + 18 JS unit, dnd-kit satu-satunya dependency, F-111 bersih (grep: nol endpoint status baru; drag & dropdown pakai route tasks.status sama). F-109 utuh.

**F-112 — temuan lebih dalam dari drag:** `resolveSegmentWorker` memperbaiki bug LATEN semua jalur — segmen dulu dibuka atas nama PELAKU transisi. Admin geser task orang (drag ATAU dropdown v0.5) → catat waktu kerja PALSU atas nama admin. Diperbaiki di observer, satu tempat. **Ke-4 kali data KPI salah tertangkap selagi dummy** (F-57/69/93). 4 skenario diuji (tunggal/nol/multi/member-sendiri).

**Deviasi F-78 sah:** perilaku sengaja berubah (C3) → 2 test lama disesuaikan SETUP (admin jadi assignee), assertion F-41 dipertahankan; kasus 0-assignee diuji terpisah. Memperbarui, bukan menambal. Dilaporkan.

**Keputusan H3 (komentar + mention):**
- **F-113** komentar tabel terpisah (log tetap audit murni)
- **F-114** mention notif = kategori kolaborasi (tak langgar 10 lifecycle F-35)
- **F-115** penulis edit/hapus sendiri via soft-delete

**F-97 kini 8 item** (drag ditandai 🔴 paling butuh mata). Extension absen 9 sesi.

---

## CATATAN — 2026-07-19 — v1.0 H3 (komentar+mention) lulus + keputusan H4

**LULUS, deviasi NOL.** 240 test. F-113 bersih — test eksplisit "comments never written to activity_log" LULUS. MentionNotification class terpisah (F-114) tapi toArray() sama → bell reuse tanpa ubah.

**3 keputusan LANGKAH 0 diimplementasi sesuai usul** (token @[Nama](id), placeholder [Komentar dihapus] tanpa reveal admin, nol event comment_added). Bukan deviasi — proses LANGKAH 0 bekerja. Minor tercatat: komentar max 5000 char (arbitrer, longgar), autocomplete regex alfanumerik+spasi (tidak blokir ketik manual).

**Keputusan H4 → F-116:** log GLOBAL di-gate `activity.view` (admin default, assignable ke peran lain). Timeline per-task membership-based (F-95). Log global = data pengawasan → tidak default terbuka ke semua member.

**F-97 kini 9 item.** Extension absen 10 sesi.

---

## CATATAN — 2026-07-21 — v1.0 H4 (activity log UI) lulus + rencana H5

**LULUS.** 249 test. activity.view masuk katalog (admin default, assignable). Log READ-ONLY (route:list nol PUT/PATCH/DELETE). Peta 15 event→kalimat Indonesia lengkap termasuk F-106. ActivityLogPresenter satu sumber label (global+per-task, anti-duplikasi F-72/F-76).

**Skeptis terhadap bukti sendiri:** Claude Code menemukan N+1 PALSU di test-nya (query INSERT ikut terhitung), tak percaya angka sampai buktikan ulang via tinker isolasi (semangat F-83).

**Deviasi (1, jujur) → F-117:** event `deleted` tak bisa sebut nama objek (observer `properties=null` sejak awal). Claude Code TIDAK sentuh observer (dilarang), label jujur ke `#id`, lapor. **Keputusan Boss: utang v1.1** (fallback cukup sekarang). Jarvis catat: WAJIB tutup sebelum payroll v2.0 — "admin hapus task apa" adalah akuntabilitas, bukan kosmetik.

**Keputusan Boss H5:** F-97 (kini **10 item**, 11 sesi tanpa mata manusia) ditutup dengan **Boss klik manual** + verifikasi integrasi. v1.0 TUNTAS hanya setelah manual pass Boss.

---

## CATATAN — 2026-07-21 — v1.0 H5 (integrasi) lulus + F-59 diputuskan

**LULUS — v1.0 KODE+TEST SELESAI.** 251 test (1191 assertions) + 18 JS unit. IntegrationV10Test membuktikan rantai penuh lintas v0.5+v0.8+v1.0: create→board+list identik→drag→segmen assignee (F-112)→realisasi 45m identik dari 3 permukaan (F-94/F-109)→komentar+mention notif tapi NOL di activity_logs (F-113)→approve→beku+attachment terkunci (F-107)→log kalimat Indonesia (F-106).

**Kejujuran F-73 tajam:** drag-drop = satu-satunya lapisan v1.0 tanpa jaring pengaman otomatis (kolom redup/optimistic/revert client-side murni). Risiko tertinggi, dinamai bukan disembunyikan.

**Claude Code menangkap F-59 terlewat** (terbuka sejak sebelum v0.8, tak pernah diangkat). Baik — kebersihan registry.

**F-59 DIPUTUSKAN Boss: opsi B (sebar beban), implementasi SEKARANG sebelum deploy.** Mekanik → F-118. Momen tepat: ubah rumus beban selagi dummy (pola F-57/69/93/112). Sub-pilihan Jarvis (Boss boleh veto): estimasi PENUH bukan sisa-kerja (beban tetap perencanaan murni, decoupled dari counter); hanya hari kerja (F-43); overdue→hari ini.

**Status v1.0:** kode+test SELESAI + tugas F-59 (v1.0.1). v1.0 TUNTAS setelah: (a) F-59 selesai, (b) Boss manual pass F-97 (10 item). F-97 masih 🟡 (11 sesi tanpa mata manusia).

---

## CATATAN — 2026-07-21 — v1.0.1 (sebar beban F-59/F-118) lulus — v1.0 BUILD SELESAI

**LULUS.** 259 test + 19 unit (regresi F-57/66/43 BusinessHoursCalculator utuh). Sebar beban benar: 40j/1/5hari-kerja→480m, 40j/2→240/org, overdue&due-hari-ini→penuh hari ini, weekend/libur dilewati, N+1 turun 7→6 query.

**Batas F-94 TAHAN:** realisasi (aktif) identik LiveTaskCounter (50m), tidak tersentuh sebar. Beban=perencanaan berubah, realisasi=eksekusi utuh. Ini garis yang paling dijaga — sukses.

**F-72/F-76 dihormati:** countBusinessDays()/isBusinessDay() diekstraksi jadi SATU sumber dari loop existing, dipakai kedua fungsi. Bukan kalkulator kembar.

**2 penyesuaian F-78 sah** (DashboardTest, IntegrationV08Test) — perilaku sengaja berubah, di-rename+assertion update, grep tests/ dulu, cakupan setara.

**F-119 dicatat:** DashboardService::aktif() dead code pra-eksisting, tak disentuh (lapor, tak self-fix), bersihkan v1.1.

### STATUS v1.0 — BUILD + TEST 100% SELESAI

Semua fitur v1.0 (board, drag-drop, komentar/mention, activity log UI) + F-59 sebar beban: kode+test LENGKAP. **v1.0 TUNTAS tinggal 1 gerbang: Boss klik manual F-97 (10 item + tooltip beban baru).** Tidak ada hari build tersisa. Setelah manual pass → F-97 🟢, v1.0 resmi tuntas.

Finding TERBUKA: F-97 (verifikasi browser, Boss), F-117 (label deleted v1.1), F-119 (aktif dead code v1.1).

---

## CATATAN — 2026-07-21 — v1.2 KICKOFF: Integrasi Mockup v1.7 (aditif)

Boss menempel mockup BARU (v1.7 "Master Workspace") + prompt Claude Code "HTML = patokan frontend, ADD jangan DELETE backend". Jarvis audit SEBELUM eksekusi — temukan konflik yang akan merusak integritas KPI:

**3 keputusan Boss (menjaga fondasi):**
- **F-120:** timer v1.7 = kontrol work-state, realisasi TETAP terhitung (F-38). Timer manual DITOLAK
- **F-121:** v1.7 = pengayaan visual, BUKAN pengganti. Fitur jadi yang absen di v1.7 DIPERTAHANKAN (ADD-DON'T-DELETE ke frontend)
- **F-122:** Eisenhower p1-p4 = field baru aditif (enum lama tetap)

**Aditif lain:** F-123 checklist+gate-review · project goal/status/due · datetime tenggat · dashboard command-center (donut/heatmap/top-10, heatmap SELARAS F-118) · toggle Kanban reuse Board (F-109/110/111).

**Guardrail tetap:** F-4 (nol rupiah/skor meski dashboard lebih kaya), F-52/F-96/F-118 semantik beban, F-91 auth asli (bukan role-picker demo).

**Status v1.0:** masih menunggu Boss manual-pass F-97 (belum ditutup). v1.2 berjalan paralel; F-97 tetap utang terbuka.

---

## CATATAN — 2026-07-21 — v1.2 H1 (audit gap v1.7) selesai + koreksi Jarvis

**Audit gap SELESAI, nol kode.** Claude Code hasilkan Tabel A/B/C + rencana H2-H8. Keputusan terminal (tak lewat Jarvis) dicatat sebagai F-124..F-128.

**Catch bagus Claude Code:** interaksi checklist↔recurring (salin item ke instance), threshold konkret, kesadaran preseden trigger.

**2 koreksi Jarvis SEBELUM jadi kode:**
1. **Framing trigger meeting** — Claude Code pakai F-106 (event log) sbg preseden trigger notif; KELIRU. Preseden benar = **F-114 (mention=kolaborasi)**. Meeting-invite = notif kategori kolaborasi, BUKAN lifecycle ke-11. F-35 "10 lifecycle" tetap utuh (F-124).
2. **"Checklist wajib" AMBIGU** — Jarvis baca gate-only (kosong lolos, per logika mockup `checklist.length===0||...`); TERBUKA di F-127, resolusi sebelum H5. Kalau Boss mau setiap-task-wajib-item, itu perubahan besar.

**Rencana H2-H8 disetujui** (dengan koreksi di atas). H2 = migration aditif + model.

**Registry KANONIK dipegang Jarvis** — Claude Code DILARANG edit docs/registry (F-124..F-128 sudah dicatat di sini; H2 pakai nomor ini, jangan re-number). Claude Code sempat menulis "memori" sendiri — itu konteksnya, bukan sumber kebenaran; registry ini yang otoritatif.

**F-97 (v1.0) & F-117/F-119 (v1.1) tetap utang terbuka.**

---

## CATATAN — 2026-07-25 — v1.2 H2 (migration aditif) lulus

**LULUS.** Migration aditif: priority_quadrant, checklist items(+template child), projects.goal, meetings+meeting_user. Regresi terjaga — Schema::hasColumn assertion buktikan priority & due_date utuh, projects.status FALSE (F-125).

**A5 — keputusan terbaik hari ini:** Claude Code TOLAK tambah due_at. tasks.due_date SUDAH datetime → mockup terpenuhi. Tambah due_at = dua sumber "kapan tenggat" = ancam F-47/F-72/F-76. Mencegah pelanggaran finding dgn TIDAK membangun yg prompt Jarvis (keliru) minta. Prompt Jarvis salah di titik ini; Claude Code benar.

**2 deviasi diterima:** meeting_user id sendiri (observer H6, pola task_user); quadrant default NULL (bukan p4 yg palsukan 35 task existing). Keduanya di-flag LANGKAH 0, tak dibantah.

**2 bug pre-existing ditemukan, dilaporkan (tak fix app-code diam-diam):**
- **F-129** Role×OrganizationScope firstOrCreate — laten, fix sebelum multi-org
- **F-130** BoardViewTest flaky (now() tanpa travelTo, gagal weekend) — fix segera, "hijau" jadi bersyarat-hari (pola F-73)

**F-51 gap sementara:** checklist/meeting belum ke activity_log (observer H5/H6). Wajar.

---

## CATATAN — 2026-07-25 — v1.2 H3 FASE 0 (fix F-129+F-130) lulus

**LULUS, deviasi NOL.** 270 test (1242 assertions). F-130 fixed (travelTo hari kerja, deterministik lintas-hari — pola F-73 ditutup). F-129 fixed di KODE-APP (withoutGlobalScope hanya query seeder eksplisit ber-org_id; scope global utuh; isolasi F-5/F-15 terbukti via regresi+idempotency test). Suite kini deterministik.

**FASE A (dashboard backend) BELUM dijalankan** — Claude Code berhenti minta keputusan Boss soal RENTANG TANGGAL heatmap (opsi a/b yang dia sebut di LANGKAH 0, teksnya tidak sampai ke Jarvis). Fase A menunggu keputusan itu; instruksinya sudah ada di PROMPT-v1.2-HARI-3.

---

## CATATAN — 2026-07-25 — keputusan heatmap (F-131), Fase A lanjut

Boss putuskan heatmap: **grid bulan navigasi prev/next; hari lewat NETRAL** (bukan realisasi). Beban maju-saja (F-118), warna F-128, satu sumber F-109. Batas beban-vs-realisasi terjaga.

Fase A (dashboard backend) lanjut dengan instruksi yang SUDAH ada di PROMPT-v1.2-HARI-3 + keputusan F-131 ini. Tidak perlu prompt baru — Boss relay potongan "LANJUT Fase A" ke Claude Code.

---

## CATATAN — 2026-07-25 — BLUEPRINT v1.7 dikunci (fungsi & alur dari Boss)

Boss beri penjelasan fungsi+alur detail untuk blueprint sumber-kebenaran. Keputusan:
- **F-132** model waktu Mulai/Hold/Lanjut/Submit (segmen, realisasi dihitung — F-38 utuh). Start/Stop timer HTML = salah desain, dibuang.
- **F-133** status proyek derived sisi user; admin/top-mgmt lihat penuh utk analisa.
- **F-134** leaderboard poin→skor MANAGEMENT-ONLY. Level 1 (Σ pts disetujui + kolom konteks). Bottom-3 utk analisa manajemen bukan papan malu. Exception sadar F-4, provisional sampai v1.5 (F-2). Management-only melindungi integritas data (member tak lihat→tak game).
- **F-127 DITUTUP** (gate-only, dikonfirmasi kode mockup: checklist kosong lolos).

Kontradiksi "fear/gaming" vs "management-only" diluruskan: management-only → member tak lihat → tak bisa game → data jujur (justru selaras tujuan Boss). Bottom-3 = alat analisa manajemen.

Blueprint .md diproduksi untuk diupload ke Claude Code.

---

## CATATAN — 2026-07-25 — konfirmasi Boss: F-127 di Submit + RBAC existing (F-135)

- **F-132 diperbarui:** tombol **Submit menegakkan gate F-127** — checklist belum lengkap → Submit GAGAL; lengkap/kosong → segmen ditutup, dijumlah, status review. Menyatukan model waktu (F-132) + gate checklist (F-127) di satu aksi.
- **F-135:** RBAC (User & Peran) yang sudah dibangun Claude Code SUDAH BENAR sesuai harapan Boss (screenshot) — **JANGAN diubah** (F-121). Form Role Baru di HTML v1.7 kurang lengkap → diabaikan; yang di dev Claude Code lebih lengkap. **Akses leaderboard = permission `leaderboard.view` (baru, INSERT ke katalog) diberikan via UI RBAC yang ada.** Bukan hardcode tier.
- Blueprint diperbarui selaras dua konfirmasi ini.

---

## CATATAN — 2026-07-25 — v1.2 H3 Fase A/B (dashboard backend) lulus + addendum 5 kartu

**LULUS.** 280 test (1286 assertions). dailyLoadTotals() ditambah setelah workloadSpread(), method lama nol disentuh (F-121). commandCenter()+6 helper, index/summary/loadRows lama utuh. 1 route, nol permission baru.

**Bukti heatmap=F-118 KUAT:** dibandingkan langsung ke forUsers() ground truth (hari ini + hari depan, vantage bergeser), hari-lewat beban=null/level=null (bukan 0, bukan realisasi). Batas F-118/F-94/F-131 terbukti.

**Tie-break bug self-caught:** recentActivity() latest('created_at') → non-deterministik saat detik sama; +orderByDesc('id'). Ketemu via test N+1 sendiri (query goyang 27↔28). Skeptis bukti sendiri (pola H4 false-N+1).

**Deviasi:** #1 kategori per task_type (Boss konfirm: task_type saja cukup) · #2 top-10 filter belum-selesai (diterima) · #3 tie-break (diterima) · **#4 5 kartu ringkas TAK dibuat — KELALAIAN PROMPT Jarvis** (ada di blueprint §7.1, tak masuk A2-A9). Claude Code scope-ketat + lapor (benar). **Boss putuskan: tambah 5 kartu ke backend sekarang** (addendum) → tutup Fase A bersih.

**Fase A tutup setelah addendum 5 kartu.** Lalu re-plan H4-H8 dari blueprint.

---

## CATATAN — 2026-07-26 — v1.2 H4 (dashboard frontend) lulus

**LULUS, deviasi NOL.** 286 test. commandCenter()→commandCenterPayload() (satu sumber F-109) + commandCenterPage() Inertia. Halaman baru /dashboard/overview; /dashboard lama TETAP hidup + section "Beban Tim" read-only (F-52/F-121 dipertahankan). Grep F-109 bersih (frontend cuma presentasi: menit→jam, proporsi donut, padding kalender — nol KPI recompute). Grep F-4 bersih.

**F-97 jujur:** Claude Code TIDAK klaim lulus visual; item 11 (12 langkah) ke checklist. F-97 tetap 🟡 sampai Boss klik.

**2 item UX ditunda (bukan deviasi, dilaporkan):**
1. Klik→filter 5 kartu DITUNDA — halaman "Semua Tugas" §7.3 belum ada. Disambung saat §7.3 dirakit. DITERIMA.
2. Login landing masih /dashboard lama (nav→/dashboard/overview baru). Claude Code tak sentuh AuthenticatedSessionController (katanya "ada perubahan Auth lain berjalan"). 🔴 Jarvis MINTA KLARIFIKASI Boss: ada kerja Auth paralel di luar registry? Kalau tidak, redirect login = 1 baris aman.

**Catatan konsolidasi (future, F-121):** kini ada 2 dashboard (/dashboard lama + /dashboard/overview baru). Data 3-angka muncul di keduanya. Suatu saat /dashboard lama bisa dipensiun setelah command-center terverifikasi (F-97) — keputusan Boss, bukan sekarang.

---

## CATATAN — 2026-07-26 — v1.2 H5 (leaderboard Level 1) lulus

**LULUS.** 295 test. leaderboard.view (default_admin=false) → admin biasa 403 (C1, skenario paling berisiko terbukti). LeaderboardService batch (1 query, F-85). Point=Σ pts disetujui (F-39 beku), on-time original_due_date (F-47), kolom konteks terpisah (F-62). Provisional note tampil (F-2). F-4 bersih (nol rupiah).

**F-136:** admin ≠ semua permission lagi (flag default_admin=false, opt-in). leaderboard.view pertama. Konsisten F-90.

**Deviasi F-78 sah:** 3 RolePermissionTest diperbarui (perilaku admin sengaja berubah), cakupan setara + assertion baru, dilaporkan.

**F-97 kini 12 item** (leaderboard = item 12). Extension absen. Boss belum jawab: klarifikasi Auth paralel + klik F-97.

---

## CATATAN — 2026-07-26 — v1.2 H6 (Eisenhower + checklist gate) lulus

**LULUS.** 318 test (295 lama utuh + 23). **Gate F-127 terwujud di SATU titik** (TaskTransitionService) → otomatis jaga dropdown + drag (endpoint tasks.status sama, F-111). Kosong lolos, tuntas lolos, belum-tuntas ditolak. Edge B3 diuji (item ditambah pasca-review tak menendang mundur). Eisenhower UI (F-122/126), enum lama tersembunyi. Template copy checklist ke instance is_done=false, idempotency F-61 utuh, eager load (F-85).

**Defensif bagus:** template sync checklist hanya kalau has('checklist_items') → cegah wipe diam-diam caller lama.

**Deviasi:** #1 filter/sort List/Board masih enum lama (task baru tak punya nilai enum → makin tak berguna); KEPUTUSAN BOSS: migrasi quadrant? · #2 checklist detail-only (blueprint §7.3, DITERIMA).

**F-137:** dua sumber warna Eisenhower (command-center vs lib) — dedup, debt.

**F-97 kini 13 item** (item #13 = Eisenhower+checklist+gate). Extension absen. Boss run `composer run dev` untuk verifikasi manual.

---

## CATATAN — 2026-07-26 — keputusan model waktu H7 (F-138/F-139)

Boss putuskan 3 (semua ke jalur "user kontrol jamnya"):
- Drag ke dikerjakan = STATUS saja, assignee klik Mulai untuk mulai jam (F-138c).
- Task ditolak = JEDA, assignee klik Lanjut (F-138d).
- Filter/sort List/Board migrasi ke quadrant (F-139, di H7b).

🔴 F-138 MENGUBAH F-41: segmen tak lagi otomatis buka saat masuk dikerjakan — hanya via Mulai/Lanjut. Test lama F-41-dependent update jujur (F-78). Realisasi tetap Σ segmen (F-38/F-94).

Jarvis putuskan: jeda=turunan (bukan field), F-57 cap tetap, jeda=counter abu-abu.

H7 = model waktu (core, rawan). H7b = kanban v1.7 visual + filter/sort quadrant. H8 meeting. H9 buffer+F-97.

---

## CATATAN — 2026-07-28 — audit mismatch dashboard-harapan + arah design system

Boss angkat: tampilan Claude Code ≠ blueprint (dashboard-harapan). Jarvis audit jujur:
**celah PROMPT/BLUEPRINT, bukan Claude Code salah** — blueprint tangkap fungsi/data, under-specify visual (0 penyebutan: Status Project/branding/sidebar/filter-widget/token di blueprint & prompt H3/H4). Prompt H4 malah "jangan salin tema mockup".

**F-140** celah diakui · **F-141** proteksi beda-yang-benar (heatmap netral/donut NULL/leaderboard-hidden JANGAN "diperbaiki") · **F-142** branding=fitur settings · **F-143** tema=fitur settings+gradasi · **F-144** fondasi design dulu.

**Rencana design (sebelum lanjut H7):** DS-1 token+sidebar → DS-2 branding settings → DS-3 tema settings → DS-4 fidelity dashboard (Status Project widget + filter per-widget). Lalu resume H7 (timer) dst dalam gaya baru. Blueprint diperkaya §12.

---

## CATATAN — 2026-07-28 — v1.2 DS-1 (token + sidebar) lulus

**LULUS, frontend-only.** 318 test IDENTIK (nol logika, git buktikan 8 file frontend). Token --tempo-* overridable (@theme, siap DS-3). Sidebar Ringkasan/Kerja/Organisasi, gating dipertahankan (F-90/95/134). Self-catch: border bug topbar + notif token. Judgment bagus: warna status global-search TAK di-token (itu data bukan brand).

**F-145** --primary=amber (deviasi #3) → Jarvis duga salah tafsir (mockup btn-pri=ink); putuskan saat verifikasi browser · **F-146** bug laten sidebar.tsx hsl ganda · **F-147** 3 nav disabled "SEGERA" wajib di-enable saat dibangun.

**F-97 kini 14 item.** 🔴 DS-1 = pekerjaan VISUAL MURNI → verifikasi browser tak bisa ditunda lagi (tak bisa tuning tampilan sambil buta). Ini momen Boss buka `composer run dev` + tutup 14 item sekaligus.

---

## CATATAN — 2026-07-28 — DS-1 terverifikasi browser + amber diterima

Boss verifikasi browser DS-1: "sudah sesuai". **F-145 resolved** — --primary=amber diterima (pilihan sadar Boss, tak dikoreksi ke ink). Fondasi visual TEMPO berdiri & terlihat mata manusia.

PENDING konfirmasi: apakah "sudah sesuai" = seluruh 14 item checklist (termasuk fungsional: gate dropdown+drag, admin-403 leaderboard, checklist CRUD) → tutup F-97; atau baru tampilan DS-1 → F-97 tetap sebagian terbuka. Jarvis tanya Boss.

Lanjut: DS-4 (Status Project widget + filter per-widget + dedup warna F-137) — melengkapi dashboard sesuai keluhan awal Boss.

---

## CATATAN — 2026-07-28 — v1.2 DS-4 fase FILTER lulus (Status Project pending)

**LULUS (sebagian).** 327 test (+9). 🔴 ANOMALI: kode filter per-widget + dedup F-137 DITEMUKAN sudah ada di working tree (sesi sebelumnya, di luar loop audit — belum commit/test/catat). Claude Code tutup dgn 9 test (filter scope benar, heatmap_user_id skala F-128, summary_cards TAK terfilter), tolak edit registry sendiri (benar).

**Registry menyusul kenyataan:** filter per-widget = ADA & bertest (retroaktif dicatat di sini). **F-137 DEDUP SELESAI** (ditemukan sudah dikerjakan). **F-148** = type-cast filter (rekomendasi cast int).

🔴 **2× anomali uncommitted (docs, lalu kode filter).** RISIKO drift registry↔kenyataan. WAJIB: Boss commit tiap sesi + lapor apa yang dibangun supaya Jarvis catat.

**PENDING:** widget Status Project (belum digarap — method derivasi status F-125 belum ada). F-97 masih menunggu konfirmasi cakupan Boss.

---

## CATATAN — 2026-07-28 — filter dashboard HILANG (uncommitted) + protokol commit

Boss lapor: fitur filter per-widget HILANG dari dashboard. Diagnosis Jarvis: kode filter belum pernah di-commit (dibangun di luar loop, cuma working tree) → rapuh, kemungkinan lenyap oleh operasi git/reset. **F-137 (dedup) sempat ditandai selesai atas laporan Claude Code — kalau ikut hilang, statusnya ikut dipertanyakan** (registry berbohong soal yang uncommitted — persis bahaya yang diflag).

**F-149 PROTOKOL COMMIT WAJIB** ditetapkan. DS-4b digabung: diagnosa+pulihkan filter → fix F-148 → widget Status Project → COMMIT wajib.

---

## CATATAN — 2026-07-28 — v1.2 DS-4b lulus + SELURUH BACKLOG DI-COMMIT

**LULUS.** 330 test. 🎯 **COMMIT PERTAMA NYATA** (172603e, 303 file) — seluruh proyek v0.5→DS-4b tersimpan permanen. F-149 berbuah.

**Misteri filter: TIDAK hilang** — utuh sejak LANGKAH 0 (backend+9test+frontend selector+tombol global). "Menghilang" = kemungkinan BUILD BASI (kode di tree, belum di-build ke bundle tersaji). Kini npm run build sukses + committed → muncul di serve segar. F-137 dedup ikut utuh (committed).

**F-148 SELESAI** (7 param cast int). **Status Project widget** (counts, top-5 by task_total DESC, sortable, arsip dikecualikan). Deviasi #1: sort task_total = asumsi (Boss boleh ganti mis. deadline terdekat). Bug test self-caught (actingAs).

**F-150:** 3 lint error DS-1 (app-sidebar/app-logo) — bersihkan saat DS-2/3.

**F-97 kini 15 item.** Boss belum jawab cakupan verifikasi.

---

## CATATAN — 2026-07-28 — aktivasi Semua Tugas + Tugas Berulang lulus

**LULUS, deviasi NOL, committed 5f245fe.** 341 test (+11). git diff 4 file backend (+142/-4), NOL sentuh engine recurring/transisi/observer (grep buktikan) — wiring/UI murni. Semua Tugas: toggle List/Kanban (kanban per-project, keputusan Boss), filter quadrant F-139, gated project.viewAll. Tugas Berulang: gated task.manage, aktif.

**F-147: 2 dari 3 nav aktif** (Setelan tersisa → DS-2/DS-3). **H7b terserap** (kanban toggle + F-139 selesai di sini). **F-150** lint DS-1 dikonfirmasi pre-existing (git stash), tak diperbaiki (scope).

**F-97 kini 16 item, MASIH BLOCKED** (Chrome extension tak konek 16 item beruntun). Claude Code tawarkan Opsi B: koneksikan ulang extension → Claude Code verifikasi sendiri. Ini momen genting — 16 item UI belum pernah dilihat mata.

---

## CATATAN — 2026-07-28 — scope Setelan dikonfirmasi Boss: Branding + Tema penuh

Boss pilih branding "PENUH" + custom warna tema (tombol & komponen). Dibangun 2 sesi di menu Setelan:
- **DS-2** = halaman Setelan (struktur tab) + Branding (logo/nama/alamat/wa/sosmed, org-scoped, permission settings.manage default-admin). Aktifkan menu Setelan (F-147 menu terakhir).
- **DS-3** = tab Tema (editor warna TOKEN inti → komponen ikot otomatis berkat fondasi DS-1, + gradasi, live preview, reset default). F-143.

Kunci: editor tema mengubah nilai TOKEN (F-144/DS-1), komponen mewarisi — bukan edit per-komponen. Fondasi token DS-1 berbuah di sini.

Setelah Setelan lengkap: H7 model waktu → H8 meeting → H9 tutup. Verifikasi browser 16 item tetap tergantung (Boss belum pilih jalur).

---

## CATATAN — 2026-07-28 — v1.2 DS-2 (Setelan + branding) lulus

**LULUS, deviasi NOL, committed c972b04.** 351 test (+10). 7 kolom branding di organizations (aditif F-121), logoUrl() satu sumber, settings.manage (default_admin TRUE), sidebar dinamis + fallback default, org isolasi (F-5). Self-correct: tab manual (React state) krn @radix tabs bukan dependency — nol dependency baru.

**F-147 TUTUP** (3 nav semua aktif). **F-150 BERSIH** (lint 0). Keputusan Boss: storage kolom organizations, 1 URL+tab client-side (siap DS-3), sosmed di footer sidebar. **Logo storage PUBLIC** (trade-off sadar performa, disetujui).

🔴 **Registry+tracker belum ter-commit di repo** (Claude Code benar tak menyentuh). Celah protokol: Boss WAJIB commit docs saat menyalin (bukan cuma kode). Solusi: `git add -A` di commit final atau Boss commit docs manual.

**Berikutnya DS-3 (editor tema) — slot tab sudah siap.** Verifikasi browser 17 item tetap tergantung.

---

## CATATAN — 2026-07-28 — menu Setelan HILANG dari sidebar (diagnosa)

Boss lapor Setelan hilang. DS-2 SUDAH committed (c972b04) → kode kemungkinan utuh. Dugaan Jarvis (urut kemungkinan):
1. BUILD BASI (pola sama filter — kode ada, bundle belum di-build ulang) → npm run build + restart serve.
2. Permission settings.manage tak ter-assign admin (gating F-90 sembunyikan menu) → re-seed/assign.
3. Git revert (dicek).

Prompt FIX-SETELAN dibuat: diagnosa 3 kemungkinan → perbaiki → COMMIT wajib (F-149). Pola berulang "UI hilang" = hampir selalu build/serve basi ATAU permission belum seed — BUKAN kode hilang (karena sudah commit).

---

## CATATAN — 2026-08-04 — REKONSILIASI dari laporan sistem (drift terungkap)

Laporan sistem (Claude Code) ungkap: registry/tracker Jarvis TERTINGGAL. Kenyataan repo v1.2 ~72% (bukan 68%). Yang SUDAH committed tapi belum teraudit Jarvis:
- **DS-3 editor tema (49170d7)** — SELESAI (F-143/144/145 sudah tercatat; implementasi kini terkonfirmasi committed).
- **10 commit tambahan (belum teraudit, direkonsiliasi):** d4c3643 Log Aktivitas 4 kartu · 1903b2b CommandCenter Top-10 sortable+workload modal · 0e05432 Leaderboard Top-3/Bottom-3 cards · 619a33d Pengguna&Peran 2-kolom · 8f59288 Tema pisah token teks-button · bcff7ac..2618793 Kalender Beban Tim modal (4 commit) · 18134f9 subtask=task.manage (→ F-155).

**Belum benar-benar dikerjakan:** H7 (timer work-state), H8 (Meetings — migration+model ADA, controller BELUM), H9 (buffer+tutup F-97).

🔴 **3 ISU WORKING-TREE (belum committed, butuh KEPUTUSAN Boss):**
1. Refactor Setelan personal → modal (32 file, hapus 3 route settings/*, DatabaseSeeder −421 baris termasuk header klasifikasi §3.1) → 1 TEST GAGAL (query count 41→42).
2. 2 DEPENDENCY baru (framer-motion, sweetalert2) TANPA approval (langgar CLAUDE.md §4).
3. BUSINESS RULE baru: user tanpa user.manage tak bisa ganti email sendiri (server-enforced) — belum diputuskan.

🔴 **PELAJARAN PROSES:** kerja di luar loop prompt→audit → registry jadi fiksi. Perlu: setiap sesi Claude Code dilaporkan balik ATAU rekonsiliasi berkala seperti ini.

---

## CATATAN — 2026-08-04 — keputusan working-tree: RAPIKAN & COMMIT refactor Setelan-modal

Boss: deps approve keduanya (F-156), rule email setuju (F-157). Q1 tak dijawab eksplisit → diinterpretasi RAPIKAN & COMMIT (approve deps+rule = pertahankan refactor). Prompt pembersihan: fix test query-regression (41→42, prefer balik 41) + kembalikan header §3.1 DatabaseSeeder + 2 lint debt → commit (F-149). Kalau Boss mau stash, koreksi.

---

## CATATAN — 2026-08-04 — Automation Engine: arsitektur extensible + §7 final; blueprint diperbarui

Boss: fokus Automation Engine, JANGAN sentuh kode UI yang Boss ubah sendiri (prompt rapikan-modal DIBATALKAN). Update alur+logika agar mudah dikembangkan + update blueprint.

**F-158** arsitektur extensible (Trigger->Guard-chain->Resolver->Action; Strategy anchor; Decision+log; data-driven; siap event-driven). **F-159** §7 final (period_key=tanggal, notif-sekali, run_log=tabel, migrasi time_based).

SPEK-AUTOMATION-ENGINE-v1.3 diperkaya §8 (arsitektur extensible) + §7 diresolusi. Blueprint utama +§13. Working-tree UI = urusan Boss (tak disentuh).

---

## CATATAN — 2026-08-04 — v1.3 AE-1 (skema) lulus, committed 84d5e74

**LULUS.** 4 migration (8 kolom template + period_key/unique + automation_run_log + data-migration). Down diuji nyata (rollback+re-apply). 5 test skema + 21 recurring lama utuh (F-121). Full 367 pass, 1 fail = bug PRE-EXISTING DashboardCommandCenterTest (42≠41, dari refactor UI manual Boss — dikonfirmasi via git stash, BUKAN dari AE-1).

**Judgment benar:** (a) org_id di automation_run_log = kepatuhan F-5 (permanen, tak perlu approval baru); (b) FK reuse `task_template_id` (bukan kolom baru); (c) down-migration InnoDB FK-index fix (index tunggal sebelum drop composite) — diverifikasi rollback nyata.

**Bukti migrasi:** DB dev kosong → 0 baris kena; logika dibuktikan E2 (3 legacy → konversi benar, idempotent).

**OPEN:** bug DashboardCommandCenterTest (query 41→42, area UI Boss) masih terbuka — perlu keputusan Boss (fix query double-load / update test / biarkan). Kolom AE belum dibaca logika (murni skema, aman). NEXT: AE-2 pipeline.

---

## CATATAN — 2026-08-04 — v1.3 AE-2 (pipeline) lulus, committed 9ae19a5

**LULUS.** 394 pass (+27), 1 known-fail (UI Boss, tak nambah). Pipeline extensible penuh (5 guard, 3 strategy registry-key, HolidayShift, GenerateAction, command+scheduler WIB). Idempotency berlapis (F-61) + isolasi (F-160) diuji.

**Menonjol:** (a) bug reason-overflow varchar(255) yang mematahkan isolasi ketemu+fix (Str::limit 250); (b) BusinessHoursCalculator::isBusinessDay reuse (F-72/76, bukan kalkulator ke-3); (c) commit di-scope ke file AE-2 (BENAR — koreksi 'git add -A' Jarvis, lihat F-149 klarifikasi).

**Deviasi diterima:** ActiveTemplateGuard selalu-pass di prod (pre-filter query) tapi tetap class terpisah utk future trigger (F-158) · 'shift' = generate dgn tanggal digeser + label log beda (interpretasi masuk akal, diterima).

🔴 **F-162 DUAL-ENGINE:** 2 scheduler aktif → double-gen saat deploy. Tutup di AE-3 (cutover) atau nonaktifkan scheduler baru dulu. Laten (deploy ditahan).

**Minor:** reason varchar(255) dijaga Str::limit di Command, tapi tak otomatis di titik lain — pertimbangkan kolom TEXT (AE-3). **Working tree UI Boss masih uncommitted** (Boss commit sendiri).

NEXT: Boss pilih AE-2b (form) atau AE-3 (cutover+notif). Jarvis condong AE-3 (tutup F-162 + harden) dulu.

---

## CATATAN — 2026-08-04 — v1.3 AE-3 (cutover+notif+harden) lulus, committed 5176563

**LULUS — fase paling matang.** Parity dibuktikan SEBELUM cutover (daily/weekly/monthly persis). **F-162 DITUTUP** (1 scheduler, verified). Engine lama @deprecated utuh (rollback). Notif Opsi B (F-154): sekali, kolaborasi F-114, clear on resolve. Edge: miss-run catch-up-satu (F-152), holiday-shift lintas-bulan (F-153), deadlock notif-1x, CalendarAnchored day-31=skip. 406 pass, 1 known-fail (tak nambah). reason→TEXT via raw ALTER (hindari dbal dependency).

**F-163 DRIFT** (weekly/monthly time_based geser anchor pasca miss-run → harusnya calendar_anchored; dev kosong, belum menggigit). **F-164** CalendarAnchored day>28 skip vs clamp. Keputusan Boss sebelum AE-2b.

NEXT: putuskan drift (F-163/F-164) → lalu AE-2b (form) → AE-4 (opsional UI riwayat).

---

## CATATAN — 2026-08-04 — keputusan drift + AE-2b digabung

Boss: (1) YA perbaiki drift — weekly/monthly → calendar_anchored (day_of_week/day_of_month), daily tetap time_based; tambah CLAMP akhir-bulan (F-101) ke CalendarAnchored (ganti perilaku skip E4, update test F-78). (2) Gabung perbaikan ke AE-2b (form konfigurasi). Form di halaman Tugas Berulang: pilih anchor A/B/C + field terkait (interval / hari-tetap / completion) + guard (date_window, quota) + is_active. Inilah panel Boss atur "tiap N hari" sendiri.

---

## CATATAN — 2026-08-05 — v1.3 AE-2b (form+fix drift) lulus, committed 0fefe75

**LULUS — lingkaran tertutup.** End-to-end D4 (HTTP POST sungguhan): form "tiap 3 hari"→engine generate tiap 3 hari; form "Rabu"→hanya Rabu. Drift F-163 SELESAI (idempotent, hormati pilihan manual Boss). Clamp F-164 SELESAI (E4 update F-78). Form Tugas Berulang: anchor A/B/C + guard config. 

**Tangkapan bagus:** TimeDeltaGuard NULL interval→Pass (cegah UnhandledMatchError calendar_anchored, celah baru terbuka form). 6 test payload lama +anchor_strategy (F-78).

**Automation Engine v1.3 PRAKTIS LENGKAP** (AE-1/2/3/2b). Sisa AE-4 opsional (UI riwayat run_log). Minor: preview form = client-only deskriptif (bukan simulasi presisi; kalau mau presisi perlu endpoint — opsional). Latent: template prod lama ter-drift-fix saat migration deploy.

NEXT: AE-4 opsional ATAU kembali v1.2 (H7/H8/H9).

---

## CATATAN — 2026-08-05 — v1.2 H7 (model waktu) lulus, committed fd2a6ef

**LULUS — fase paling rawan v1.2, eksekusi matang.** Baseline 424+1-known-fail. F-138 diimplementasi PENUH: segmen buka HANYA via Mulai/Lanjut (F-41 lama diubah), drag/reject=0 segmen, jeda=turunan. 🔴 **Realisasi Σ segmen TAK berubah angkanya** (75/90/45 tetap; hanya mekanisme). F-57 cap terbukti (Jum16:00→Sen09:00=120m bukan 3900). F-94 konsisten (50=50=50). Checklist gate F-127 tetap di transisi (dropdown & Submit, F-111 one-gate).

**F-41 kini DIGANTI F-138** (segmen eksplisit) — diimplementasi H7. TaskObserver auto-open + resolveSegmentWorker jadi dead code (dihapus).

**Disiplin:** BoardDragTest (lolos grep, ketemu full-suite pre-commit → 3 test update jujur F-78, angka akhir tetap). UI manual Boss tak diclobber (ikut pola confirmAction/showError existing). Commit scoped 14 file.

**F-97 kini 19 item.** 🔴 H7 SANGAT visual (4 tombol + jeda) + payroll-critical → verifikasi browser paling mendesak. Dev server JALAN, data uji SIAP (task 5, asep@deevatech.test).

NEXT: verifikasi item 19 → lalu H8 (Meetings) → H9.

---

## CATATAN — 2026-08-05 — Boss lompat ke H9 (H8 Meetings ditunda)

Boss: "langsung H9 saja". **H8 Meetings DITUNDA/skip** (migration+model ADA sejak 25 Jul, controller/UI belum — bisa disusun kapan saja kalau Boss mau). H9 = FINALISASI: stabilkan working-tree (commit tertunda + resolusi 1 known-fail query 42≠41), regresi penuh, konsistensi lintas-fitur (F-94/F-109), sinkron docs, kompilasi checklist verifikasi F-97 jadi satu pass. 🔴 H9 TAK menutup F-97 (butuh mata Boss) — hanya menyiapkannya rapi.

---

## CATATAN — 2026-08-05 — v1.2 H9 (finalisasi) lulus 🎯 — SUITE HIJAU

**LULUS.** 435 pass, 0 fail (known-fail lama tuntas). 3 commit terpisah (99c7c1c kerja UI Boss + F-156/157, b50f993 docs sync, 270b2a4 H9). Working tree bersih, 22 commit lokal siap push (BELUM push — tunggu Boss).

**Root-cause query 41→42:** BUKAN N+1 — cacat fixture (actingAs() dipanggil setelah 3 task ditulis → user_id NULL → eager-load di-skip; batch berikut terisi → query muncul). Fix: pindah actingAs() ke awal (F-78, bukan naikkan angka). Query sah, test-nya yang salah hitung.

**F-94/F-109 konsisten** (satu sumber, grep nol duplikat). **F-97** checklist terkonsolidasi 19 item (TAK diklaim tuntas).

**F-165:** workload_top5 dihitung tak dirender (kemungkinan regresi). Keputusan Boss.

🎯 **v1.2 PRAKTIS TUNTAS & HIJAU.** Sisa: verifikasi F-97 (mata Boss) + keputusan push + F-166 + opsional H8/AE-4.

---

## CATATAN — 2026-08-05 — desain SISTEM KPI (v1.4) dikunci

Boss: fitur KPI indikator sederhana (ontime=5/telat=3/notdone=0, default+admin override), dibangun EXTENSIBLE agar versi nanti tinggal disable/ganti setting. Boss konfirmasi: (1) pluggable+config+toggle (F-166), (2) bekukan saat approve (F-167), (3) kpi_score ganti Σ pts di leaderboard (F-168).

Pola sama Automation Engine (F-158) & tema/branding (config-driven). Not-done: versi sederhana konteks-saja, versi ketat strategy nanti.

Rencana: KPI-1 (schema kpi_score + config + SimpleTimelinessStrategy + freeze at approve) → KPI-2 (integrasi leaderboard ganti Σpts + Setelan UI config + master toggle + tampil skor task). Feature v1.4.

---

## CATATAN — 2026-08-09 — rekonsiliasi laporan KPI + revisi F-168; KPI-1 mulai

Laporan KPI Claude Code (9 Agu) AKURAT soal state: formula FINAL ditunda v1.5 (F-2/F-58), 6 metrik terkumpul sejak Hari-1 (F-37: ditolak/estimasi-aktual/points/quality_rating/lama-status/geser-due), leaderboard Point=Σpts + on-time%/rating/rejection TERPISAH (F-62), F-39 freeze, F-4 no-money. (Bagian roadmap laporan agak basi: DS-3 & H9 sudah selesai; laporan klaim leaderboard browser-verified padahal F-97 masih pending di tracking kita.)

**BUKAN kontradiksi:** simple-KPI-Boss = indikator SEMENTARA provisional (F-2 dihormati), v1.5 = formula terkalibrasi (tukar strategy). Data fondasi SUDAH ADA → KPI-1 ringan. original_due_date (F-47) sudah dipakai on-time%.

**F-168 DIREVISI:** kpi_score = kolom terpisah (bukan ganti Σpts); F-62 dipertahankan penuh. Boss pilih ini setelah paham pemisahan timeliness disengaja.

**Boss: LANJUT bangun KPI-1 sekarang** (schema kpi_score + config + SimpleTimelinessStrategy + freeze at approve).
