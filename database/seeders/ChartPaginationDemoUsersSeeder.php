<?php

/**
 * ==========================================================
 * MODUL       : ChartPaginationDemoUsersSeeder
 * KLASIFIKASI : DATA (seeder, BUKAN wajib jalan otomatis)
 * TUJUAN      : Permintaan Boss (2026-08-22) -- data uji manual untuk paginasi
 *               widget "Beban per Kategori" Command Center (10 member/halaman,
 *               lihat command-center.tsx CHART_PAGE_SIZE). Tim aktif organisasi
 *               ini cuma 6 orang saat dibuat -- di bawah ambang paginasi, jadi
 *               Boss butuh user tambahan supaya kontrol Prev/Next benar-benar
 *               muncul & bisa dicoba. TIDAK didaftarkan di DatabaseSeeder::run()
 *               -- jalankan manual saat butuh:
 *                   php artisan db:seed --class=ChartPaginationDemoUsersSeeder
 * DIPANGGIL   : php artisan db:seed --class=... (manual, Boss)
 * MEMANGGIL   : Organization::first() (single-tenant, F-5, pola sama seeder
 *               demo lain), UserFactory (REUSE F-38 -- nol logic buat-user baru
 *               di sini; factory sudah urus role_id='member' via
 *               configure()/RolePermissionSeeder::seedSystemRolesForOrganization()).
 * DATA MASUK  : -
 * DATA KELUAR : 17 baris users baru (nama/email acak dari Faker), organization_id
 *               DIPAKSA ke organisasi existing (BUKAN organisasi baru bawaan
 *               UserFactory::definition()), role 'member', is_active=true.
 * RISIKO      : F-16 -- user ini TIDAK BOLEH di-hard-delete kalau Boss mau
 *               bersihkan nanti. Nonaktifkan SATU-SATU lewat toggleActive()
 *               (halaman Pengguna & Peran, tombol nonaktifkan), BUKAN query
 *               DELETE manual -- riwayat KPI/assignee (kalaupun nanti mereka
 *               di-assign task) wajib utuh selamanya. User ini SENGAJA tidak
 *               diberi task/time segment apa pun -- murni buat menaikkan
 *               JUMLAH baris di tabel "Team Work Load"/chart "Beban per
 *               Kategori" (mereka tetap muncul dengan longgar_minutes = penuh
 *               kapasitas, todo/achievement = 0, karena nol tugas).
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChartPaginationDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // F-5: single-tenant sampai v3.0 -- ambil organisasi SATU-SATUNYA yang
        // ada, pola sama seeder demo lain (MemberCategoryChartDemoSeeder dst).
        $organization = Organization::firstOrFail();

        $before = User::where('organization_id', $organization->id)->where('is_active', true)->count();

        // SUMBER: UserFactory::definition() default bikin Organization::factory()
        // BARU -- override organization_id di sini SUPAYA 17 user ini masuk
        // organisasi Boss yang SAMA, bukan organisasi baru terpisah. role_id
        // TIDAK diisi manual (factory's configure() urus otomatis -> 'member').
        User::factory()->count(17)->create(['organization_id' => $organization->id]);

        $after = User::where('organization_id', $organization->id)->where('is_active', true)->count();

        $this->command?->info('17 user demo dibuat (role member, is_active=true).');
        $this->command?->info("Jumlah user aktif organisasi: {$before} -> {$after}.");
        $this->command?->info('Cek: widget "Beban per Kategori" Command Center (/dashboard/overview) -- kontrol Prev/Next sekarang harus muncul di bawah chart.');
    }
}
