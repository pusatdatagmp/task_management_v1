<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Organization Utama
        $organization = Organization::create([
            'name' => 'DEEVATECH',
            'slug' => 'deevatech',
        ]);

        // 2. Generate System Roles (Admin & Member)
        $roles = RolePermissionSeeder::seedSystemRolesForOrganization($organization);

        // 3. Buat Akun Admin
        User::create([
            'organization_id' => $organization->id,
            'name' => 'Admin Boss',
            'email' => 'admin@deevatech.test',
            'password' => bcrypt('password'),
            'role_id' => $roles['admin']->id,
            'employment_type' => 'internal',
            'is_active' => true,
        ]);

    }
}
