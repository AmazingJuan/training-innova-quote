<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garantiza una única fila en configurations (puede tener campos vacíos).
     */
    public function up(): void
    {
        if (DB::table('configurations')->count() > 0) {
            return;
        }

        $row = [
            'notification_email' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('configurations', 'application_url')) {
            $row['application_url'] = null;
        }
        DB::table('configurations')->insert($row);
    }

    public function down(): void
    {
        // No eliminar datos: la fila puede haberse editado.
    }
};
