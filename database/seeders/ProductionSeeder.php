<?php

/**
 * ==========================================================
 * MODUL       : ProductionSeeder
 * KLASIFIKASI : DATA
 * TUJUAN      : Seed produksi — TERPISAH dari DatabaseSeeder (dev). Hanya data
 *               yang WAJIB ada supaya aplikasi bisa dipakai: 1 organization,
 *               1 work_schedule, 1 admin. TIDAK ADA project/task dummy — data
 *               nyata mulai dari NOL saat tim mulai memakai (Hari-7 §E1).
 * DIPANGGIL   : php artisan db:seed --class=ProductionSeeder (SEKALI saja, deploy awal)
 * MEMANGGIL   : Organization, User, WorkSchedule, RolePermissionSeeder (RBAC §F)
 * DATA MASUK  : -
 * DATA KELUAR : Database produksi
 * RISIKO      : SUMBER : F-16/F-51 — jalankan ULANG seeder ini di DB yang sudah
 *               berisi data akan membuat organization/admin DUPLIKAT (tidak ada
 *               guard idempotency di sini, sengaja — seeder produksi memang
 *               dirancang SEKALI PAKAI saat deploy awal, bukan operasi rutin).
 *               Password admin di-generate ACAK dan HANYA dicetak sekali ke
 *               console saat seeding — TIDAK disimpan plaintext di mana pun.
 *               Boss WAJIB salin & simpan password itu sebelum menutup terminal,
 *               lalu ganti sendiri lewat halaman profil setelah login pertama.
 * ==========================================================
 */

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // SUMBER: nama organisasi & email admin dikonfirmasi Boss langsung
        // (Hari-7 §E1) — BUKAN dikarang. Kalau organisasi/email produksi
        // sebenarnya berbeda, ubah dua baris ini SEBELUM menjalankan seeder,
        // atau edit langsung lewat halaman Pengaturan setelah login pertama.
        $organization = Organization::create([
            'name' => 'DEEVATECH',
            'slug' => 'deevatech',
        ]);

        // SUMBER: RBAC §F — role 'admin'/'member' pindah dari kolom enum (dihapus,
        // migrasi 100500) ke tabel roles/permissions. Role sistem organisasi ini
        // WAJIB ada SEBELUM admin dibuat (role_id NOT NULL FK).
        $roles = RolePermissionSeeder::seedSystemRolesForOrganization($organization);

        // BUSINESS RULE: password acak 24 karakter, dicetak SEKALI ke console.
        // Str::password() (bukan Str::random()) supaya hasilnya sudah memenuhi
        // aturan kompleksitas default Laravel (huruf besar/kecil, angka, simbol)
        // -- tim tidak perlu ganti password pertama kali kalau memang tidak mau,
        // walau tetap disarankan.
        $adminPassword = Str::password(24);

        $admin = User::create([
            'organization_id' => $organization->id,
            'name' => 'Admin',
            'email' => 'admin@deevatech.com',
            'password' => bcrypt($adminPassword),
            'role_id' => $roles['admin']->id,
            'employment_type' => 'internal',
            'is_active' => true,
        ]);

        // SUMBER: Sen-Jum 08:00-17:00, kapasitas 480 menit/hari — dikonfirmasi
        // Boss (Hari-7 §E1), sama dengan seeder dev. F-40: baris PERTAMA, ubah
        // setting nanti = INSERT baris baru lewat halaman Pengaturan Jam Kerja,
        // BUKAN edit baris ini.
        WorkSchedule::create([
            'organization_id' => $organization->id,
            'effective_from' => now()->toDateString(),
            'days_of_week' => [1, 2, 3, 4, 5],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'daily_capacity_minutes' => 480,
            'created_by' => $admin->id,
        ]);

        $this->command?->info('=================================================');
        $this->command?->info('  SEED PRODUKSI SELESAI — SALIN PASSWORD INI SEKARANG');
        $this->command?->info('=================================================');
        $this->command?->info("  Email    : {$admin->email}");
        $this->command?->info("  Password : {$adminPassword}");
        $this->command?->info('=================================================');
        $this->command?->warn('  Password ini TIDAK disimpan plaintext dan TIDAK akan ditampilkan lagi.');
        $this->command?->warn('  Segera login dan ganti password lewat halaman Profil.');
    }
}
