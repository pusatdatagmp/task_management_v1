# PROMPT RBAC — USER & ROLE MANAGEMENT (enum → RBAC penuh)

> **Menyentuh SEMUA controller yang sudah dibangun.** Ini bukan fitur tambahan — ini mengganti cara izin bekerja di seluruh sistem.
> **Dikerjakan sebelum deploy & sebelum upgrade L13** (keputusan Boss: enum→role_id butuh `migrate:fresh`, gratis mumpung data kosong).

---

## §0. YANG BOSS LAKUKAN DULU

Salin ulang 2 file:
```
CLAUDE.md                    <- +F-90 (izin per-permission)
docs/04-FINDING-REGISTRY.md  <- +F-86..F-92 + catatan keputusan RBAC
```

---

## §1. PROMPT — SIAP TEMPEL

```
=== MULAI ===

Kamu adalah Jarvis. Baca CLAUDE.md di root sebelum melakukan apa pun.

## LANGKAH 0 — BACA & LAPOR (DILARANG MENULIS KODE)

Baca utuh:
1. CLAUDE.md                    <- F-90 BARU
2. docs/02-DATA-MODEL.md        <- §3.4 users (kolom role akan berubah)
3. docs/03-BUSINESS-FLOW.md     <- §6 matriks permission (jadi sumber daftar permission)
4. docs/04-FINDING-REGISTRY.md  <- F-86 s/d F-92 BARU

Lalu LAPORKAN:
- Konfirmasi kamu paham enum admin/member PENSIUN jadi role sistem (F-88)
- INVENTARIS: daftar SEMUA tempat di kode yang saat ini memanggil isAdmin() atau
  memeriksa role enum. Ini peta pekerjaan Fase D. Grep dulu, tempel hasilnya.
- Terjemahkan matriks permission Business-Flow §6 jadi daftar permission konkret
  (usulkan penamaan, TUNGGU Boss setujui sebelum Fase A)
- Checklist Fase A-G, dependency-aware
- Hal yang belum jelas / bertentangan

BERHENTI. Tunggu Boss bilang "LANJUT".

## KONTEKS & ATURAN INDUK

Sistem ini sudah 7 hari dibangun. Role saat ini = enum('admin','member') di users.
Boss memutuskan RBAC penuh (F-88): tabel roles + permissions + pivot.

ATURAN INDUK — SATU SUMBER KEBENARAN:
enum PENSIUN sepenuhnya. TIDAK BOLEH ada enum role DAN RBAC hidup berdampingan.
Dua sistem izin untuk pertanyaan yang sama = fresh entry tidak tahu mana yang
berlaku, salah satunya pasti membusuk. Ini pola yang sudah kita bunuh 2x
(F-72 dua serializeDate, F-76 dua cek scope). Jangan hidupkan lagi.

admin & member TIDAK dihapus secara perilaku — mereka jadi role sistem
(is_system=true) yang perilakunya IDENTIK dengan hari ini. Yang berubah cuma
mekanismenya: enum -> role_id.

## FASE A — SKEMA (F-88, F-89)

A1. Migration tabel roles:
    id, organization_id (F-5), role_name, is_system (bool, default false),
    is_default (bool - role otomatis untuk user baru), created_by (nullable FK users),
    timestamps
    UNIQUE(organization_id, role_name)

A2. Migration tabel permissions:
    id, permission_name (mis. 'task.create'), module (mis. 'task'), timestamps
    UNIQUE(permission_name)
    CATATAN: permissions TIDAK per-organization - ini kamus sistem global,
    sama untuk semua tenant. JANGAN beri organization_id.

A3. Migration pivot role_permission:
    role_id, permission_id, timestamps
    UNIQUE(role_id, permission_id)

A4. Migration ubah users:
    - TAMBAH role_id (FK roles, nullable dulu untuk migrasi bertahap)
    - JANGAN drop kolom role enum di migration yang sama - drop di migration
      TERPISAH setelah data dipindah (A5), supaya bisa rollback aman
    Setelah role_id terisi & diverifikasi: migration ketiga men-drop enum.

A5. Data migration: petakan enum lama -> role sistem baru
    'admin'  -> role_id role admin
    'member' -> role_id role member

A6. F-5: roles & pivot punya organization_id (kecuali permissions - A2).
    F-15: Role & Permission model pakai OrganizationScope? Role YA. Permission TIDAK
    (kamus global). JELASKAN pilihan ini di komentar.

## FASE B — MODEL & RELASI

B1. Model Role, Permission. Trait SerializesDatesInAppTimezone (F-72) di keduanya.
    BelongsToOrganization di Role saja (F-88), TIDAK di Permission.

B2. Relasi:
    User belongsTo Role
    Role belongsToMany Permission (via role_permission)
    Role hasMany User

B3. User helper - GANTI isAdmin() lama:
    - hasPermission(string $perm): bool  -> cek lewat role->permissions
    - Pertahankan isAdmin() SEMENTARA sebagai shim: return role->is_system && name=='admin'
      TANDAI @deprecated. Ini jembatan supaya Fase D bisa bertahap, BUKAN permanen.
      Semua isAdmin() harus hilang di akhir Fase D.

B4. Integrasi Gate Laravel:
    Gate::before atau definisikan Gate dari permissions -> $user->can('task.create')
    berfungsi native. Ini yang dipakai controller & frontend.

## FASE C — ONBOARDING SERVICE (blueprint §3, §4)

C1. app/Services/UserService.php -> onboardNewUser(array $userData, array $roleConfig)

C2. 🔴 ADMIN-ONLY (F-91). Ini BUKAN self-signup.
    Route di belakang middleware admin/permission 'user.manage'.
    JANGAN sentuh/hidupkan register.tsx (dihapus Hari-6). JANGAN buat route register.

C3. 🔴 TRANSACTION (blueprint §4.1):
    Seluruh alур dalam DB::transaction(). Gagal di tengah -> rollback penuh.
    TIDAK BOLEH ada orphaned user (user tanpa role). Test wajib untuk ini.

C4. Tiga bentuk payload (blueprint §4.3):
    - role_id ada                          -> assign role eksisting
    - base_role_id + custom_permissions[]  -> clone role default, modifikasi, simpan baru
    - new_role_name + permissions[]        -> role baru dari kosong
    Percabangan if-else terpusat di service, BUKAN tersebar di controller.

C5. 🔴 PASSWORD (F-92):
    Hash SINKRON via cast 'password' => 'hashed' bawaan Laravel (bcrypt).
    Blueprint bilang "asinkron" - itu keliru teknis. JANGAN buat antrian/job hashing.

C6. Validasi role_name baru: cegah duplikat per-organization, cegah nama yang
    bentrok dengan role sistem, sanitasi (jangan izinkan karakter aneh).

## FASE D — MIGRASI SEMUA CONTROLLER (F-90) — INTI PEKERJAAN

D1. Dari inventaris LANGKAH 0: ganti SETIAP isAdmin() / cek enum menjadi
    cek permission konkret.

    Terjemahan matriks Business-Flow §6 (konfirmasi penamaan di LANGKAH 0):
    - buat/edit user, kelola role      -> user.manage
    - kelola jam kerja                 -> workschedule.manage
    - buat/edit project, atur member   -> project.manage
    - atur status project              -> status.manage
    - buat/edit/hapus task             -> task.manage
    - approve/reject review            -> task.approve
    - lihat semua project & dashboard  -> project.viewAll
    - ubah status task sendiri         -> (semua role, tidak perlu permission khusus)

D2. Role sistem 'admin' -> punya SEMUA permission.
    Role sistem 'member' -> hanya yang setara perilaku Hari-3 (ubah status task
    sendiri, lihat project yang di-assign, upload attachment output).
    Petakan PERSIS perilaku sekarang - jangan menambah/mengurangi hak diam-diam.

D3. Frontend: HandleInertiaRequests bagikan permissions user (bukan role string).
    Sidebar & tombol (Edit/Hapus/Approve) tampil berdasarkan can(...), bukan
    role === 'admin'. F-44-style: jangan hardcode nama role di React.

D4. 🔴 HAPUS shim isAdmin() setelah semua titik migrasi.
    grep isAdmin -> hanya boleh tersisa di definisi deprecated yang lalu dihapus.
    Akhir Fase D: nol pemanggilan isAdmin() di app/ dan resources/js.

D5. F-15 tetap: semua cek permission WAJIB di dalam batas organization.
    User organisasi A tidak bisa punya role/permission organisasi B.

## FASE E — UI ROLE MANAGEMENT

E1. Halaman kelola role (permission 'user.manage'):
    - Daftar role (tandai is_system - tidak bisa dihapus/rename)
    - Buat role baru: nama + centang permission per module
    - Edit role: ubah permission (role sistem: permission TIDAK bisa dikurangi
      di bawah minimum fungsionalnya - jelaskan di UI kenapa admin tak bisa
      dilucuti user.manage)
    - Hapus role: TOLAK kalau masih ada user memakainya (pola F-19).
      Pesan: "Masih ada N user dengan role ini. Pindahkan dulu."

E2. Halaman onboarding user (permission 'user.manage'):
    - Form: nama, email, + pilih salah satu dari 3 mode payload (C4)
    - Mode "role baru" munculkan matriks permission
    - Password: generate acak, tampilkan SEKALI (jangan simpan plaintext)

E3. is_default: tepat 1 role boleh is_default per organization (pola radio F-74).
    User baru tanpa role eksplisit -> dapat role default.

## FASE F — SEEDER (sekalian benahi F-86)

F1. Seed 2 role sistem + kamus permission lengkap + pemetaan.
    admin = semua permission. member = subset perilaku Hari-3.

F2. 🔴 F-86: perbaiki seeder dev yang melanggar invarian.
    Assignee task WAJIB jadi project_user dulu, baru di-assign
    (StoreTaskRequest mensyaratkannya). Seeder harus menghasilkan state yang
    MUNGKIN dibuat lewat UI, bukan state mustahil.

F3. ProductionSeeder: 1 role admin sistem, 1 user admin dengan role itu.
    Email admin: TANYA BOSS (jangan pakai yang salah-tangkap dari sesi lalu).

## FASE G — TEST (di MySQL, F-83)

G1. tests/Feature/RolePermissionTest.php
    - role sistem admin punya semua permission
    - member TIDAK punya task.manage (F-29 terjaga lewat permission sekarang)
    - hapus role yang dipakai user -> ditolak
    - role sistem tidak bisa dihapus/rename

G2. tests/Feature/OnboardingTest.php
    - 3 mode payload masing-masing menghasilkan user + role benar
    - 🔴 transaction rollback: paksa gagal di tengah -> TIDAK ada orphaned user
    - onboarding butuh permission user.manage -> member ditolak 403
    - password ter-hash (bukan plaintext di DB)

G3. tests/Feature/PermissionEnforcementTest.php
    - tiap permission benar-benar menjaga route-nya
    - user org A tidak bisa akses role/permission org B (F-15)

G4. 103 test lama tetap lulus. F-78 berlaku: yang pecah karena enum->role_id
    diperbarui + DILAPORKAN. Yang pecah karena kode salah, PERBAIKI KODE.

## DILARANG KERAS

JANGAN sisakan enum role & RBAC berdampingan (aturan induk F-88)
JANGAN hidupkan register / self-signup (F-91)
JANGAN buat hashing asinkron/job (F-92)
JANGAN hardcode nama role di kode atau React (F-90)
JANGAN beri permissions kolom organization_id (kamus global, A2)
JANGAN sisakan satu pun isAdmin() di akhir Fase D (D4)
JANGAN menambah/mengurangi hak admin/member diam-diam - petakan PERSIS Hari-3 (D2)
JANGAN drop enum sebelum data dipindah & diverifikasi (A4)
JANGAN buat multi-role per user - Boss pilih 1 role/user (F-89)
JANGAN buat scheduler/cron (F-38)
JANGAN mulai upgrade L13 - itu SETELAH RBAC
JANGAN install dependency tanpa approval Boss
JANGAN edit dokumen docs/ - lapor kalau perlu

## STANDAR KOMENTAR
CLAUDE.md §3. Header klasifikasi tiap file baru. Sebut F-N di komentar business rule.

## DEFINITION OF DONE

🔴 F-75: item [BROWSER] wajib bukti browser nyata. F-83: test di MySQL.

[ ] grep isAdmin app/ resources/js -> No matches (shim sudah dihapus, D4)
[ ] grep "role === 'admin'" resources/js -> No matches
[ ] grep enum role di migration users -> kolom sudah di-drop (A4)
[ ] User::whereNull('role_id')->count() -> 0 (semua user punya role)
[ ] Role::where('is_system',true)->count() -> 2 (admin, member)
[ ] [BROWSER] login admin -> semua menu admin muncul (via permission)
[ ] [BROWSER] login member -> menu admin TIDAK muncul, perilaku = Hari-3
[ ] [BROWSER] buat role baru "supervisor" + pilih permission -> tersimpan, nol hardcode
[ ] [BROWSER] onboard user baru mode "role baru" -> user+role tercipta, bisa login
[ ] [BROWSER] hapus role yang dipakai user -> ditolak dengan pesan
[ ] Transaction: onboarding gagal di tengah -> nol orphaned user (test G2)
[ ] php artisan test -> SEMUA lulus di MySQL (103 lama + baru)
[ ] Test lama yang pecah karena enum->role_id -> DILAPORKAN + sebabnya
[ ] npx tsc --noEmit 0 error, pint + build + lint bersih
[ ] F-86: seeder dev tidak lagi melanggar invarian project_user

## FORMAT LAPORAN AKHIR

STATUS   : [SELESAI / BLOCKED / BUTUH KEPUTUSAN]
DIUBAH   : <daftar file>
BUKTI    : <perintah + output + bukti browser>
MIGRASI  : <berapa titik isAdmin() diganti, di controller mana saja>
DEVIASI  : <kalau nol, tulis "NOL" eksplisit>
RISIKO   : <apa yang bisa pecah, khususnya di controller yang tersentuh>
NEXT     : <opsi + rekomendasi — TUNGGU keputusan Boss>

Mulai dari LANGKAH 0. Jangan tulis kode sebelum Boss bilang "LANJUT".

=== SELESAI ===
```

---

## CATATAN UNTUK BOSS

**Kenapa LANGKAH 0 minta inventaris `isAdmin()` dulu?**
Karena itu **peta seluruh pekerjaan Fase D**. Sebelum satu baris diubah, Boss dan Jarvis akan melihat persis berapa titik yang tersentuh — kalau ternyata 40 titik di 12 controller, itu informasi yang Boss perlu tahu sebelum menyetujui, bukan kejutan di tengah jalan.

**Kenapa enum di-drop di migration TERPISAH (A4), bukan sekaligus?**
Kalau enum dan `role_id` diubah dalam satu migration lalu ada yang salah, rollback-nya berantakan. Pisahkan: tambah `role_id` → pindah data → verifikasi → baru drop enum. Tiap langkah bisa mundur aman. Ini pola yang sama dengan `work_schedules` circular FK (F-54).

**Kenapa `permissions` TIDAK punya `organization_id`?**
Permission adalah **kamus sistem** — `task.create` berarti sama di semua tenant. Yang per-organization adalah **role** (organisasi A punya "supervisor" sendiri) dan **pemetaannya**. Kalau permission ikut di-tenant, Boss akan punya ribuan baris duplikat yang identik. Role bermakna per-tenant; permission universal.

**Yang Jarvis tolak dari blueprint, sudah tercatat permanen:**
- "Buat login user" **bukan** self-signup (F-91) — onboarding tetap admin-only, register tetap mati
- "Hash asinkron" **keliru teknis** (F-92) — Laravel hash sinkron, sudah aman

**Peringatan urutan.** Boss memilih RBAC sebelum L13. Itu sah — enum→role_id gratis mumpung data kosong. Konsekuensinya: saat L13 nanti, ada lebih banyak kode untuk diverifikasi. Bukan masalah besar, tapi Jarvis catat supaya bukan kejutan.

**Peringatan kapasitas.** `CLAUDE.md` kini **tepat 200 baris — batas mutlak**. Sebelum finding berikutnya, Jarvis akan pindahkan aturan lama yang sudah stabil ke `docs/` dan sisakan pointer. File ini dimuat tiap request; tiap baris dibayar terus.

---

**Setelah RBAC, antrean yang tersisa:**

| Urutan | Isi |
|---|---|
| 1 | **Upgrade L13 + PHP 8.3** (F-65) — mumpung data masih kosong |
| 2 | **Deploy v0.5** — jam KPI mulai berdetak |
| 3 | **v0.8** — dashboard, counter, recurring, attachment, extension (+ tutup F-87) |

**F-2 mensyaratkan ≥1 bulan data nyata sebelum scoring v1.5 dikalibrasi. Jam itu tidak mulai sampai langkah 2.**
