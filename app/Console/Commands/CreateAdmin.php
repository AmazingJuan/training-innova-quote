<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an administrator from the terminal: the first one on a new
 * installation, or a way back in if every password was lost. No password
 * lives in the repository.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crea un usuario administrador (pide nombre, correo, teléfono y contraseña)';

    public function handle(): int
    {
        $data = [
            'name' => text('Nombre', required: true),
            'email' => text('Correo electrónico', required: true, validate: ['email' => 'email|unique:users,email']),
            'phone' => text('Teléfono', required: true, validate: ['phone' => 'string|min:10|max:15']),
            'password' => password('Contraseña (mínimo 12 caracteres)', required: true, validate: ['password' => 'min:12']),
        ];

        // Prompts validate interactively; this also covers non-interactive input.
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|min:10|max:15',
            'password' => 'required|string|min:12',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // The password is hashed by the model's cast.
        $user = User::create([...$data, 'role' => UserRole::Admin]);
        $this->info("Administrador creado: {$user->email}");

        return self::SUCCESS;
    }
}
