<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * BUSINESS RULE: F-90/RBAC — RefreshDatabase (tests/Pest.php) migrasi ulang
     * tiap test, tapi TIDAK auto-seed kecuali diminta lewat $seed/$seeder di
     * sini. Katalog permission (global, lihat RolePermissionSeeder) HARUS ada
     * SEBELUM test mana pun jalan — UserFactory::admin()/member() bergantung
     * padanya untuk sync role_permission. Role per-organisasi TETAP dibuat
     * on-demand oleh factory (organisasi baru tiap test), BUKAN di sini.
     */
    protected $seed = true;

    protected string $seeder = RolePermissionSeeder::class;
}
