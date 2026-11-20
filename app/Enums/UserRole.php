<?php

namespace App\Enums;

/**
 * What an account may do in the admin panel. Every account runs the panel;
 * only administrators manage the accounts themselves (users and passwords).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::User => 'Usuario',
        };
    }
}
