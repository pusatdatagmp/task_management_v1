<?php

/**
 * ==========================================================
 * MODUL       : UserFactory
 * KLASIFIKASI : DATA
 * TUJUAN      : Generate data uji User untuk seeder & test — default role member,
 *               employment_type internal (F-18 hook v3.0), is_active true.
 *               F-88/F-90 — role_id (bukan enum) dibuat ON-DEMAND lewat
 *               RolePermissionSeeder::seedSystemRolesForOrganization() supaya
 *               test TIDAK bergantung urutan seeder global (organisasi baru
 *               dibuat tiap test lewat Organization::factory(), butuh role
 *               sistemnya sendiri juga baru).
 * DIPANGGIL   : Seeder, test Feature/Unit yang butuh actingAs()
 * MEMANGGIL   : Organization::factory() (F-5 — user selalu terikat 1 organization),
 *               RolePermissionSeeder (katalog permission + role sistem per organisasi)
 * DATA MASUK  : -
 * DATA KELUAR : User instance dengan password ter-hash sekali (cache static::$password)
 * RISIKO      : Kalau organization_id tidak di-set di sini, semua test yang bergantung
 *               OrganizationScope (F-15) akan gagal query karena user tidak punya tenant.
 *               role_id WAJIB terisi (F-89) — user tanpa role_id lolos dari SEMUA
 *               cek permission (Gate::before -> hasPermission() -> role null -> false),
 *               bukan bug keamanan (default-deny), tapi test jadi tidak representatif.
 * ==========================================================
 */

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'employment_type' => 'internal',
            'is_active' => true,
        ];
    }

    /**
     * KONTRAK: isi role_id SETELAH user (dan organization_id-nya) benar-benar
     * tersimpan. WORKAROUND — closure attribute biasa ala `'role_id' => fn
     * ($attributes) => ...` TIDAK BISA dipakai di sini: dicoba dulu, GAGAL runtime
     * ("Object of class OrganizationFactory could not be converted to string")
     * karena Laravel MENGGABUNG raw attribute definition()+state() DULU (closure
     * ikut digabung mentah, organization_id di titik itu MASIH Factory instance
     * yang belum di-resolve), baru DIEKSPANSI satu kali di akhir — beda dari
     * closure di dalam definition() sendiri yang memang dieksekusi berurutan
     * dengan sibling sudah resolved. afterCreating() aman karena $user di sana
     * sudah model tersimpan penuh (organization_id sudah integer nyata).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->role_id) {
                return;
            }

            $roleId = RolePermissionSeeder::seedSystemRolesForOrganization($user->organization)['member']->id;
            $user->update(['role_id' => $roleId]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * DIPAKAI: test permission — role admin dapat SEMUA permission (D2), lolos
     * gate mana pun. F-90: nama state ini DIPERTAHANKAN sama walau mekanisme di
     * baliknya berubah total (enum -> role_id) — 15+ file test yang sudah
     * memanggil `User::factory()->admin()` tidak perlu diubah satu-satu.
     *
     * SUMBER: afterCreating() (bukan raw attribute) — sama seperti configure(),
     * lihat penjelasan di atasnya. Callback ini terdaftar SETELAH callback
     * configure(), jadi jalan BELAKANGAN dan menimpa role 'member' bawaan
     * dengan role 'admin' — urutan ini yang membuat state ini efektif.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $roleId = RolePermissionSeeder::seedSystemRolesForOrganization($user->organization)['admin']->id;
            $user->update(['role_id' => $roleId]);
        });
    }
}
