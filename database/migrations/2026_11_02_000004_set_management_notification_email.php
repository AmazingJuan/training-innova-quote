<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Management's inbox, which receives every new request with the documents the
 * client attached. It stays editable in the admin (Configuración): this only
 * fills it in when no address has been set.
 */
return new class extends Migration
{
    private const EMAIL = 'gerencia@trainingcorporation.com.co';

    public function up(): void
    {
        DB::table('configurations')
            ->where(fn ($query) => $query->whereNull('notification_email')->orWhere('notification_email', ''))
            ->update(['notification_email' => self::EMAIL, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Leave it: it may have been edited in the admin since.
    }
};
