<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The business units and their request forms. Users are not seeded (no
 * password lives in the repository): the first administrator is created with
 * `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([BusinessUnitSeeder::class, EncuadreSeeder::class]);
    }
}
