<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The first administrator, taken from the environment so no password lives in
 * the repository:
 *
 *   ADMIN_NAME="Gerencia" ADMIN_EMAIL=... ADMIN_PASSWORD=... php artisan db:seed
 *
 * The rest of the users are created from the admin panel.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Sin ADMIN_EMAIL y ADMIN_PASSWORD no se crea el administrador inicial.');

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error('ADMIN_PASSWORD debe tener al menos 12 caracteres.');

            return;
        }

        User::query()->firstOrCreate(['email' => $email], [
            'name' => env('ADMIN_NAME', 'Administrador'),
            'password' => $password, // Hashed by the model's cast.
            'phone' => env('ADMIN_PHONE', '0000000000'),
        ]);

        $this->command?->info("Administrador inicial: {$email}");
    }
}
