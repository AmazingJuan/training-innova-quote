<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Users are not seeded: no password lives in the repository. The first
 * administrator is created with `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ExampleDataSeeder::class);
    }
}
