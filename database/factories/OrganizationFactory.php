<?php

/**
 * ==========================================================
 * MODUL       : OrganizationFactory
 * KLASIFIKASI : DATA
 * TUJUAN      : Generate data uji Organization untuk seeder & test (F-5 — akar tenant,
 *               semua factory lain butuh organization_id dari sini).
 * DIPANGGIL   : Seeder, factory model lain (UserFactory dkk), test Feature/Unit
 * MEMANGGIL   : Organization
 * DATA MASUK  : -
 * DATA KELUAR : Organization instance dengan name + slug unik
 * RISIKO      : Slug tabrakan antar test kalau Str::random tidak dipertahankan.
 * ==========================================================
 */

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
        ];
    }
}
