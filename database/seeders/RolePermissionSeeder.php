<?php

/**
 * ==========================================================
 * MODUL       : RolePermissionSeeder
 * KLASIFIKASI : DATA
 * TUJUAN      : Satu-satunya sumber kebenaran kamus permission RBAC (F-88/F-90) +
 *               cara membuat 2 role sistem (admin/member) untuk satu organisasi.
 *               Dipakai DatabaseSeeder, ProductionSeeder, DAN base test case
 *               (tests/TestCase.php) — supaya kamus permission TIDAK pernah
 *               didefinisikan dua kali di tempat berbeda (pola sama F-72/F-76:
 *               satu sumber, bukan dua yang bisa drift).
 * DIPANGGIL   : DatabaseSeeder, ProductionSeeder, tests/TestCase.php ($seeder),
 *               Database\Factories\UserFactory (bikin role on-demand per organisasi)
 * MEMANGGIL   : Permission, Role, Organization
 * DATA MASUK  : -
 * DATA KELUAR : Tabel permissions (global), roles + role_permission (per organisasi)
 * RISIKO      : SUMBER : D2 (RBAC) — role 'admin' dapat SEMUA permission di
 *               katalog ini, role 'member' dapat NOL (perilaku Hari-3: aksi yang
 *               boleh member — ubah status task sendiri, lihat project ter-assign,
 *               upload attachment output — semuanya DIJAGA lewat cek assignee/
 *               keanggotaan project, BUKAN permission RBAC. Kalau nanti ada
 *               permission yang seharusnya juga dimiliki member, itu PERUBAHAN
 *               PERILAKU sadar, bukan efek samping seeder ini).
 *               v1.2/v1.5 (F-134): SATU pengecualian ke "admin dapat semua" —
 *               `leaderboard.view` ditandai `default_admin => false` di catalog(),
 *               nol organisasi (baru maupun existing, re-seed) yang admin-nya
 *               otomatis dapat permission ini. Boss WAJIB assign manual per role
 *               lewat UI Role Management (F-135) kalau mau ada yang lihat leaderboard.
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Seeder;

/**
 * KONTRAK: extends Seeder (bukan class util biasa) supaya BISA dipanggil lewat
 * `php artisan db:seed --class=RolePermissionSeeder` ATAU sebagai
 * `tests/TestCase.php::$seeder` (RefreshDatabase auto-seed). run() SENGAJA cuma
 * isi katalog global (tidak butuh organisasi) — pembuatan role per-organisasi
 * tetap lewat seedSystemRolesForOrganization() static, dipanggil eksplisit oleh
 * DatabaseSeeder/ProductionSeeder/UserFactory yang SUDAH tahu organisasinya.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        self::seedPermissionCatalog();
    }

    /**
     * KONTRAK: katalog permission RBAC — terjemahan matriks 03-BUSINESS-FLOW §6
     * (dikonfirmasi Boss, RBAC §D1). Format "module.aksi". Menambah permission
     * baru = tambah baris di sini, BUKAN hardcode nama di controller (F-90).
     *
     * `default_admin` (opsional, default TRUE kalau tidak ditulis — SEMUA baris
     * lama nol berubah perilaku): apakah baris ini ikut disync OTOMATIS ke role
     * admin tiap organisasi di seedSystemRolesForOrganization() di bawah. SATU-
     * SATUNYA baris `false` adalah `leaderboard.view` (F-134/F-135) — management-
     * only BENAR-BENAR berarti nol pemegang default, admin TERMASUK, sampai Boss
     * assign manual lewat UI Role Management. Baris lain TIDAK PERNAH pakai flag
     * ini (admin memang seharusnya dapat semuanya, itu makna "admin" di sistem ini).
     *
     * @return array<int, array{permission_name: string, module: string, default_admin?: bool}>
     */
    public static function catalog(): array
    {
        return [
            ['permission_name' => 'user.manage', 'module' => 'user'],
            ['permission_name' => 'workschedule.manage', 'module' => 'workschedule'],
            ['permission_name' => 'project.manage', 'module' => 'project'],
            ['permission_name' => 'status.manage', 'module' => 'status'],
            ['permission_name' => 'task.manage', 'module' => 'task'],
            ['permission_name' => 'task.approve', 'module' => 'task'],
            ['permission_name' => 'project.viewAll', 'module' => 'project'],
            // v0.8 H2 (F-52/F-95): dashboard TIM — admin saja (matriks BF §6
            // "Lihat dashboard tim"), member NOL permission sesuai desain RBAC.
            ['permission_name' => 'dashboard.view', 'module' => 'dashboard'],
            // v1.0 H4 (F-116): log GLOBAL (lintas project) — data pengawasan,
            // BUKAN default terbuka ke member. Timeline PER-TASK di detail task
            // TIDAK pakai permission ini sama sekali (F-95 membership, lihat
            // TaskController::show()) — permission ini KHUSUS halaman global.
            ['permission_name' => 'activity.view', 'module' => 'activity'],
            // v1.2/v1.5 (F-134/F-135): leaderboard skor MANAGEMENT-ONLY. Beda dari
            // SEMUA baris di atas — admin TIDAK otomatis dapat (default_admin=false),
            // Boss assign manual per role lewat UI Role Management yang SUDAH ADA
            // (F-135, form ini otomatis dapat grup "leaderboard" baru, nol kode UI).
            ['permission_name' => 'leaderboard.view', 'module' => 'leaderboard', 'default_admin' => false],
        ];
    }

    /**
     * KONTRAK: pastikan seluruh baris katalog() ADA di tabel permissions
     * (idempotent — firstOrCreate, aman dipanggil berkali-kali).
     */
    public static function seedPermissionCatalog(): void
    {
        foreach (self::catalog() as $permission) {
            Permission::firstOrCreate(
                ['permission_name' => $permission['permission_name']],
                ['permission_name' => $permission['permission_name'], 'module' => $permission['module']],
            );
        }
    }

    /**
     * KONTRAK: pastikan organisasi ini punya 2 role sistem (admin/member),
     * idempotent. Admin dapat SEMUA permission katalog KECUALI yang ditandai
     * `default_admin => false` (F-134 — leaderboard.view SATU-SATUNYA per hari
     * ini); member NOL (lihat RISIKO di header file). Dipanggil seeder produksi/
     * dev DAN UserFactory (Fase B/D test) supaya test tidak bergantung urutan
     * seeder global jalan.
     *
     * @return array{admin: Role, member: Role}
     */
    public static function seedSystemRolesForOrganization(Organization $organization): array
    {
        self::seedPermissionCatalog();

        // F-129: withoutGlobalScope WAJIB di sini. Role pakai BelongsToOrganization
        // (OrganizationScope, F-15) yang menambah WHERE organization_id=Auth::user()
        // ->organization_id ke SELECT firstOrCreate. Kalau dipanggil untuk $organization
        // lain sementara Auth::user() masih di organisasi berbeda (mis. admin org A
        // memicu seed role utk org B baru), SELECT itu ber-AND dua organization_id yang
        // beda -> tidak pernah match walau baris org B sudah ada -> INSERT ganda ->
        // duplicate-key crash. Query di sini eksplisit target $organization->id yang
        // DIMINTA CALLER, bukan org user yang sedang login.
        $admin = Role::withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            ['organization_id' => $organization->id, 'role_name' => 'admin'],
            ['is_system' => true, 'is_default' => false],
        );

        // F-134: filter catalog() ke nama permission yang BOLEH otomatis ke admin
        // (default_admin !== false) SEBELUM sync -- syncWithoutDetaching HANYA
        // menambah, tidak pernah mencabut, jadi kalau Boss sudah assign manual
        // leaderboard.view ke admin lewat UI, baris itu TETAP aman (tidak ikut
        // filter ini, cuma tidak ditambah ULANG di sini).
        $adminPermissionNames = collect(self::catalog())
            ->reject(fn (array $p) => ($p['default_admin'] ?? true) === false)
            ->pluck('permission_name');
        $admin->permissions()->syncWithoutDetaching(Permission::whereIn('permission_name', $adminPermissionNames)->pluck('id'));

        $member = Role::withoutGlobalScope(OrganizationScope::class)->firstOrCreate(
            ['organization_id' => $organization->id, 'role_name' => 'member'],
            ['is_system' => true, 'is_default' => true],
        );
        // SUMBER: D2 — member TIDAK dapat permission RBAC apa pun. Perilaku
        // Hari-3 miliknya (ubah status task sendiri, dst) dijaga cek
        // assignee/keanggotaan project di service/controller, bukan RBAC.

        return ['admin' => $admin, 'member' => $member];
    }
}
